<?php

namespace App\Services\Import;

use App\Http\Requests\Driver\SaveDriverRequest;
use App\Models\Driver;
use App\Models\User;
use App\Services\DriverService;
use App\Support\Permissions;
use App\Support\Phone;

final class DriverImport implements ImportDefinition
{
    public function key(): string
    {
        return 'driver';
    }

    public function label(): string
    {
        return 'driver';
    }

    public function columns(): array
    {
        return [
            new Column('name', 'Nama driver', required: true, example: 'Budi Santoso'),
            new Column('phone', 'No. HP', 'phone', true, hint: 'Format sel Teks', example: '081311112222'),
            new Column('license_number', 'No. SIM', example: '987654321012'),
            new Column('license_expires_at', 'Masa berlaku SIM', 'date', hint: 'Tanggal (dd/mm/yyyy)', example: '30/06/2028'),
            new Column('daily_fee', 'Honor harian', 'money', hint: 'Rupiah', example: '200000'),
            new Column('is_active', 'Aktif', 'bool', false, ['Ya' => '1', 'Tidak' => '0'], 'Kosong = Ya', 'Ya', '1'),
            new Column('notes', 'Catatan', example: ''),
        ];
    }

    public function requestClass(): string
    {
        return SaveDriverRequest::class;
    }

    public function createPermission(): string
    {
        return Permissions::DRIVER_CREATE;
    }

    public function fileKey(array $input): ?string
    {
        return filled($input['phone'] ?? null) ? 'HP '.Phone::normalize($input['phone']) : null;
    }

    public function existing(array $input): ?string
    {
        $phone = Phone::normalize($input['phone'] ?? null);
        $d = $phone ? Driver::withTrashed()->where('phone', $phone)->first() : null;

        return $d ? "Nomor HP sudah dipakai driver {$d->name}.".($d->trashed() ? ' (terhapus)' : '') : null;
    }

    public function summary(array $input): string
    {
        return trim((string) ($input['name'] ?? '')).(filled($input['phone'] ?? null) ? ' · '.Phone::normalize($input['phone']) : '');
    }

    public function create(array $validated, User $by): void
    {
        app(DriverService::class)->create($validated);
    }
}
