<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UploadCustomerDocumentRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerDocumentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerDocumentController extends Controller
{
    public function __construct(private readonly CustomerDocumentService $documents) {}

    public function store(UploadCustomerDocumentRequest $request, Customer $customer): CustomerResource
    {
        $type = $request->validated('type');
        $this->documents->store($customer, $type, $request->file('file'));

        return (new CustomerResource($customer->fresh()->loadCount('rentals')))
            ->additional(['message' => 'Foto '.strtoupper($type).' berhasil diunggah.']);
    }

    public function destroy(Customer $customer, string $type): CustomerResource
    {
        Gate::authorize('manageDocuments', $customer);
        $this->documents->delete($customer, $type);

        return (new CustomerResource($customer->fresh()->loadCount('rentals')))
            ->additional(['message' => 'Foto '.strtoupper($type).' dihapus.']);
    }

    /**
     * Menyajikan berkas dari disk privat. Route ini dilindungi middleware "signed":
     * hanya bisa diakses lewat URL yang ditandatangani CustomerResource (berlaku 10 menit).
     */
    public function file(Customer $customer, string $type): StreamedResponse
    {
        $path = $this->documents->path($customer, $type);
        abort_unless($path && Storage::disk(CustomerDocumentService::DISK)->exists($path), 404);

        return Storage::disk(CustomerDocumentService::DISK)->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
