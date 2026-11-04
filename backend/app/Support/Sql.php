<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Potongan SQL yang berbeda per database (MySQL produksi, SQLite untuk test cepat).
 * Waktu di database UTC; laporan dikelompokkan per tanggal WIB (UTC+7, tanpa DST).
 */
final class Sql
{
    private static function mysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    /** Tanggal WIB (YYYY-MM-DD) dari kolom DATETIME UTC. */
    public static function wibDate(string $column): string
    {
        return self::mysql()
            ? "DATE(CONVERT_TZ({$column}, '+00:00', '+07:00'))"
            : "date({$column}, '+7 hours')";
    }

    public static function greatest(string $a, string $b): string
    {
        return self::mysql() ? "GREATEST({$a}, {$b})" : "MAX({$a}, {$b})";
    }

    public static function least(string $a, string $b): string
    {
        return self::mysql() ? "LEAST({$a}, {$b})" : "MIN({$a}, {$b})";
    }

    /** Selisih menit (b − a), bisa negatif. */
    public static function minutesBetween(string $a, string $b): string
    {
        return self::mysql()
            ? "TIMESTAMPDIFF(MINUTE, {$a}, {$b})"
            : "CAST(ROUND((julianday({$b}) - julianday({$a})) * 1440) AS INTEGER)";
    }

    /**
     * Ekspresi "uang masuk untuk tagihan" per baris pembayaran:
     * DP, pelunasan, potong deposit (+), refund (−). Deposit titipan tidak dihitung.
     */
    public static function incomeExpr(string $alias = 'p'): string
    {
        return "CASE WHEN {$alias}.type IN ('dp','pelunasan','deposit_potong') THEN {$alias}.amount "
            ."WHEN {$alias}.type = 'refund' THEN -{$alias}.amount ELSE 0 END";
    }
}
