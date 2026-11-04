<?php

namespace App\Services\Rental;

use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Models\RentalFee;
use App\Models\RentalInspection;
use App\Models\RentalPayment;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Data nota sewa (dipakai modal nota, PDF server-side, dan pesan WhatsApp).
 * Semua angka berasal dari server: total, terbayar, sisa, denda, deposit.
 */
class InvoiceService
{
    public const SHARE_DAYS = 7;

    public function __construct(
        private readonly RentalBilling $billing,
        private readonly PricingService $pricing,
        private readonly SettingService $settings,
    ) {}

    /** @return array<string, mixed> */
    public function data(Rental $rental, bool $withLinks = true): array
    {
        $rental->loadMissing(['customer', 'car', 'driver', 'payments', 'fees', 'inspections']);
        $tz = config('app.display_timezone');
        $fmt = fn (?Carbon $d) => $d?->copy()->setTimezone($tz)->locale('id')->translatedFormat('j M Y, H.i');
        $b = $this->billing->summary($rental);

        // Rincian
        $lines = [[
            'label' => 'Sewa',
            'detail' => "{$rental->billed_days} hari × ".self::rupiah($rental->daily_rate)
                .(collect($rental->price_breakdown['lines'] ?? [])->contains('special', true) ? ' (termasuk tarif akhir pekan/libur)' : ''),
            'amount' => $rental->rental_amount,
        ]];
        if ($rental->discount > 0) {
            $lines[] = ['label' => 'Diskon', 'detail' => $rental->discount_reason, 'amount' => -$rental->discount];
        }

        $finished = $rental->status === RentalStatus::Selesai;
        if ($finished) {
            $late = $rental->returned_at ? $this->pricing->lateFee($rental->planned_end_at, $rental->returned_at, $rental->daily_rate) : null;
            $extraDaysAmount = $rental->late_extra_days * $rental->daily_rate;
            $lines[] = [
                'label' => 'Denda keterlambatan',
                'detail' => $late && $late->lateMinutes > 0
                    ? 'Terlambat '.ReturnService::duration($late->lateMinutes)
                        .($late->withinGrace ? ' (dalam toleransi)' : '')
                        .($rental->late_extra_days ? "; {$rental->late_extra_days} hari tambahan (".self::rupiah($extraDaysAmount).')' : '')
                        .($rental->late_fee - $extraDaysAmount > 0 ? "; {$late->chargedHours} jam".($late->capped ? ' (maks. tarif 1 hari)' : '') : '')
                    : 'Tepat waktu',
                'amount' => $rental->late_fee,
            ];
            foreach ($rental->fees as $fee) {
                /** @var RentalFee $fee */
                $lines[] = ['label' => 'Biaya '.mb_strtolower($fee->type->label()), 'detail' => $fee->reason, 'amount' => $fee->amount];
            }
            if ($rental->fees->isEmpty()) {
                $lines[] = ['label' => 'Biaya tambahan', 'detail' => null, 'amount' => 0];
            }
        } else {
            $lines[] = ['label' => 'Denda', 'detail' => $b['running_late_fee'] > 0 ? 'Estimasi berjalan, final saat Kembali' : null, 'amount' => 0];
            $lines[] = ['label' => 'Biaya tambahan', 'detail' => null, 'amount' => 0];
        }

        $sum = fn (PaymentType $t) => (int) $rental->payments->filter(fn (RentalPayment $p) => $p->type === $t)->sum('amount');
        $inspection = fn (string $type) => ($i = $rental->inspections->first(fn (RentalInspection $x) => $x->type->value === $type)) ? [
            'odometer' => $i->odometer,
            'fuel_level' => $i->fuel_level,
            'fuel_label' => self::fuel($i->fuel_level),
            'at' => $fmt($i->inspected_at),
            'issues' => collect($i->checklist ?? [])->where('ok', false)->map(fn ($c) => $c['item'].(! empty($c['note']) ? ": {$c['note']}" : ''))->values()->all(),
        ] : null;

        $terms = array_values(array_filter(array_map('trim', explode("\n", (string) $this->settings->get('invoice_terms', '')))));

        $data = [
            'business' => [
                'name' => $this->settings->get('business_name', config('app.name')),
                'tagline' => $this->settings->get('business_tagline', ''),
                'address' => $this->settings->get('business_address', ''),
                'whatsapp' => $this->settings->get('business_whatsapp', ''),
            ],
            'code' => $rental->code,
            'status' => $rental->isOverdue() ? 'terlambat' : $rental->status->value,
            'status_label' => $rental->isOverdue() ? 'Terlambat' : $rental->status->label(),
            'payment_status' => $b['payment_status'],
            'payment_status_label' => $b['payment_status_label'],
            'issued_at' => $fmt(now()),
            'customer' => [
                'name' => $rental->customer->name,
                'phone' => $rental->customer->phone,
                'nik_masked' => $rental->customer->nik_masked,
                'address' => $rental->customer->address,
            ],
            'car' => [
                'name' => $rental->car->display_name,
                'plate_number' => $rental->car->plate_number,
                'year' => $rental->car->year,
                'color' => $rental->car->color,
                'transmission' => $rental->car->transmission->label(),
            ],
            'service_type_label' => $rental->service_type->label(),
            'driver' => $rental->driver ? ['name' => $rental->driver->name, 'phone' => $rental->driver->phone] : null,
            'schedule' => [
                'start_at' => $fmt($rental->start_at),
                'planned_end_at' => $fmt($rental->planned_end_at),
                'picked_up_at' => $fmt($rental->picked_up_at),
                'returned_at' => $fmt($rental->returned_at),
                'billed_days' => $rental->billed_days,
            ],
            'lines' => $lines,
            'totals' => [
                'total' => $b['total_amount'],
                'paid' => $b['paid_amount'],
                'balance' => $b['balance'],
                'running_late_fee' => $b['running_late_fee'],
                'estimated_due' => $b['estimated_due'],
            ],
            'deposit' => [
                'required' => $rental->deposit_required,
                'received' => $sum(PaymentType::Deposit),
                'deducted' => $sum(PaymentType::DepositPotong),
                'refunded' => $sum(PaymentType::DepositKembali),
                'held' => $rental->deposit_held,
            ],
            'payments' => $rental->payments->map(fn (RentalPayment $p) => [
                'type_label' => $p->type->label(),
                'method_label' => $p->method?->label() ?? ($p->type === PaymentType::DepositPotong ? 'Dari deposit' : '-'),
                'paid_at' => $fmt($p->paid_at),
                'reference_number' => $p->reference_number,
                'amount' => $p->amount,
                'direction' => match ($p->type) {
                    PaymentType::DepositKembali, PaymentType::Refund => 'out',
                    PaymentType::DepositPotong => 'internal',
                    default => 'in',
                },
            ])->values()->all(),
            'inspections' => ['serah_terima' => $inspection('serah_terima'), 'kembali' => $inspection('kembali')],
            'note' => $finished ? null : 'Denda dan biaya tambahan akan difinalisasi saat pengembalian.',
            'terms' => $terms,
            'footer' => $this->settings->get('invoice_footer', 'Terima kasih.'),
        ];

        if ($withLinks) {
            $data['pdf_share_url'] = $this->shareUrl($rental);
            $data['whatsapp_url'] = $this->whatsappUrl($rental, $data);
            $data['whatsapp_message'] = $this->whatsappMessage($data);
        }

        return $data;
    }

    public function pdf(Rental $rental, bool $download = false): Response
    {
        $pdf = Pdf::loadView('invoices.rental', ['n' => $this->data($rental, withLinks: false)])->setPaper('a4', 'portrait');
        $name = "nota-{$rental->code}.pdf";

        return $download ? $pdf->download($name) : $pdf->stream($name);
    }

    /** Tautan PDF bertanda tangan untuk dibagikan ke pelanggan (berlaku 7 hari). */
    public function shareUrl(Rental $rental): string
    {
        return URL::temporarySignedRoute('v1.rentals.invoice.shared', now()->addDays(self::SHARE_DAYS), ['rental' => $rental->id]);
    }

    /** Tautan PDF sangat singkat (2 menit) untuk tombol Cetak di Admin Web, dibuka di tab baru tanpa bergantung cookie. */
    public function printUrl(Rental $rental): string
    {
        return URL::temporarySignedRoute('v1.rentals.invoice.shared', now()->addMinutes(2), ['rental' => $rental->id]);
    }

    /** @param array<string, mixed> $n */
    public function whatsappMessage(array $n): string
    {
        $t = $n['totals'];
        $lines = [
            "Halo {$n['customer']['name']},",
            '',
            "Berikut nota sewa dari {$n['business']['name']}:",
            "No. nota: {$n['code']} ({$n['status_label']})",
            "Mobil: {$n['car']['name']} · {$n['car']['plate_number']} · {$n['service_type_label']}",
            "Jadwal: {$n['schedule']['start_at']} – {$n['schedule']['planned_end_at']} WIB",
            'Total tagihan: '.self::rupiah($t['total']),
            'Pembayaran diterima: '.self::rupiah($t['paid']),
            'Sisa tagihan: '.self::rupiah($t['balance']).($t['running_late_fee'] > 0 ? ' (belum termasuk estimasi denda '.self::rupiah($t['running_late_fee']).')' : ''),
        ];
        if (! empty($n['pdf_share_url'])) {
            $lines[] = '';
            $lines[] = 'Nota lengkap (PDF, berlaku '.self::SHARE_DAYS.' hari):';
            $lines[] = $n['pdf_share_url'];
        }
        $lines[] = '';
        $lines[] = $n['footer'];

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $n */
    private function whatsappUrl(Rental $rental, array $n): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', $rental->customer->phone).'?text='.rawurlencode($this->whatsappMessage($n));
    }

    public static function fuel(int $level): string
    {
        return match (true) {
            $level <= 0 => 'Kosong',
            $level <= 25 => '¼',
            $level <= 50 => '½',
            $level <= 75 => '¾',
            default => 'Penuh',
        };
    }

    public static function rupiah(int $amount): string
    {
        return ($amount < 0 ? '− ' : '').'Rp '.number_format(abs($amount), 0, ',', '.');
    }
}
