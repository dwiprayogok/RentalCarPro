<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Foto KTP/SIM: disimpan di disk PRIVAT dan hanya bisa dibuka lewat signed URL
 * berumur pendek (default 10 menit). URL tidak bisa ditebak dan kedaluwarsa sendiri.
 */
class CustomerDocumentService
{
    public const DISK = 'private';

    public const TTL_MINUTES = 10;

    private const COLUMNS = ['ktp' => 'ktp_photo_path', 'sim' => 'sim_photo_path'];

    public function store(Customer $customer, string $type, UploadedFile $file): Customer
    {
        $column = self::COLUMNS[$type];
        $old = $customer->{$column};

        $path = $file->storeAs(
            "customers/{$customer->id}",
            $type.'-'.Str::uuid().'.'.$file->extension(),
            self::DISK,
        );

        $customer->forceFill([$column => $path])->save();

        if ($old) {
            Storage::disk(self::DISK)->delete($old);
        }

        activity('customer')->performedOn($customer)->event('document_uploaded')
            ->withProperties(['type' => $type])->log("Unggah foto {$type}");

        return $customer;
    }

    public function delete(Customer $customer, string $type): Customer
    {
        $column = self::COLUMNS[$type];
        if ($path = $customer->{$column}) {
            $customer->forceFill([$column => null])->save();
            Storage::disk(self::DISK)->delete($path);

            activity('customer')->performedOn($customer)->event('document_deleted')
                ->withProperties(['type' => $type])->log("Hapus foto {$type}");
        }

        return $customer;
    }

    public function path(Customer $customer, string $type): ?string
    {
        return $customer->{self::COLUMNS[$type]} ?? null;
    }

    public function signedUrl(Customer $customer, string $type): string
    {
        return URL::temporarySignedRoute(
            'v1.customers.documents.file',
            now()->addMinutes(self::TTL_MINUTES),
            ['customer' => $customer->id, 'type' => $type],
        );
    }
}
