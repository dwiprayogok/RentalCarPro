<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MaintenanceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\Rental\RentalBilling;
use App\Services\ReportService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan (hanya report.view: owner & admin). Filter: from, to (Y-m-d WIB), method, car_id.
 * Setiap laporan bisa diekspor CSV lewat /reports/{type}/export.
 */
class ReportController extends Controller
{
    public const TYPES = ['payments', 'receivables', 'revenue-by-car', 'utilization', 'maintenance', 'profit'];

    public function __construct(private readonly ReportService $reports) {}

    private function filters(Request $request): array
    {
        abort_unless($request->user()->can(Permissions::REPORT_VIEW), 403);

        $f = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'method' => ['nullable', 'in:tunai,transfer,qris,deposit'],
            'car_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], ['from' => 'tanggal awal', 'to' => 'tanggal akhir', 'method' => 'metode', 'car_id' => 'mobil']);

        [$from, $to] = $this->reports->range($f);
        abort_if($from->diffInDays($to) > 366, 422, 'Rentang laporan maksimal 1 tahun.');

        return $f;
    }

    private function meta(array $f): array
    {
        $tz = config('app.display_timezone');
        [$from, $to] = $this->reports->range($f);

        return ['from' => $from->copy()->setTimezone($tz)->toDateString(), 'to' => $to->copy()->setTimezone($tz)->toDateString()];
    }

    public function payments(Request $request): JsonResponse
    {
        $f = $this->filters($request);
        $page = $this->reports->paymentRows($f)->paginate($f['per_page'] ?? 20)->withQueryString();

        return response()->json([
            'summary' => $this->reports->paymentSummary($f),
            'data' => collect($page->items())->map(fn ($p) => $this->paymentRow($p))->values(),
            'meta' => $this->meta($f) + [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'from_row' => $page->firstItem(), 'to_row' => $page->lastItem(),
            ],
        ]);
    }

    public function receivables(Request $request, RentalBilling $billing): JsonResponse
    {
        $f = $this->filters($request);
        $r = $this->reports->receivables($f);
        $page = $r['query']->paginate($f['per_page'] ?? 20)->withQueryString();

        return response()->json([
            'summary' => collect($r)->except('query'),
            'data' => collect($page->items())->map(fn ($rental) => $this->receivableRow($rental, $billing))->values(),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }

    public function revenueByCar(Request $request): JsonResponse
    {
        $f = $this->filters($request);
        $rows = $this->reports->revenueByCar($f);

        return response()->json(['data' => $rows, 'summary' => ['income_total' => $rows->sum('income'), 'rentals' => $rows->sum('rentals')], 'meta' => $this->meta($f)]);
    }

    public function utilization(Request $request): JsonResponse
    {
        $f = $this->filters($request);
        $u = $this->reports->utilization($f);

        return response()->json(['data' => $u['cars'], 'summary' => ['average_pct' => $u['average_pct'], 'period_days' => round($u['period_minutes'] / 1440, 1)], 'meta' => $this->meta($f)]);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $f = $this->filters($request);
        $m = $this->reports->maintenanceCosts($f);

        return response()->json(['data' => $m['cars'], 'summary' => collect($m)->except('cars'), 'meta' => $this->meta($f)]);
    }

    /** Laba per mobil & laba bersih usaha (pendapatan − perawatan − pengeluaran). */
    public function profit(Request $request): JsonResponse
    {
        $f = $this->filters($request);
        $p = $this->reports->profit($f);

        return response()->json(['data' => $p['cars'], 'summary' => $p['summary'], 'meta' => $this->meta($f)]);
    }

    /** CSV (pemisah ";" + BOM UTF-8 agar langsung rapi di Excel Indonesia). */
    public function export(Request $request, string $type, RentalBilling $billing): StreamedResponse
    {
        $f = $this->filters($request);
        $meta = $this->meta($f);

        [$header, $rows] = match ($type) {
            'payments' => [
                ['Tanggal Bayar (WIB)', 'No. Nota', 'Pelanggan', 'Mobil', 'Plat', 'Jenis', 'Metode', 'Referensi', 'Jumlah', 'Pengaruh ke Pendapatan', 'Diterima Oleh'],
                (function () use ($f) {
                    foreach ($this->reports->paymentRows($f)->cursor() as $p) {
                        $r = $this->paymentRow($p);
                        yield [$r['paid_at_local'], $r['code'], $r['customer_name'], $r['car'], $r['plate_number'], $r['type_label'], $r['method_label'], $r['reference_number'], $r['amount'], $r['income'], $r['received_by']];
                    }
                })(),
            ],
            'receivables' => [
                ['No. Nota', 'Status', 'Pelanggan', 'No. HP', 'Mobil', 'Plat', 'Mulai (WIB)', 'Kembali Rencana (WIB)', 'Total', 'Terbayar', 'Sisa', 'Denda Berjalan', 'Total Piutang'],
                (function () use ($f, $billing) {
                    foreach ($this->reports->receivables($f)['query']->cursor() as $rental) {
                        $r = $this->receivableRow($rental, $billing);
                        yield [$r['code'], $r['status_label'], $r['customer_name'], $r['customer_phone'], $r['car'], $r['plate_number'], $r['start_local'], $r['planned_end_local'], $r['total'], $r['paid'], $r['balance'], $r['running_late_fee'], $r['due']];
                    }
                })(),
            ],
            'revenue-by-car' => [
                ['Mobil', 'Plat', 'Pendapatan Diterima', 'Jumlah Sewa', 'Hari Sewa', 'Denda', 'Biaya Tambahan'],
                $this->reports->revenueByCar($f)->map(fn ($r) => [$r['car'], $r['plate_number'], $r['income'], $r['rentals'], $r['billed_days'], $r['late_fees'], $r['extra_fees']]),
            ],
            'utilization' => [
                ['Mobil', 'Plat', 'Status', 'Jumlah Sewa', 'Hari Tersewa', 'Utilisasi (%)'],
                $this->reports->utilization($f)['cars']->map(fn ($r) => [$r['car'], $r['plate_number'], $r['status'], $r['rentals'], str_replace('.', ',', (string) $r['rented_days']), str_replace('.', ',', (string) $r['utilization_pct'])]),
            ],
            'maintenance' => [
                ['Mobil', 'Plat', 'Jumlah Catatan', 'Total Biaya', ...collect(MaintenanceType::cases())->map->label()->all()],
                $this->reports->maintenanceCosts($f)['cars']->map(fn ($r) => [$r['car'], $r['plate_number'], $r['records'], $r['total'], ...array_values($r['by_type'])]),
            ],
            'profit' => [
                ['Mobil', 'Plat', 'Pendapatan Diterima', 'Biaya Perawatan', 'Pengeluaran Mobil', 'Total Biaya', 'Laba', 'Margin (%)'],
                (function () use ($f) {
                    $p = $this->reports->profit($f);
                    foreach ($p['cars'] as $r) {
                        yield [$r['car'], $r['plate_number'], $r['income'], $r['maintenance'], $r['expenses'], $r['costs'], $r['profit'], $r['margin_pct'] === null ? '' : str_replace('.', ',', (string) $r['margin_pct'])];
                    }
                    $s = $p['summary'];
                    yield [];
                    yield ['Pengeluaran umum (tidak per mobil)', '', '', '', $s['general_expenses'], $s['general_expenses'], -$s['general_expenses'], ''];
                    yield ['LABA BERSIH USAHA', '', $s['income'], $s['maintenance'], $s['car_expenses'] + $s['general_expenses'], $s['maintenance'] + $s['car_expenses'] + $s['general_expenses'], $s['net_profit'], $s['margin_pct'] === null ? '' : str_replace('.', ',', (string) $s['margin_pct'])];
                })(),
            ],
            default => abort(404),
        };

        $name = "laporan-{$type}-{$meta['from']}_{$meta['to']}.csv";

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function paymentRow(object $p): array
    {
        $type = PaymentType::from($p->type);
        $tz = config('app.display_timezone');

        return [
            'id' => $p->id,
            'paid_at' => Carbon::parse($p->paid_at, 'UTC')->setTimezone($tz)->toIso8601String(),
            'paid_at_local' => Carbon::parse($p->paid_at, 'UTC')->setTimezone($tz)->format('Y-m-d H:i'),
            'rental_id' => $p->rental_id,
            'code' => $p->code,
            'customer_name' => $p->customer_name,
            'car' => trim("{$p->brand} {$p->model}"),
            'plate_number' => $p->plate_number,
            'type' => $type->value,
            'type_label' => $type->label(),
            'method' => $p->method,
            'method_label' => $p->method ? PaymentMethod::from($p->method)->label() : 'Dari deposit',
            'reference_number' => $p->reference_number,
            'amount' => (int) $p->amount,
            'income' => $type->billEffect() * (int) $p->amount,
            'received_by' => $p->received_by,
        ];
    }

    private function receivableRow(Rental $r, RentalBilling $billing): array
    {
        $b = $billing->summary($r);
        $tz = config('app.display_timezone');

        return [
            'rental_id' => $r->id,
            'code' => $r->code,
            'status' => $r->isOverdue() ? 'terlambat' : $r->status->value,
            'status_label' => $r->isOverdue() ? 'Terlambat' : $r->status->label(),
            'customer_name' => $r->customer->name,
            'customer_phone' => $r->customer->phone,
            'car' => $r->car->display_name,
            'plate_number' => $r->car->plate_number,
            'start_at' => $r->start_at->copy()->setTimezone($tz)->toIso8601String(),
            'start_local' => $r->start_at->copy()->setTimezone($tz)->format('Y-m-d H:i'),
            'planned_end_local' => $r->planned_end_at->copy()->setTimezone($tz)->format('Y-m-d H:i'),
            'total' => $b['total_amount'],
            'paid' => $b['paid_amount'],
            'balance' => $b['balance'],
            'running_late_fee' => $b['running_late_fee'],
            'due' => $b['estimated_due'],
            'age_days' => (int) $r->start_at->diffInDays(now()),
        ];
    }
}
