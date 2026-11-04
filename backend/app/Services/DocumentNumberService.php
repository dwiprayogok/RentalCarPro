<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen urut & atomik: TRX-2026-0001, reset per tahun (tahun WIB).
 *
 * MySQL: satu pernyataan atomik
 *   INSERT ... ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)
 * yang langsung mengambil exclusive lock pada baris counter (prefix, year). Pola
 * "insert lalu SELECT FOR UPDATE" sengaja tidak dipakai karena dua transaksi bersamaan
 * bisa sama-sama memegang shared lock lalu deadlock saat naik ke exclusive lock
 * (ditemukan oleh ConcurrentBookingTest). LAST_INSERT_ID() bersifat per koneksi.
 *
 * Panggil di dalam transaksi pembuat dokumen: nomor ikut di-rollback bila penyimpanan gagal,
 * sehingga tidak ada nomor bolong karena error validasi.
 */
class DocumentNumberService
{
    public function next(string $prefix = 'TRX', ?Carbon $at = null): string
    {
        $year = (int) ($at ?? now())->copy()->setTimezone(config('app.display_timezone'))->format('Y');

        $number = DB::getDriverName() === 'mysql'
            ? $this->nextMysql($prefix, $year)
            : $this->nextGeneric($prefix, $year);

        return sprintf('%s-%d-%04d', $prefix, $year, $number);
    }

    private function nextMysql(string $prefix, int $year): int
    {
        $now = now()->format('Y-m-d H:i:s');
        // Baris affected: 1 = baris counter baru dibuat (nomor 1), 2 = counter dinaikkan.
        // Untuk baris baru, LAST_INSERT_ID() berisi id auto-increment, BUKAN nomor, jadi tidak dipakai.
        $affected = DB::affectingStatement(
            'INSERT INTO document_sequences (prefix, year, last_number, created_at, updated_at)
             VALUES (?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1), updated_at = VALUES(updated_at)',
            [$prefix, $year, $now, $now],
        );

        return $affected === 1 ? 1 : (int) DB::selectOne('SELECT LAST_INSERT_ID() AS n')->n;
    }

    /** SQLite/PostgreSQL (dev/test): kunci tingkat database/baris sudah cukup. */
    private function nextGeneric(string $prefix, int $year): int
    {
        return DB::transaction(function () use ($prefix, $year) {
            $sequence = DocumentSequence::query()->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->first();
            if (! $sequence) {
                DocumentSequence::query()->insertOrIgnore([
                    'prefix' => $prefix, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $sequence = DocumentSequence::query()->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->firstOrFail();
            }
            $sequence->increment('last_number');

            return $sequence->last_number;
        });
    }
}
