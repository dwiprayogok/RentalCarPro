<?php

namespace App\Services\Owner;

use App\Enums\ShareBasis;
use App\Models\CarOwner;
use App\Models\OwnerSettlement;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\ReportService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bagi hasil mobil titipan.
 *
 * Per mobil milik pemilik: pendapatan diterima, perawatan, pengeluaran (definisi sama dengan
 * laporan Laba). Dasar bagi hasil:
 *  - kotor  → total pendapatan diterima (biaya ditanggung rental);
 *  - bersih → pendapatan − perawatan − pengeluaran (biaya ikut ditanggung pemilik).
 * Bagian pemilik = dasar × persen, dibulatkan ke rupiah, minimal 0 (rugi tidak dibawa ke periode berikutnya).
 */
class SettlementService
{
    public const DISK = 'private';

    public const SHARE_DAYS = 7;

    public function __construct(
        private readonly ReportService $reports,
        private readonly DocumentNumberService $numbers,
        private readonly SettingService $settings,
    ) {}

    /** @return array<string, mixed> */
    public function calculate(CarOwner $owner, string $from, string $to): array
    {
        $cars = $owner->cars()->withTrashed()->orderBy('brand')->orderBy('model')->get(['id', 'brand', 'model', 'plate_number', 'deleted_at']);
        $fin = $this->reports->carFinancials($cars->pluck('id')->all(), $from, $to);

        $lines = $cars->map(function ($car) use ($fin) {
            $f = $fin[$car->id];

            return [
                'car_id' => $car->id,
                'car' => $car->display_name,
                'plate_number' => $car->plate_number,
                'rentals' => $f['rentals'],
                'billed_days' => $f['billed_days'],
                'income' => $f['income'],
                'maintenance' => $f['maintenance'],
                'expenses' => $f['expenses'],
                'net' => $f['income'] - $f['maintenance'] - $f['expenses'],
            ];
        })->values();

        $income = (int) $lines->sum('income');
        $maintenance = (int) $lines->sum('maintenance');
        $expenses = (int) $lines->sum('expenses');
        $net = $income - $maintenance - $expenses;
        $base = $owner->share_basis === ShareBasis::Kotor ? $income : $net;
        $ownerAmount = max(0, (int) round($base * (float) $owner->share_percent / 100));

        return [
            'period_from' => $from,
            'period_to' => $to,
            'share_basis' => $owner->share_basis->value,
            'share_basis_label' => $owner->share_basis->label(),
            'share_percent' => (float) $owner->share_percent,
            'share_label' => $owner->shareLabel(),
            'income' => $income,
            'maintenance' => $maintenance,
            'expenses' => $expenses,
            'net' => $net,
            'base_amount' => $base,
            'owner_amount' => $ownerAmount,
            'business_amount' => $base - $ownerAmount,
            'loss' => $base < 0,
            'lines' => $lines->all(),
        ];
    }

    public function create(CarOwner $owner, string $from, string $to, User $by, ?string $notes = null): OwnerSettlement
    {
        return DB::transaction(function () use ($owner, $from, $to, $by, $notes) {
            // Kunci baris pemilik: dua admin tidak bisa membuat setoran periode yang sama bersamaan.
            $owner = CarOwner::query()->lockForUpdate()->findOrFail($owner->id);

            if (! $owner->cars()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['owner_id' => 'Pemilik ini belum punya mobil titipan.']);
            }

            $overlap = OwnerSettlement::query()->where('owner_id', $owner->id)
                ->where('period_from', '<=', $to)->where('period_to', '>=', $from)
                ->orderBy('period_from')->first();
            if ($overlap) {
                throw ValidationException::withMessages(['period' => "Periode ini tumpang tindih dengan setoran {$overlap->code} ("
                    .$overlap->period_from->locale('id')->translatedFormat('j M Y').' – '.$overlap->period_to->locale('id')->translatedFormat('j M Y').').']);
            }

            $c = $this->calculate($owner, $from, $to);

            return OwnerSettlement::create([
                'code' => $this->numbers->next('SET'),
                'owner_id' => $owner->id,
                'period_from' => $from,
                'period_to' => $to,
                'share_basis' => $c['share_basis'],
                'share_percent' => $c['share_percent'],
                'income' => $c['income'],
                'maintenance' => $c['maintenance'],
                'expenses' => $c['expenses'],
                'base_amount' => $c['base_amount'],
                'owner_amount' => $c['owner_amount'],
                'business_amount' => $c['business_amount'],
                'lines' => $c['lines'],
                'notes' => $notes,
                'created_by' => $by->id,
            ]);
        });
    }

    /** @param array{paid_at?: string|null, method: string, reference?: string|null} $data */
    public function pay(OwnerSettlement $settlement, array $data, User $by, ?UploadedFile $proof = null): OwnerSettlement
    {
        $stored = $proof?->storeAs('settlements/'.$settlement->id, Str::uuid().'.'.$proof->extension(), self::DISK);

        try {
            return DB::transaction(function () use ($settlement, $data, $by, $stored) {
                $locked = OwnerSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
                if ($locked->isPaid()) {
                    throw ValidationException::withMessages(['settlement' => "Setoran {$locked->code} sudah tercatat dibayar."]);
                }
                $locked->update([
                    'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at'], config('app.display_timezone'))->utc() : now(),
                    'paid_method' => $data['method'],
                    'paid_reference' => $data['reference'] ?? null,
                    'paid_proof_path' => $stored,
                    'paid_by' => $by->id,
                ]);

                return $locked;
            });
        } catch (\Throwable $e) {
            $stored && Storage::disk(self::DISK)->delete($stored);
            throw $e;
        }
    }

    /** Hanya setoran yang belum dibayar yang bisa dibatalkan (berlaku juga untuk pemilik usaha). */
    public function delete(OwnerSettlement $settlement): void
    {
        DB::transaction(function () use ($settlement) {
            $locked = OwnerSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($locked->isPaid()) {
                throw ValidationException::withMessages(['settlement' => "Setoran {$locked->code} sudah dibayar dan tidak bisa dibatalkan."]);
            }
            $locked->delete();
        });
    }

    public function proofUrl(OwnerSettlement $s): ?string
    {
        return $s->paid_proof_path ? URL::temporarySignedRoute('v1.settlements.proof', now()->addMinutes(10), ['settlement' => $s->id]) : null;
    }

    public function shareUrl(OwnerSettlement $s): string
    {
        return URL::temporarySignedRoute('v1.settlements.shared', now()->addDays(self::SHARE_DAYS), ['settlement' => $s->id]);
    }

    public function printUrl(OwnerSettlement $s): string
    {
        return URL::temporarySignedRoute('v1.settlements.shared', now()->addMinutes(2), ['settlement' => $s->id]);
    }

    /** Data slip (PDF & pesan WhatsApp). @return array<string, mixed> */
    public function slip(OwnerSettlement $s): array
    {
        $s->loadMissing(['owner', 'payer']);
        $tz = config('app.display_timezone');
        $d = fn ($date) => $date?->locale('id')->translatedFormat('j M Y');

        return [
            'code' => $s->code,
            'business' => [
                'name' => $this->settings->get('business_name', config('app.name')),
                'address' => $this->settings->get('business_address'),
                'whatsapp' => $this->settings->get('business_whatsapp'),
            ],
            'owner' => [
                'name' => $s->owner->name,
                'phone' => $s->owner->phone,
                'bank' => trim(implode(' · ', array_filter([$s->owner->bank_name, $s->owner->bank_account_number, $s->owner->bank_account_name ? "a.n. {$s->owner->bank_account_name}" : null]))) ?: null,
            ],
            'period' => $d($s->period_from).' – '.$d($s->period_to),
            'share_label' => rtrim(rtrim(number_format((float) $s->share_percent, 2, ',', '.'), '0'), ',').'% '.$s->share_basis->short(),
            'share_basis' => $s->share_basis->value,
            'lines' => $s->lines,
            'income' => $s->income,
            'maintenance' => $s->maintenance,
            'expenses' => $s->expenses,
            'net' => $s->income - $s->maintenance - $s->expenses,
            'base_amount' => $s->base_amount,
            'owner_amount' => $s->owner_amount,
            'business_amount' => $s->business_amount,
            'paid' => $s->isPaid(),
            'paid_at' => $s->paid_at?->copy()->setTimezone($tz)->locale('id')->translatedFormat('j M Y, H.i'),
            'paid_method' => $s->paid_method?->label(),
            'paid_reference' => $s->paid_reference,
            'notes' => $s->notes,
            'issued_at' => now()->setTimezone($tz)->locale('id')->translatedFormat('j M Y, H.i'),
        ];
    }

    public function pdf(OwnerSettlement $s): Response
    {
        return Pdf::loadView('settlements.slip', ['n' => $this->slip($s)])->setPaper('a4', 'portrait')
            ->stream("slip-bagi-hasil-{$s->code}.pdf");
    }

    public function whatsappUrl(OwnerSettlement $s): ?string
    {
        $phone = preg_replace('/\D/', '', (string) $s->owner->phone);
        if (! $phone) {
            return null;
        }
        $n = $this->slip($s);
        $text = implode("\n", array_filter([
            "Halo {$n['owner']['name']},",
            '',
            "Berikut slip bagi hasil mobil titipan dari {$n['business']['name']}:",
            "No. {$n['code']} · Periode {$n['period']}",
            'Pendapatan mobil: '.self::rupiah($n['income']),
            $n['share_basis'] === 'bersih' ? 'Laba bersih (setelah servis & pengeluaran): '.self::rupiah($n['net']) : null,
            "Bagian Anda ({$n['share_label']}): *".self::rupiah($n['owner_amount']).'*',
            $n['paid'] ? "Status: sudah ditransfer {$n['paid_at']} WIB".($n['paid_method'] ? " via {$n['paid_method']}" : '') : 'Status: segera kami transfer.',
            '',
            'Rincian (PDF, berlaku '.self::SHARE_DAYS.' hari):',
            $this->shareUrl($s),
        ], fn ($l) => $l !== null));

        return "https://wa.me/{$phone}?text=".rawurlencode($text);
    }

    public static function rupiah(int $v): string
    {
        return ($v < 0 ? '-' : '').'Rp '.number_format(abs($v), 0, ',', '.');
    }
}
