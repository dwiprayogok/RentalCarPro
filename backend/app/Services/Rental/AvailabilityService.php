<?php

namespace App\Services\Rental;

use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Services\SettingService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cek bentrok jadwal mobil/driver.
 *
 * Sewa yang memblokir: status booking atau aktif. Rentang blokir sebuah sewa adalah
 * [start_at, akhir_efektif], dengan akhir_efektif = planned_end_at, KECUALI sewa aktif yang
 * sudah lewat jam kembali: mobil belum kembali sehingga diblokir sampai "sekarang".
 * Dua sewa bentrok bila jarak di antara keduanya kurang dari buffer (default 120 menit).
 *
 * Catatan: metode ini TIDAK mengunci. RentalService memanggilnya setelah mengunci baris mobil
 * (dan driver) dengan lockForUpdate di dalam transaksi, sehingga pengecekan + penyimpanan atomik.
 */
class AvailabilityService
{
    public function __construct(private readonly SettingService $settings) {}

    public function bufferMinutes(): int
    {
        return $this->settings->int('booking_buffer_minutes', 120);
    }

    /** @return Collection<int, Rental> */
    public function carConflicts(int $carId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreRentalId = null): Collection
    {
        return $this->conflicts(Rental::query()->where('car_id', $carId), $start, $end, $ignoreRentalId);
    }

    /** @return Collection<int, Rental> */
    public function driverConflicts(int $driverId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreRentalId = null): Collection
    {
        return $this->conflicts(Rental::query()->where('driver_id', $driverId), $start, $end, $ignoreRentalId);
    }

    public function effectiveEnd(Rental $rental, ?CarbonInterface $now = null): Carbon
    {
        $now = Carbon::instance($now ?? now());

        return $rental->status === RentalStatus::Aktif && $rental->planned_end_at->lt($now)
            ? $now
            : $rental->planned_end_at->copy();
    }

    /** @return Collection<int, Rental> */
    private function conflicts(Builder $query, CarbonInterface $start, CarbonInterface $end, ?int $ignoreRentalId): Collection
    {
        $buffer = $this->bufferMinutes();
        $windowStart = Carbon::instance($start)->subMinutes($buffer);
        $windowEnd = Carbon::instance($end)->addMinutes($buffer);

        return $query
            ->whereIn('status', RentalStatus::blocking())
            ->when($ignoreRentalId, fn ($q) => $q->whereKeyNot($ignoreRentalId))
            ->where('start_at', '<', $windowEnd)
            // Kandidat: berakhir setelah awal jendela, ATAU sewa aktif (bisa molor melewati planned_end_at).
            ->where(fn ($q) => $q->where('planned_end_at', '>', $windowStart)->orWhere('status', RentalStatus::Aktif->value))
            ->with('customer:id,name')
            ->orderBy('start_at')
            ->get()
            ->filter(fn (Rental $r) => $this->effectiveEnd($r)->gt($windowStart))
            ->values();
    }

    /**
     * Pesan bentrok yang ramah, mis.
     * "Bentrok dengan TRX-2026-0003 (21 Okt 15.41 – 22 Okt 15.41 WIB). Jeda antar sewa minimal 2 jam."
     *
     * @param  Collection<int, Rental>  $conflicts
     */
    public function conflictMessage(string $subject, Collection $conflicts): string
    {
        $tz = config('app.display_timezone');
        $fmt = fn (CarbonInterface $d) => Carbon::instance($d)->setTimezone($tz)->locale('id')->translatedFormat('j M H.i');
        $items = $conflicts->take(3)->map(fn (Rental $r) => "{$r->code} ({$fmt($r->start_at)} – {$fmt($this->effectiveEnd($r))} WIB)")->implode(', ');
        $buffer = $this->bufferMinutes();
        $bufferText = $buffer % 60 === 0 ? ($buffer / 60).' jam' : $buffer.' menit';

        return "{$subject} sudah terjadwal pada waktu tersebut: {$items}. Jeda antar sewa minimal {$bufferText}.";
    }
}
