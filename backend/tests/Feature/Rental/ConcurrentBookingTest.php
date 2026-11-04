<?php

namespace Tests\Feature\Rental;

use App\Models\Car;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/**
 * Race condition sungguhan: beberapa proses PHP terpisah membuat sewa BERSAMAAN.
 *
 * Tidak memakai RefreshDatabase (transaksi test tidak terlihat oleh proses lain), jadi
 * data di-commit lalu dibersihkan (truncate) sesudahnya. Butuh MySQL (row lock).
 * Setiap proses menahan transaksinya 300 ms setelah mengunci (RENTALPRO_RACE_DELAY_MS)
 * agar semua proses benar-benar tumpang tindih.
 */
class ConcurrentBookingTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Uji race condition membutuhkan MySQL (row-level lock).');
        }

        Artisan::call('migrate', ['--force' => true]);
        $this->truncateAll();
        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Kembalikan ke kondisi dasar yang diharapkan test lain (RefreshDatabase
            // hanya men-seed sekali saat migrasi pertama): tabel kosong + role & pengaturan.
            $this->truncateAll();
            $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
        }
        parent::tearDown();
    }

    private function truncateAll(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (Schema::getTableListing() as $table) {
            $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if ($name !== 'migrations') {
                DB::table($name)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();
    }

    /** @return list<string> */
    private function runConcurrently(array $jobs): array
    {
        $processes = [];
        foreach ($jobs as $args) {
            $p = new Process(
                [PHP_BINARY, base_path('tests/Support/race-create-rental.php'), ...array_map('strval', $args)],
                base_path(),
                ['RENTALPRO_RACE_DELAY_MS' => '300', 'APP_ENV' => 'testing'],
                null,
                60,
            );
            $p->start();
            $processes[] = $p;
        }

        return array_map(function (Process $p) {
            $p->wait();

            return trim($p->getOutput().$p->getErrorOutput());
        }, $processes);
    }

    public function test_only_one_of_parallel_bookings_for_same_car_wins_and_numbers_are_unique(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');
        $start = Carbon::now()->addDay()->startOfHour();
        $end = $start->copy()->addDay();

        // 1) Empat proses, mobil SAMA, jadwal sama → tepat satu berhasil.
        $car = Car::factory()->create();
        $results = $this->runConcurrently(array_map(fn () => [
            $car->id, Customer::factory()->selfDriveReady()->create()->id, $user->id, $start->toIso8601String(), $end->toIso8601String(),
        ], range(1, 4)));

        $ok = array_values(array_filter($results, fn ($r) => str_starts_with($r, 'OK ')));
        $conflicts = array_filter($results, fn ($r) => $r === 'CONFLICT car_id');
        $this->assertCount(1, $ok, 'Hasil: '.implode(' | ', $results));
        $this->assertCount(3, $conflicts, 'Hasil: '.implode(' | ', $results));
        $this->assertSame(1, DB::table('rentals')->where('car_id', $car->id)->count());

        // 2) Empat proses, mobil BERBEDA, bersamaan → semua berhasil dengan nomor nota unik & berurutan.
        $results = $this->runConcurrently(array_map(fn () => [
            Car::factory()->create()->id, Customer::factory()->selfDriveReady()->create()->id, $user->id, $start->toIso8601String(), $end->toIso8601String(),
        ], range(1, 4)));

        $codes = array_map(fn ($r) => substr($r, 3), array_filter($results, fn ($r) => str_starts_with($r, 'OK ')));
        $this->assertCount(4, $codes, 'Hasil: '.implode(' | ', $results));
        $this->assertCount(4, array_unique($codes));

        $year = Carbon::now('Asia/Jakarta')->format('Y');
        $all = DB::table('rentals')->orderBy('code')->pluck('code')->all();
        $this->assertSame(array_map(fn ($n) => sprintf('TRX-%s-%04d', $year, $n), range(1, 5)), $all, 'Tidak ada nomor ganda atau bolong');
    }
}
