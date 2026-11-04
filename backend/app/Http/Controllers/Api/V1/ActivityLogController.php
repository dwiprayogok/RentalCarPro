<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RentalFee;
use App\Models\RentalPayment;
use App\Models\User;
use App\Support\ActivityPresenter;
use App\Support\Permissions;
use App\Support\Search;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

/** Log aktivitas: siapa mengubah apa dan kapan. Butuh activity.view (owner & admin). */
class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::ACTIVITY_VIEW), 403);
        $tz = config('app.display_timezone');

        $f = $request->validate([
            'module' => ['nullable', Rule::in(array_keys(ActivityPresenter::MODULES))],
            'event' => ['nullable', 'string', 'max:40'],
            'causer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], ['module' => 'modul', 'causer_id' => 'pengguna', 'from' => 'tanggal awal', 'to' => 'tanggal akhir']);

        $page = Activity::query()
            // Muat relasi yang dipakai label subjek sekaligus (hindari N+1 / lazy loading).
            ->with(['causer', 'subject' => fn (MorphTo $m) => $m->morphWith([
                RentalPayment::class => ['rental:id,code'],
                RentalFee::class => ['rental:id,code'],
            ])])
            ->when($f['module'] ?? null, fn ($q, $m) => $q->where('log_name', $m))
            ->when($f['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($f['causer_id'] ?? null, fn ($q, $id) => $q->where('causer_type', User::class)->where('causer_id', $id))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d, $tz)->startOfDay()->utc()))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d, $tz)->endOfDay()->utc()))
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('description', 'like', Search::like($s))
                ->orWhere('properties', 'like', Search::like($s))))
            ->latest('id')
            ->paginate($f['per_page'] ?? 25)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Activity $a) => ActivityPresenter::present($a))->values(),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
                'modules' => collect(ActivityPresenter::MODULES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'events' => collect(ActivityPresenter::EVENTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'causers' => User::query()->whereIn('id', Activity::query()->where('causer_type', User::class)->distinct()->pluck('causer_id'))
                    ->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }
}
