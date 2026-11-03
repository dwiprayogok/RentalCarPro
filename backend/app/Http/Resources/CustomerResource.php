<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Customer;
use App\Services\CustomerDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * NIK TIDAK PERNAH dikirim utuh. Hanya bentuk ter-mask.
 * URL dokumen (KTP/SIM) hanya diberikan sebagai signed URL berumur pendek
 * kepada pengguna dengan permission customer.view_documents.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewDocs = (bool) $user?->can('viewDocuments', $this->resource);
        $docs = app(CustomerDocumentService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials(),
            'phone' => $this->phone,
            'nik_masked' => $this->nik_masked,
            'sim_number' => $this->sim_number,
            'sim_type' => $this->sim_type,
            'sim_expires_at' => $this->dateOnly($this->sim_expires_at),
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'self_drive_ready' => $this->selfDriveMissing() === [],
            'self_drive_missing' => $this->selfDriveMissing(),
            'risk_status' => $this->risk_status->value,
            'risk_status_label' => $this->risk_status->label(),
            'risk_note' => $this->risk_note,
            'risk_updated_at' => $this->wib($this->risk_updated_at),
            'notes' => $this->notes,
            'documents' => [
                'ktp' => [
                    'uploaded' => $this->ktp_photo_path !== null,
                    'url' => $canViewDocs && $this->ktp_photo_path ? $docs->signedUrl($this->resource, 'ktp') : null,
                ],
                'sim' => [
                    'uploaded' => $this->sim_photo_path !== null,
                    'url' => $canViewDocs && $this->sim_photo_path ? $docs->signedUrl($this->resource, 'sim') : null,
                ],
            ],
            'rentals_count' => $this->whenCounted('rentals'),
            'created_at' => $this->wib($this->created_at),
            'updated_at' => $this->wib($this->updated_at),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
                'change_risk' => (bool) $user?->can('changeRisk', $this->resource),
                'manage_documents' => (bool) $user?->can('manageDocuments', $this->resource),
            ],
        ];
    }
}
