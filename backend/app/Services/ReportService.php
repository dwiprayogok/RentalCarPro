<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\MaintenanceType;
use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use App\Services\Rental\RentalBilling;
use App\Support\Sql;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan keuangan & operasional. Semua agregasi (SUM/COUNT/GROUP BY) dilakukan di database;
 * PHP hanya merapikan hasil. Rentang tanggal dalam WIB, dikonversi ke UTC untuk query.
 *
 * Filter: from, to (Y-m-d WIB), method (tunai|transfer|qris|deposit), car_id.
 */
class ReportService
{
    public function __construct(private readonly RentalBilling $billing) {}

    /** @return array{0: Carbon, 1: Carbon} rentang UTC [awal hari from WIB, akhir hari to WIB] */
    public function range(array $f): array
    {
        $tz = config('app.display_timezone');
        $from = isset($f['from']) ? Carbon::parse($f['from'], $tz) : Carbon::now($tz)->startOfMonth();
        $to = isset($f['to']) ? Carbon::parse($f['to'], $tz) : Carbon::now($tz);

        return [$from->startOfDay()->utc(), $to->endOfDay()->utc()];
    }

    /** Baris pembayaran dalam rentang (join rentals untuk filter mobil). */
    private function paymentsBase(array $f): QueryBuilder
    {
        [$from, $to] = $this->range($f);

        return DB::table('rental_payments as p')
            ->join('rentals as r', 'r.id', '=', 'p.rental_id')
            ->whereNull('p.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereBetween('p.paid_at', [$from, $to])
            ->when($f['method'] ?? null, fn ($q, $m) => $m === 'deposit'
                ? $q->whereNull('p.method')
                : $q->where('p.method', $m))
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->where('r.car_id', $id));
    }

    /**
     * Ringkasan pembayaran masuk: total pendapatan, per metode, per jenis, per hari, arus deposit.
     *
     * @return array<string, mixed>
     */
    public function paymentSummary(array $f): array
    {
        $income = Sql::incomeExpr('p');

        $byMethod = $this->paymentsBase($f)
            ->selectRaw("COALESCE(p.method, 'deposit') as method, SUM({$income}) as income, COUNT(*) as count")
            ->whereIn('p.type', ['dp', 'pelunasan', 'deposit_potong', 'refund'])
            ->groupByRaw("COALESCE(p.method, 'deposit')")
            ->get();

        $byType = $this->paymentsBase($f)
            ->selectRaw('p.type, SUM(p.amount) as amount, COUNT(*) as count')
            ->groupBy('p.type')
            ->pluck('amount', 'type')->map(fn ($v) => (int) $v);

        $day = Sql::wibDate('p.paid_at');
        $daily = $this->paymentsBase($f)
            ->selectRaw("{$day} as d, SUM({$income}) as income")
            ->groupByRaw($day)->orderByRaw($day)
            ->pluck('income', 'd')->map(fn ($v) => (int) $v);

        return [
            'income_total' => (int) $byMethod->sum('income'),
            'by_method' => collect(['tunai', 'transfer', 'qris', 'deposit'])->map(fn ($m) => [
                'method' => $m,
                'label' => ['tunai' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'deposit' => 'Dari deposit'][$m],
                'income' => (int) ($byMethod->firstWhere('method', $m)->income ?? 0),
                'count' => (int) ($byMethod->firstWhere('method', $m)->count ?? 0),
            ])->values()->all(),
            'by_type' => [
                'dp' => $byType['dp'] ?? 0,
                'pelunasan' => $byType['pelunasan'] ?? 0,
                'deposit_potong' => $byType['deposit_potong'] ?? 0,
                'refund' => $byType['refund'] ?? 0,
            ],
            'deposit_flow' => [
                'received' => $byType['deposit'] ?? 0,
                'returned' => $byType['deposit_kembali'] ?? 0,
                'applied' => $byType['deposit_potong'] ?? 0,
            ],
            'daily' => $this->fillDays($f, $daily),
        ];
    }

    /** Daftar pembayaran (paginasi) untuk tabel & CSV. */
    public function paymentRows(array $f): QueryBuilder
    {
        return $this->paymentsBase($f)
            ->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->join('cars as k', 'k.id', '=', 'r.car_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.received_by')
            ->select([
                'p.id', 'p.paid_at', 'p.type', 'p.method', 'p.amount', 'p.reference_number',
                'r.id as rental_id', 'r.code', 'c.name as customer_name', 'k.brand', 'k.model', 'k.plate_number', 'u.name as received_by',
            ])
            ->orderByDesc('p.paid_at')->orderByDesc('p.id');
    }

    /** Piutang saat ini (tidak dibatasi tanggal): sisa tagihan + estimasi denda berjalan. */
    public function receivables(array $f): array
    {
        $base = Rental::query()
            ->where('status', '!=', RentalStatus::Batal->value)
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->where('car_id', $id));

        $open = (clone $base)->whereColumn('paid_amount', '<', 'total_amount');
        $totals = (clone $open)->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total_amount - paid_amount), 0) as balance')->first();

        // Denda berjalan hanya untuk sewa aktif yang terlambat (jumlahnya kecil), dihitung di PHP.
        $overdue = (clone $base)->overdue()->get(['id', 'planned_end_at', 'daily_rate', 'status']);
        $running = $overdue->sum(fn (Rental $r) => $this->billing->runningLateFee($r)->total());

        return [
            'count' => (int) $totals->n,
            'balance_total' => (int) $totals->balance,
            'running_late_total' => (int) $running,
            'grand_total' => (int) $totals->balance + (int) $running,
            'query' => (clone $base)->where(fn ($q) => $q->whereColumn('paid_amount', '<', 'total_amount')
                ->orWhereIn('id', $overdue->pluck('id')))
                ->with(['customer:id,name,phone', 'car:id,brand,model,plate_number'])
                ->orderBy('start_at'),
        ];
    }

    /**
     * Pendapatan per mobil: uang masuk (per tanggal bayar) + jumlah sewa & hari sewa yang dimulai di periode.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function revenueByCar(array $f): Collection
    {
        [$from, $to] = $this->range($f);
        $income = Sql::incomeExpr('p');

        $revenue = $this->paymentsBase($f)
            ->selectRaw("r.car_id, SUM({$income}) as income")
            ->groupBy('r.car_id')->pluck('income', 'car_id');

        $rentals = DB::table('rentals')
            ->whereNull('deleted_at')
            ->where('status', '!=', RentalStatus::Batal->value)
            ->whereBetween('start_at', [$from, $to])
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->where('car_id', $id))
            ->selectRaw('car_id, COUNT(*) as rentals, SUM(billed_days) as days, SUM(late_fee) as late_fees, SUM(extra_fees_total) as extra_fees')
            ->groupBy('car_id')->get()->keyBy('car_id');

        return $this->cars($f)->map(fn (Car $car) => [
            'car_id' => $car->id,
            'car' => $car->display_name,
            'plate_number' => $car->plate_number,
            'income' => (int) ($revenue[$car->id] ?? 0),
            'rentals' => (int) ($rentals[$car->id]->rentals ?? 0),
            'billed_days' => (int) ($rentals[$car->id]->days ?? 0),
            'late_fees' => (int) ($rentals[$car->id]->late_fees ?? 0),
            'extra_fees' => (int) ($rentals[$car->id]->extra_fees ?? 0),
        ])->sortByDesc('income')->values();
    }

    /**
     * Utilisasi armada: menit mobil benar-benar keluar (serah terima s/d kembali; aktif s/d sekarang)
     * yang beririsan dengan periode, dibanding total menit periode (dibatasi s/d sekarang).
     *
     * @return array{period_minutes: int, average_pct: float, cars: Collection<int, array<string, mixed>>}
     */
    public function utilization(array $f): array
    {
        [$from, $to] = $this->range($f);
        $end = $to->copy()->min(now());
        $periodMinutes = max(0, (int) $from->diffInMinutes($end));

        // Nilai waktu dibuat server (bukan input pengguna) dan disisipkan sebagai literal ter-quote.
        // Ini menghindari urutan placeholder "?" yang berbeda antara ekspresi MySQL dan SQLite.
        $lit = fn (Carbon $d) => DB::getPdo()->quote($d->format('Y-m-d H:i:s'));
        $nowLit = $lit(now());
        $start = 'COALESCE(picked_up_at, start_at)';
        $stop = "COALESCE(returned_at, CASE WHEN status = 'aktif' THEN {$nowLit} ELSE planned_end_at END)";
        $overlap = Sql::minutesBetween(Sql::greatest($start, $lit($from)), Sql::least($stop, $lit($end)));

        $rows = DB::table('rentals')
            ->whereNull('deleted_at')
            ->whereIn('status', [RentalStatus::Aktif->value, RentalStatus::Selesai->value])
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->where('car_id', $id))
            ->whereRaw("{$start} < {$lit($end)}")
            ->whereRaw("{$stop} > {$lit($from)}")
            ->selectRaw("car_id, COUNT(*) as rentals, SUM(CASE WHEN ({$overlap}) > 0 THEN ({$overlap}) ELSE 0 END) as minutes")
            ->groupBy('car_id')->get()->keyBy('car_id');

        $cars = $this->cars($f)->map(function (Car $car) use ($rows, $periodMinutes) {
            $minutes = (int) ($rows[$car->id]->minutes ?? 0);

            return [
                'car_id' => $car->id,
                'car' => $car->display_name,
                'plate_number' => $car->plate_number,
                'status' => $car->status->value,
                'rentals' => (int) ($rows[$car->id]->rentals ?? 0),
                'rented_minutes' => $minutes,
                'rented_days' => round($minutes / 1440, 1),
                'utilization_pct' => $periodMinutes > 0 ? round(min(100, $minutes / $periodMinutes * 100), 1) : 0.0,
            ];
        })->sortByDesc('utilization_pct')->values();

        return [
            'period_minutes' => $periodMinutes,
            'average_pct' => $cars->isEmpty() ? 0.0 : round($cars->avg('utilization_pct'), 1),
            'cars' => $cars,
        ];
    }

    /** Biaya servis per mobil & per jenis (tanggal pengerjaan dalam periode). */
    public function maintenanceCosts(array $f): array
    {
        $tz = config('app.display_timezone');
        [$from, $to] = $this->range($f);
        $fromDate = $from->copy()->setTimezone($tz)->toDateString();
        $toDate = $to->copy()->setTimezone($tz)->toDateString();

        $rows = DB::table('maintenance_records')
            ->whereNull('deleted_at')
            ->whereBetween('performed_at', [$fromDate, $toDate])
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->where('car_id', $id))
            ->selectRaw('car_id, type, COUNT(*) as n, SUM(cost) as cost')
            ->groupBy('car_id', 'type')->get();

        $types = MaintenanceType::cases();
        $byCar = $this->cars($f)->map(function (Car $car) use ($rows, $types) {
            $mine = $rows->where('car_id', $car->id);

            return [
                'car_id' => $car->id,
                'car' => $car->display_name,
                'plate_number' => $car->plate_number,
                'records' => (int) $mine->sum('n'),
                'total' => (int) $mine->sum('cost'),
                'by_type' => collect($types)->mapWithKeys(fn ($t) => [$t->value => (int) ($mine->firstWhere('type', $t->value)->cost ?? 0)])->all(),
            ];
        })->filter(fn ($r) => $r['records'] > 0)->sortByDesc('total')->values();

        return [
            'total' => (int) $rows->sum('cost'),
            'records' => (int) $rows->sum('n'),
            'by_type' => collect($types)->map(fn ($t) => [
                'type' => $t->value, 'label' => $t->label(), 'total' => (int) $rows->where('type', $t->value)->sum('cost'),
            ])->values()->all(),
            'cars' => $byCar,
        ];
    }

    /** Total uang masuk dalam rentang UTC (dipakai dashboard). */
    /**
     * Laba per mobil = pendapatan diterima − biaya perawatan − pengeluaran mobil itu.
     * Pengeluaran umum (tanpa mobil) tidak dibagi ke mobil; ia mengurangi laba bersih usaha.
     * Total dihitung dari agregat mentah (bukan dari baris mobil) agar mobil yang sudah
     * dihapus tetap ikut terhitung.
     *
     * @return array{summary: array<string, mixed>, cars: Collection}
     */
    public function profit(array $f): array
    {
        $f = array_diff_key($f, ['method' => true]);
        $tz = config('app.display_timezone');
        [$from, $to] = $this->range($f);
        $fromDate = $from->copy()->setTimezone($tz)->toDateString();
        $toDate = $to->copy()->setTimezone($tz)->toDateString();
        $income = Sql::incomeExpr('p');

        $revenue = $this->paymentsBase($f)
            ->selectRaw("r.car_id, SUM({$income}) as income")
            ->groupBy('r.car_id')->pluck('income', 'car_id');

        $maintenance = DB::table('maintenance_records')
            ->whereNull('deleted_at')
            ->whereBetween('performed_at', [$fromDate, $toDate])
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->where('car_id', $id))
            ->selectRaw('car_id, SUM(cost) as total')
            ->groupBy('car_id')->pluck('total', 'car_id');

        $expenses = DB::table('expenses')
            ->whereNull('deleted_at')
            ->whereBetween('spent_at', [$fromDate, $toDate])
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q->where('car_id', $id)))
            ->selectRaw('car_id, category, SUM(amount) as total')
            ->groupBy('car_id', 'category')->get();

        $carExpenses = $expenses->whereNotNull('car_id');
        $general = DB::table('expenses')
            ->whereNull('deleted_at')->whereNull('car_id')
            ->whereBetween('spent_at', [$fromDate, $toDate])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')->pluck('total', 'category');

        $cars = $this->cars($f)->map(function (Car $car) use ($revenue, $maintenance, $carExpenses) {
            $in = (int) ($revenue[$car->id] ?? 0);
            $maint = (int) ($maintenance[$car->id] ?? 0);
            $mine = $carExpenses->where('car_id', $car->id);
            $exp = (int) $mine->sum('total');
            $profit = $in - $maint - $exp;

            return [
                'car_id' => $car->id,
                'car' => $car->display_name,
                'plate_number' => $car->plate_number,
                'income' => $in,
                'maintenance' => $maint,
                'expenses' => $exp,
                'expenses_by_category' => $mine->mapWithKeys(fn ($r) => [$r->category => (int) $r->total])->all(),
                'costs' => $maint + $exp,
                'profit' => $profit,
                'margin_pct' => $in > 0 ? round($profit / $in * 100, 1) : null,
            ];
        })->sortByDesc('profit')->values();

        $incomeTotal = (int) $revenue->sum();
        $maintTotal = (int) $maintenance->sum();
        $carExpTotal = (int) $carExpenses->sum('total');
        // Dengan filter mobil, pengeluaran umum tidak relevan (tidak dibagi per mobil).
        $generalTotal = ($f['car_id'] ?? null) ? 0 : (int) $general->sum();
        $net = $incomeTotal - $maintTotal - $carExpTotal - $generalTotal;

        return [
            'summary' => [
                'income' => $incomeTotal,
                'maintenance' => $maintTotal,
                'car_expenses' => $carExpTotal,
                'general_expenses' => $generalTotal,
                'general_by_category' => ($f['car_id'] ?? null) ? [] : collect(ExpenseCategory::cases())
                    ->map(fn ($c) => ['category' => $c->value, 'label' => $c->label(), 'total' => (int) ($general[$c->value] ?? 0)])
                    ->filter(fn ($r) => $r['total'] > 0)->sortByDesc('total')->values()->all(),
                'cars_profit' => $incomeTotal - $maintTotal - $carExpTotal,
                'net_profit' => $net,
                'margin_pct' => $incomeTotal > 0 ? round($net / $incomeTotal * 100, 1) : null,
            ],
            'cars' => $cars,
        ];
    }

    public function incomeBetween(Carbon $fromUtc, Carbon $toUtc): int
    {
        return (int) DB::table('rental_payments as p')
            ->whereNull('p.deleted_at')
            ->whereBetween('p.paid_at', [$fromUtc, $toUtc])
            ->selectRaw('COALESCE(SUM('.Sql::incomeExpr('p').'), 0) as s')
            ->value('s');
    }

    /** Uang masuk per hari WIB untuk N hari terakhir (termasuk hari ini), hari kosong = 0. */
    public function dailyIncome(int $days): array
    {
        $tz = config('app.display_timezone');
        $f = ['from' => Carbon::now($tz)->subDays($days - 1)->toDateString(), 'to' => Carbon::now($tz)->toDateString()];

        return $this->paymentSummaryDailyOnly($f);
    }

    private function paymentSummaryDailyOnly(array $f): array
    {
        $day = Sql::wibDate('p.paid_at');
        [$from, $to] = $this->range($f);
        $daily = DB::table('rental_payments as p')
            ->whereNull('p.deleted_at')
            ->whereBetween('p.paid_at', [$from, $to])
            ->selectRaw("{$day} as d, SUM(".Sql::incomeExpr('p').') as income')
            ->groupByRaw($day)
            ->pluck('income', 'd')->map(fn ($v) => (int) $v);

        return $this->fillDays($f, $daily);
    }

    /** @return list<array{date: string, amount: int}> */
    private function fillDays(array $f, Collection $values): array
    {
        $tz = config('app.display_timezone');
        [$from, $to] = $this->range($f);
        $cursor = $from->copy()->setTimezone($tz)->startOfDay();
        $last = $to->copy()->setTimezone($tz)->startOfDay();
        $out = [];
        while ($cursor->lte($last) && count($out) < 400) {
            $d = $cursor->toDateString();
            $out[] = ['date' => $d, 'amount' => (int) ($values[$d] ?? 0)];
            $cursor->addDay();
        }

        return $out;
    }

    /** @return Collection<int, Car> */
    private function cars(array $f): Collection
    {
        return Car::query()
            ->when($f['car_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('brand')->orderBy('model')
            ->get(['id', 'brand', 'model', 'plate_number', 'status']);
    }
}
