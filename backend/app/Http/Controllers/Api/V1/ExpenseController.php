<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\SaveExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    /**
     * Filter: car_id (angka, atau "umum" untuk pengeluaran tanpa mobil), category, from, to, search.
     * meta.summary = total & per kategori sesuai filter (bukan hanya halaman ini).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Expense::class);

        $f = $request->validate([
            'car_id' => ['nullable', 'regex:/^(\d+|umum)$/'],
            'category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], ['car_id' => 'mobil', 'category' => 'kategori', 'from' => 'tanggal awal', 'to' => 'tanggal akhir']);

        $query = Expense::query()
            ->when($f['car_id'] ?? null, fn (Builder $q, $c) => $c === 'umum' ? $q->whereNull('car_id') : $q->where('car_id', (int) $c))
            ->when($f['category'] ?? null, fn (Builder $q, $c) => $q->where('category', $c))
            ->when($f['from'] ?? null, fn (Builder $q, $d) => $q->where('spent_at', '>=', $d))
            ->when($f['to'] ?? null, fn (Builder $q, $d) => $q->where('spent_at', '<=', $d))
            ->when($f['search'] ?? null, fn (Builder $q, $s) => $q->where(
                fn ($q) => $q->where('title', 'like', Search::like($s))->orWhere('vendor', 'like', Search::like($s)),
            ));

        $byCategory = (clone $query)->reorder()->selectRaw('category, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('category')->get()->keyBy(fn ($r) => $r->category instanceof ExpenseCategory ? $r->category->value : $r->category);

        $page = $query->with(['car', 'creator'])
            ->orderByDesc('spent_at')->orderByDesc('id')
            ->paginate($f['per_page'] ?? 20)
            ->withQueryString();

        return ExpenseResource::collection($page)->additional(['meta' => [
            'summary' => [
                'total' => (int) $byCategory->sum('total'),
                'count' => (int) $byCategory->sum('n'),
                'by_category' => collect(ExpenseCategory::cases())
                    ->map(fn ($c) => ['category' => $c->value, 'label' => $c->label(), 'total' => (int) ($byCategory[$c->value]->total ?? 0)])
                    ->filter(fn ($r) => $r['total'] > 0)->sortByDesc('total')->values(),
            ],
            'categories' => collect(ExpenseCategory::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label(), 'per_car' => $c->perCar()]),
        ]]);
    }

    public function store(SaveExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->create($request->validated(), $request->user(), $request->file('proof'));

        return (new ExpenseResource($expense->load(['car', 'creator'])))
            ->additional(['message' => 'Pengeluaran berhasil dicatat.'])
            ->response()->setStatusCode(201);
    }

    public function show(Expense $expense): ExpenseResource
    {
        Gate::authorize('view', $expense);

        return new ExpenseResource($expense->load(['car', 'creator']));
    }

    public function update(SaveExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $this->expenses->update($expense, $request->validated(), $request->file('proof'));

        return (new ExpenseResource($expense->fresh()->load(['car', 'creator'])))
            ->additional(['message' => 'Pengeluaran berhasil diperbarui.']);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        Gate::authorize('delete', $expense);
        $this->expenses->delete($expense);

        return response()->json(['message' => 'Pengeluaran berhasil dihapus.']);
    }

    /** Bukti pengeluaran (hanya lewat signed URL). */
    public function proof(Expense $expense): StreamedResponse
    {
        abort_unless($expense->proof_path, 404);

        return Storage::disk(ExpenseService::DISK)->response($expense->proof_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
