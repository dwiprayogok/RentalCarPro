<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingRequestRequest;
use App\Http\Resources\BookingRequestResource;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Support\Search;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', BookingRequest::class);
        $f = $request->validate([
            'status' => ['nullable', Rule::enum(BookingRequestStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $base = BookingRequest::query()->when($f['search'] ?? null, fn ($q, $s) => $q->where(
            fn ($q) => $q->where('name', 'like', Search::like($s))->orWhere('phone', 'like', Search::like(preg_replace('/\D/', '', $s) ?: $s)),
        ));
        $counts = (clone $base)->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $page = (clone $base)
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->with(['car', 'rental', 'handledBy'])
            ->orderByRaw("CASE status WHEN 'baru' THEN 0 WHEN 'dihubungi' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate($f['per_page'] ?? 15)->withQueryString();

        $this->attachCustomers($page->getCollection());

        return BookingRequestResource::collection($page)->additional(['meta' => ['counts' => ['semua' => (int) $counts->sum()]
            + collect(BookingRequestStatus::values())->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all()]]);
    }

    public function show(BookingRequest $bookingRequest): BookingRequestResource
    {
        Gate::authorize('view', $bookingRequest);
        $bookingRequest->load(['car', 'rental', 'handledBy']);
        $this->attachCustomers(collect([$bookingRequest]));

        return new BookingRequestResource($bookingRequest);
    }

    public function update(UpdateBookingRequestRequest $request, BookingRequest $bookingRequest): BookingRequestResource
    {
        if (in_array($bookingRequest->status, [BookingRequestStatus::Dikonfirmasi], true)) {
            throw ValidationException::withMessages(['status' => 'Permintaan yang sudah menjadi sewa tidak bisa diubah.']);
        }

        $status = BookingRequestStatus::from($request->validated('status'));
        $bookingRequest->forceFill([
            'status' => $status,
            'rejection_reason' => $status === BookingRequestStatus::Ditolak ? $request->validated('rejection_reason') : null,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        activity('booking_request')->performedOn($bookingRequest)->causedBy($request->user())->event('status_changed')
            ->withProperties(['status' => $status->value])->log('Status permintaan booking diubah');

        $bookingRequest->load(['car', 'rental', 'handledBy']);
        $this->attachCustomers(collect([$bookingRequest]));

        return (new BookingRequestResource($bookingRequest))->additional(['message' => "Status diubah menjadi {$status->label()}."]);
    }

    /** Cocokkan pelanggan berdasarkan nomor HP (satu query untuk semua baris). */
    private function attachCustomers(Collection $requests): void
    {
        $customers = Customer::query()->whereIn('phone', $requests->pluck('phone')->unique())->get()->keyBy('phone');
        $requests->each(fn (BookingRequest $r) => $r->setAttribute('matched_customer', $customers[$r->phone] ?? null));
    }
}
