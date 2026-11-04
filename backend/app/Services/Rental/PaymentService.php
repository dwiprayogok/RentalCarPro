<?php

namespace App\Services\Rental;

use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pembayaran sewa. Total terbayar dan saldo deposit SELALU dihitung ulang di server
 * dari tabel rental_payments (bukan dari angka kiriman klien).
 */
class PaymentService
{
    public const DISK = 'private';

    /**
     * @param  array{type: string, method: string, amount: int, paid_at?: ?string, reference_number?: ?string, notes?: ?string}  $data
     */
    public function record(Rental $rental, array $data, User $by, ?UploadedFile $proof = null): RentalPayment
    {
        $stored = null;

        try {
            return DB::transaction(function () use ($rental, $data, $by, $proof, &$stored) {
                /** @var Rental $locked */
                $locked = Rental::query()->lockForUpdate()->findOrFail($rental->id);
                $type = PaymentType::from($data['type']);
                $amount = (int) $data['amount'];

                if ($locked->status === RentalStatus::Batal) {
                    throw ValidationException::withMessages(['type' => 'Sewa yang dibatalkan tidak bisa menerima pembayaran.']);
                }

                if ($type->billEffect() > 0) {
                    $balance = $locked->total_amount - $locked->paid_amount;
                    if ($balance <= 0) {
                        throw ValidationException::withMessages(['amount' => 'Tagihan sewa ini sudah lunas.']);
                    }
                    if ($amount > $balance) {
                        throw ValidationException::withMessages([
                            'amount' => 'Jumlah melebihi sisa tagihan ('.self::rupiah($balance).'). Catat kelebihan sebagai deposit bila perlu.',
                        ]);
                    }
                }

                if ($type === PaymentType::Deposit && $locked->deposit_required > 0) {
                    $outstanding = $locked->deposit_required - $locked->deposit_held;
                    if ($outstanding <= 0) {
                        throw ValidationException::withMessages(['type' => 'Deposit untuk sewa ini sudah diterima penuh.']);
                    }
                    if ($amount > $outstanding) {
                        throw ValidationException::withMessages(['amount' => 'Jumlah melebihi sisa deposit yang diminta ('.self::rupiah($outstanding).').']);
                    }
                }

                if ($proof) {
                    $stored = $proof->storeAs("rentals/{$locked->id}/payments", Str::uuid().'.'.$proof->extension(), self::DISK);
                }

                $payment = $locked->payments()->create([
                    'type' => $type,
                    'method' => $data['method'],
                    'amount' => $amount,
                    'paid_at' => $data['paid_at'] ?? now(),
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'proof_path' => $stored,
                    'received_by' => $by->id,
                ]);

                $this->recalculate($locked);

                return $payment;
            });
        } catch (\Throwable $e) {
            if ($stored) {
                Storage::disk(self::DISK)->delete($stored);
            }
            throw $e;
        }
    }

    public function delete(RentalPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $rental = Rental::query()->lockForUpdate()->findOrFail($payment->rental_id);
            if ($rental->status === RentalStatus::Selesai) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran pada sewa yang sudah selesai tidak bisa dihapus.']);
            }
            $payment->delete();
            $this->recalculate($rental);
        });
    }

    /** Hitung ulang agregat dari baris pembayaran (sumber kebenaran). */
    public function recalculate(Rental $rental): void
    {
        $paid = 0;
        $deposit = 0;
        foreach ($rental->payments()->get(['type', 'amount']) as $p) {
            $paid += $p->type->billEffect() * $p->amount;
            $deposit += $p->type->depositEffect() * $p->amount;
        }

        $rental->forceFill([
            'paid_amount' => $paid,
            'deposit_held' => $deposit,
            'deposit_required' => max($rental->deposit_required, $deposit),
        ])->saveQuietly();
    }

    public function proofUrl(RentalPayment $payment): ?string
    {
        return $payment->proof_path
            ? URL::temporarySignedRoute('v1.rentals.payments.proof', now()->addMinutes(10), ['rental' => $payment->rental_id, 'payment' => $payment->id])
            : null;
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
