<?php

namespace Tests\Feature\Reports;

use App\Enums\PaymentType;
use App\Models\Car;
use App\Models\Expense;
use App\Models\MaintenanceRecord;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laporan diuji dengan data seeder deterministik (ReportDemoSeeder, 60 hari).
 * Setiap angka agregat dari database dibandingkan dengan perhitungan ulang di PHP dari baris mentah.
 */
class ReportTest extends TestCase
{
    private const FROM = '2026-09-01';

    private const TO = '2026-10-21';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', 'UTC')); // 15.00 WIB
        $this->seed([DemoUserSeeder::class, DemoDataSeeder::class]);
    }

    private function owner()
    {
        return User::where('email', 'owner@rentalpro.test')->first();
    }

    /** Pembayaran dalam rentang (tanggal WIB) dihitung manual. */
    private function paymentsInRange(?string $method = null, ?int $carId = null)
    {
        $from = Carbon::parse(self::FROM, 'Asia/Jakarta')->startOfDay()->utc();
        $to = Carbon::parse(self::TO, 'Asia/Jakarta')->endOfDay()->utc();

        return RentalPayment::query()->with('rental')->get()
            ->filter(fn ($p) => $p->paid_at->betweenIncluded($from, $to))
            ->filter(fn ($p) => ! $method || ($method === 'deposit' ? $p->method === null : $p->method?->value === $method))
            ->filter(fn ($p) => ! $carId || $p->rental->car_id === $carId);
    }

    private function income($payments): int
    {
        return (int) $payments->sum(fn ($p) => $p->type->billEffect() * $p->amount);
    }

    public function test_seeder_produces_meaningful_dataset(): void
    {
        $this->assertGreaterThan(30, Rental::count());
        $this->assertGreaterThan(0, Rental::where('status', 'batal')->count());
        $this->assertGreaterThan(0, Rental::where('status', 'selesai')->where('late_fee', '>', 0)->count());
        $this->assertGreaterThan(0, Rental::where('status', '!=', 'batal')->whereColumn('paid_amount', '<', 'total_amount')->count());
        foreach (['dp', 'pelunasan', 'deposit', 'deposit_potong', 'deposit_kembali', 'refund'] as $t) {
            $this->assertGreaterThan(0, RentalPayment::where('type', $t)->count(), "jenis $t ada");
        }
    }

    public function test_payment_summary_matches_raw_rows(): void
    {
        $res = $this->actingAs($this->owner())->getJson('/api/v1/reports/payments?from='.self::FROM.'&to='.self::TO.'&per_page=10')->assertOk();

        $rows = $this->paymentsInRange();
        $res->assertJsonPath('summary.income_total', $this->income($rows))
            ->assertJsonPath('meta.total', $rows->count())
            ->assertJsonPath('meta.from', self::FROM);

        foreach ($res->json('summary.by_method') as $m) {
            $expected = $this->income($rows->filter(fn ($p) => ($p->method?->value ?? 'deposit') === $m['method']));
            $this->assertSame($expected, $m['income'], "metode {$m['method']}");
        }
        $this->assertSame((int) $rows->where('type', PaymentType::Deposit)->sum('amount'), $res->json('summary.deposit_flow.received'));

        // Seri harian: tanggal WIB berurutan tanpa celah, jumlahnya sama dengan total.
        $daily = $res->json('summary.daily');
        $this->assertSame(self::FROM, $daily[0]['date']);
        $this->assertSame(self::TO, end($daily)['date']);
        $this->assertCount(51, $daily);
        $this->assertSame($this->income($rows), array_sum(array_column($daily, 'amount')));
        $byDay = $rows->groupBy(fn ($p) => $p->paid_at->copy()->setTimezone('Asia/Jakarta')->toDateString())->map(fn ($g) => $this->income($g));
        foreach ($daily as $d) {
            $this->assertSame((int) ($byDay[$d['date']] ?? 0), $d['amount'], "hari {$d['date']}");
        }
    }

    public function test_payment_filters_by_method_and_car(): void
    {
        $car = Car::orderBy('id')->first();
        foreach (['tunai', 'transfer', 'qris', 'deposit'] as $method) {
            $this->actingAs($this->owner())->getJson('/api/v1/reports/payments?from='.self::FROM.'&to='.self::TO."&method={$method}&car_id={$car->id}")
                ->assertOk()
                ->assertJsonPath('summary.income_total', $this->income($this->paymentsInRange($method, $car->id)))
                ->assertJsonPath('meta.total', $this->paymentsInRange($method, $car->id)->count());
        }
    }

    public function test_revenue_by_car_matches_raw_rows(): void
    {
        $res = $this->actingAs($this->owner())->getJson('/api/v1/reports/revenue-by-car?from='.self::FROM.'&to='.self::TO)->assertOk();
        $from = Carbon::parse(self::FROM, 'Asia/Jakarta')->startOfDay()->utc();
        $to = Carbon::parse(self::TO, 'Asia/Jakarta')->endOfDay()->utc();

        foreach ($res->json('data') as $row) {
            $this->assertSame($this->income($this->paymentsInRange(null, $row['car_id'])), $row['income'], $row['car']);
            $started = Rental::where('car_id', $row['car_id'])->where('status', '!=', 'batal')->get()
                ->filter(fn ($r) => $r->start_at->betweenIncluded($from, $to));
            $this->assertSame($started->count(), $row['rentals']);
            $this->assertSame((int) $started->sum('billed_days'), $row['billed_days']);
        }
        $this->assertSame($this->income($this->paymentsInRange()), $res->json('summary.income_total'));
    }

    public function test_utilization_matches_manual_overlap(): void
    {
        $res = $this->actingAs($this->owner())->getJson('/api/v1/reports/utilization?from='.self::FROM.'&to='.self::TO)->assertOk();
        $from = Carbon::parse(self::FROM, 'Asia/Jakarta')->startOfDay()->utc();
        $end = now(); // dibatasi s/d sekarang
        $period = $from->diffInMinutes($end);

        foreach ($res->json('data') as $row) {
            $minutes = Rental::where('car_id', $row['car_id'])->whereIn('status', ['aktif', 'selesai'])->get()
                ->sum(function ($r) use ($from, $end) {
                    $s = ($r->picked_up_at ?? $r->start_at)->max($from);
                    $e = ($r->returned_at ?? ($r->status->value === 'aktif' ? now() : $r->planned_end_at))->min($end);

                    return max(0, (int) round($s->diffInMinutes($e, false)));
                });
            $this->assertEqualsWithDelta($minutes, $row['rented_minutes'], 1, $row['car']);
            $this->assertEqualsWithDelta(round($minutes / $period * 100, 1), $row['utilization_pct'], 0.11);
            $this->assertLessThanOrEqual(100, $row['utilization_pct']);
        }
        $this->assertGreaterThan(0, $res->json('summary.average_pct'));
    }

    public function test_maintenance_costs_match_raw_rows(): void
    {
        $res = $this->actingAs($this->owner())->getJson('/api/v1/reports/maintenance?from='.self::FROM.'&to='.self::TO)->assertOk();
        $records = MaintenanceRecord::all()->filter(fn ($m) => $m->performed_at->toDateString() >= self::FROM && $m->performed_at->toDateString() <= self::TO);

        $res->assertJsonPath('summary.total', (int) $records->sum('cost'))
            ->assertJsonPath('summary.records', $records->count());
        foreach ($res->json('data') as $row) {
            $this->assertSame((int) $records->where('car_id', $row['car_id'])->sum('cost'), $row['total']);
        }
    }

    public function test_profit_matches_raw_rows(): void
    {
        $this->assertGreaterThan(0, Expense::whereNull('car_id')->count(), 'seeder punya pengeluaran umum');
        $res = $this->actingAs($this->owner())->getJson('/api/v1/reports/profit?from='.self::FROM.'&to='.self::TO)->assertOk();
        $inRange = fn ($date) => $date->toDateString() >= self::FROM && $date->toDateString() <= self::TO;

        $sumMaint = 0;
        $sumCarExp = 0;
        foreach ($res->json('data') as $row) {
            $income = $this->income($this->paymentsInRange(null, $row['car_id']));
            $maint = (int) MaintenanceRecord::where('car_id', $row['car_id'])->get()->filter(fn ($m) => $inRange($m->performed_at))->sum('cost');
            $exp = (int) Expense::where('car_id', $row['car_id'])->get()->filter(fn ($e) => $inRange($e->spent_at))->sum('amount');
            $this->assertSame([$income, $maint, $exp, $income - $maint - $exp], [$row['income'], $row['maintenance'], $row['expenses'], $row['profit']], $row['car']);
            $sumMaint += $maint;
            $sumCarExp += $exp;
        }

        $general = (int) Expense::whereNull('car_id')->get()->filter(fn ($e) => $inRange($e->spent_at))->sum('amount');
        $this->assertGreaterThan(0, $general);
        $income = $this->income($this->paymentsInRange());
        $res->assertJsonPath('summary.general_expenses', $general)
            ->assertJsonPath('summary.net_profit', $income - $sumMaint - $sumCarExp - $general);
        $this->assertSame($general, collect($res->json('summary.general_by_category'))->sum('total'));

        // Urut dari laba terbesar.
        $profits = array_column($res->json('data'), 'profit');
        $sorted = $profits;
        rsort($sorted);
        $this->assertSame($sorted, $profits);

        // Filter per mobil: pengeluaran umum tidak ikut.
        $car = Car::first();
        $one = $this->actingAs($this->owner())->getJson('/api/v1/reports/profit?from='.self::FROM.'&to='.self::TO."&car_id={$car->id}")->assertOk();
        $one->assertJsonCount(1, 'data')->assertJsonPath('summary.general_expenses', 0)
            ->assertJsonPath('summary.net_profit', $one->json('data.0.profit'));

        $csv = $this->actingAs($this->owner())->get('/api/v1/reports/profit/export?from='.self::FROM.'&to='.self::TO)->streamedContent();
        $this->assertStringContainsString('LABA BERSIH USAHA', $csv);
        $this->assertStringContainsString(';'.($income - $sumMaint - $sumCarExp - $general).';', $csv);
    }

    public function test_receivables_include_balance_and_running_late_fee(): void
    {
        // Jadikan satu sewa aktif terlambat 5 jam dengan tagihan lunas.
        $active = Rental::where('status', 'aktif')->first();
        $active->forceFill(['planned_end_at' => now()->subHours(5)])->save();

        $res = $this->actingAs($this->owner())->getJson('/api/v1/reports/receivables?per_page=100')->assertOk();
        $open = Rental::where('status', '!=', 'batal')->whereColumn('paid_amount', '<', 'total_amount')->get();

        $res->assertJsonPath('summary.count', $open->count())
            ->assertJsonPath('summary.balance_total', (int) $open->sum(fn ($r) => $r->total_amount - $r->paid_amount))
            ->assertJsonPath('summary.running_late_total', 250000);
        $this->assertContains($active->code, array_column($res->json('data'), 'code'));
    }

    public function test_report_queries_do_not_grow_with_data(): void
    {
        $urls = [
            '/api/v1/reports/payments?from='.self::FROM.'&to='.self::TO.'&per_page=50',
            '/api/v1/reports/revenue-by-car?from='.self::FROM.'&to='.self::TO,
            '/api/v1/reports/utilization?from='.self::FROM.'&to='.self::TO,
            '/api/v1/reports/profit?from='.self::FROM.'&to='.self::TO,
        ];
        $count = function (string $url) {
            $this->actingAs($this->owner())->getJson($url)->assertOk(); // pemanasan cache auth/permission
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->owner())->getJson($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $before = array_map($count, $urls);
        // Gandakan data pembayaran: jumlah query harus tetap (agregasi di DB, tanpa N+1).
        RentalPayment::query()->get()->each(fn ($p) => $p->replicate()->save());
        Expense::query()->get()->each(fn ($e) => $e->replicate()->saveQuietly());
        $this->assertSame($before, array_map($count, $urls));
        $this->assertLessThanOrEqual(10, max($before));
    }

    public function test_csv_exports(): void
    {
        foreach (['payments', 'receivables', 'revenue-by-car', 'utilization', 'maintenance', 'profit'] as $type) {
            $res = $this->actingAs($this->owner())->get("/api/v1/reports/{$type}/export?from=".self::FROM.'&to='.self::TO);
            $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $this->assertStringContainsString("laporan-{$type}-".self::FROM.'_'.self::TO.'.csv', $res->headers->get('Content-Disposition'));
            $csv = $res->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
            $this->assertGreaterThan(1, count(array_filter(explode("\n", $csv))), $type);
        }

        $lines = array_filter(explode("\n", trim(substr($this->actingAs($this->owner())->get('/api/v1/reports/payments/export?from='.self::FROM.'&to='.self::TO)->streamedContent(), 3))));
        $this->assertCount($this->paymentsInRange()->count() + 1, $lines);
    }

    public function test_only_report_view_permission(): void
    {
        $staff = $this->userWithRole('staff');
        foreach (['payments', 'receivables', 'revenue-by-car', 'utilization', 'maintenance', 'profit'] as $type) {
            $this->actingAs($staff)->getJson("/api/v1/reports/{$type}")->assertForbidden();
            $this->actingAs($staff)->get("/api/v1/reports/{$type}/export")->assertForbidden();
        }
        $this->actingAs($this->userWithRole('admin'))->getJson('/api/v1/reports/payments')->assertOk();
    }

    public function test_validation(): void
    {
        $this->actingAs($this->owner())->getJson('/api/v1/reports/payments?from=2026-10-10&to=2026-10-01')
            ->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->actingAs($this->owner())->getJson('/api/v1/reports/payments?method=cek')
            ->assertStatus(422)->assertJsonValidationErrors(['method']);
    }
}
