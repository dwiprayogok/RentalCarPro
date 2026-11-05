<?php

namespace App\Enums;

/**
 * Kategori pengeluaran operasional (di luar perawatan/servis yang dicatat di modul Perawatan).
 * `perCar()` = kategori yang lazimnya melekat ke satu mobil (dipakai sebagai saran di form).
 */
enum ExpenseCategory: string
{
    use HasLabel;

    case Bbm = 'bbm';
    case Cuci = 'cuci';
    case ParkirTol = 'parkir_tol';
    case Asuransi = 'asuransi';
    case Cicilan = 'cicilan';
    case Gaji = 'gaji';
    case SewaTempat = 'sewa_tempat';
    case Utilitas = 'utilitas';
    case Pemasaran = 'pemasaran';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Bbm => 'BBM',
            self::Cuci => 'Cuci & salon',
            self::ParkirTol => 'Parkir & tol',
            self::Asuransi => 'Asuransi',
            self::Cicilan => 'Cicilan/leasing',
            self::Gaji => 'Gaji & komisi',
            self::SewaTempat => 'Sewa tempat',
            self::Utilitas => 'Listrik, air & internet',
            self::Pemasaran => 'Pemasaran',
            self::Lainnya => 'Lainnya',
        };
    }

    public function perCar(): bool
    {
        return in_array($this, [self::Bbm, self::Cuci, self::ParkirTol, self::Asuransi, self::Cicilan], true);
    }
}
