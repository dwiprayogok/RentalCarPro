<?php

namespace App\Enums;

/**
 * Status sewa yang disimpan. "Terlambat" BUKAN status tersimpan:
 * dihitung dari status aktif + waktu sekarang melewati planned_end_at.
 */
enum RentalStatus: string
{
    use HasLabel;

    case Booking = 'booking';
    case Aktif = 'aktif';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking',
            self::Aktif => 'Aktif',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    /** Status yang memblokir jadwal mobil/driver. */
    public static function blocking(): array
    {
        return [self::Booking->value, self::Aktif->value];
    }
}
