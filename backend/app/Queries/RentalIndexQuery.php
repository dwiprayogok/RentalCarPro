<?php

namespace App\Queries;

use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Query daftar transaksi (dipakai API daftar & ekspor CSV agar hasilnya identik).
 *
 * Filter "view":
 *  - aktif       : status aktif (termasuk yang terlambat)
 *  - terlambat   : aktif dan planned_end_at < sekarang
 *  - booking, selesai, batal
 *  - belum_lunas : bukan batal dan total terbayar < total tagihan
 */
class RentalIndexQuery
{
    public const VIEWS = ['semua', 'booking', 'aktif', 'terlambat', 'selesai', 'batal', 'belum_lunas'];

    /** @param array{search?: ?string, car_id?: ?int, customer_id?: ?int, from?: ?string, to?: ?string} $filters */
    public static function base(array $filters): Builder
    {
        $tz = config('app.display_timezone');

        return Rental::query()
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = Search::like($s);
                $compact = Search::like(preg_replace('/\s+/', '', $s));
                $q->where(fn (Builder $q) => $q
                    ->where('code', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', Search::like(preg_replace('/\D/', '', $s) ?: $s)))
                    ->orWhereHas('car', fn ($c) => $c->where('brand', 'like', $like)->orWhere('model', 'like', $like)
                        ->orWhereRaw("REPLACE(plate_number, ' ', '') like ?", [$compact])));
            })
            ->when($filters['car_id'] ?? null, fn (Builder $q, $id) => $q->where('car_id', $id))
            ->when($filters['customer_id'] ?? null, fn (Builder $q, $id) => $q->where('customer_id', $id))
            // Rentang tanggal mulai sewa, dalam tanggal WIB.
            ->when($filters['from'] ?? null, fn (Builder $q, $d) => $q->where('start_at', '>=', Carbon::parse($d, $tz)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn (Builder $q, $d) => $q->where('start_at', '<=', Carbon::parse($d, $tz)->endOfDay()->utc()));
    }

    public static function applyView(Builder $query, string $view): Builder
    {
        return match ($view) {
            'booking', 'aktif', 'selesai', 'batal' => $query->where('status', $view),
            'terlambat' => $query->where('status', RentalStatus::Aktif->value)->where('planned_end_at', '<', now()),
            'belum_lunas' => $query->where('status', '!=', RentalStatus::Batal->value)->whereColumn('paid_amount', '<', 'total_amount'),
            default => $query,
        };
    }

    /** @return array<string, int> */
    public static function counts(Builder $base): array
    {
        $now = now();
        $row = (clone $base)->toBase()->selectRaw(
            'COUNT(*) as semua,
             SUM(status = ?) as booking,
             SUM(status = ?) as aktif,
             SUM(status = ? AND planned_end_at < ?) as terlambat,
             SUM(status = ?) as selesai,
             SUM(status = ?) as batal,
             SUM(status <> ? AND paid_amount < total_amount) as belum_lunas',
            ['booking', 'aktif', 'aktif', $now, 'selesai', 'batal', 'batal'],
        )->first();

        return collect(self::VIEWS)->mapWithKeys(fn ($v) => [$v => (int) ($row->{$v} ?? 0)])->all();
    }
}
