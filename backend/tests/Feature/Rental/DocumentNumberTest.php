<?php

namespace Tests\Feature\Rental;

use App\Models\DocumentSequence;
use App\Services\DocumentNumberService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    public function test_numbers_are_sequential_padded_and_reset_per_year(): void
    {
        $svc = app(DocumentNumberService::class);

        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00', 'UTC'));
        $this->assertSame('TRX-2026-0001', $svc->next('TRX'));
        $this->assertSame('TRX-2026-0002', $svc->next('TRX'));
        $this->assertSame('TRX-2026-0003', $svc->next('TRX'));

        // 31 Des 2026 18.00 UTC = 1 Jan 2027 01.00 WIB → tahun WIB yang dipakai.
        Carbon::setTestNow(Carbon::parse('2026-12-31 18:00', 'UTC'));
        $this->assertSame('TRX-2027-0001', $svc->next('TRX'));

        // Prefix lain punya urutan sendiri.
        $this->assertSame('INV-2027-0001', $svc->next('INV'));
    }

    public function test_number_is_rolled_back_with_failed_transaction(): void
    {
        $svc = app(DocumentNumberService::class);
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00', 'UTC'));
        $svc->next('TRX');

        try {
            \DB::transaction(function () use ($svc) {
                $svc->next('TRX');
                throw new \RuntimeException('gagal simpan');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('TRX-2026-0002', $svc->next('TRX'), 'Nomor dari transaksi gagal tidak terpakai');
    }

    public function test_first_number_of_new_year_is_one_even_when_counter_ids_are_high(): void
    {
        // Regresi: di MySQL, LAST_INSERT_ID() pada baris counter baru berisi id auto-increment.
        foreach (range(2000, 2010) as $y) {
            DocumentSequence::create(['prefix' => 'OLD', 'year' => $y, 'last_number' => 99]);
        }

        Carbon::setTestNow(Carbon::parse('2030-06-01 08:00', 'UTC'));
        $svc = app(DocumentNumberService::class);
        $this->assertSame('TRX-2030-0001', $svc->next('TRX'));
        $this->assertSame('TRX-2030-0002', $svc->next('TRX'));
    }
}
