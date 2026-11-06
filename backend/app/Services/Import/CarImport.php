<?php

namespace App\Services\Import;

use App\Http\Requests\Car\SaveCarRequest;
use App\Models\Car;
use App\Models\User;
use App\Services\CarService;
use App\Support\Permissions;
use Illuminate\Support\Str;

final class CarImport implements ImportDefinition
{
    public function key(): string
    {
        return 'armada';
    }

    public function label(): string
    {
        return 'armada';
    }

    public function columns(): array
    {
        return [
            new Column('brand', 'Merk', required: true, example: 'Toyota'),
            new Column('model', 'Tipe', required: true, example: 'Avanza'),
            new Column('plate_number', 'Plat nomor', required: true, hint: 'Format: F 1234 ABC', example: 'F 1234 ABC'),
            new Column('transmission', 'Transmisi', 'enum', true, ['Matic' => 'matic', 'Manual' => 'manual'], example: 'Matic'),
            new Column('year', 'Tahun', 'int', true, example: '2022'),
            new Column('color', 'Warna', example: 'Putih'),
            new Column('seats', 'Kursi', 'int', hint: 'Jumlah kursi', example: '7', default: 5),
            new Column('fuel_type', 'Bahan bakar', example: 'Bensin'),
            new Column('rate_self_drive', 'Tarif lepas kunci', 'money', true, hint: 'Rupiah per hari', example: '450000'),
            new Column('rate_with_driver', 'Tarif dengan driver', 'money', true, hint: 'Total per hari termasuk driver', example: '650000'),
            new Column('weekend_rate_self_drive', 'Tarif akhir pekan lepas kunci', 'money', example: ''),
            new Column('weekend_rate_with_driver', 'Tarif akhir pekan dengan driver', 'money', example: ''),
            new Column('default_deposit', 'Deposit', 'money', example: '500000', default: 0),
            new Column('odometer', 'Odometer (km)', 'int', hint: 'Kosong = 0', example: '35000', default: 0),
            new Column('status', 'Status', 'enum', false, ['Tersedia' => 'tersedia', 'Servis' => 'servis', 'Nonaktif' => 'nonaktif'], 'Kosong = Tersedia', 'Tersedia', 'tersedia'),
            new Column('tax_due_date', 'Jatuh tempo pajak', 'date', hint: 'Tanggal (dd/mm/yyyy)', example: '31/08/2027'),
            new Column('stnk_due_date', 'Jatuh tempo STNK', 'date', hint: 'Tanggal (dd/mm/yyyy)', example: ''),
            new Column('notes', 'Catatan', example: ''),
        ];
    }

    public function requestClass(): string
    {
        return SaveCarRequest::class;
    }

    public function createPermission(): string
    {
        return Permissions::CAR_CREATE;
    }

    private static function plate(?string $v): string
    {
        return Str::upper(preg_replace('/\s+/', ' ', trim((string) $v)));
    }

    public function fileKey(array $input): ?string
    {
        return filled($input['plate_number'] ?? null) ? 'plat '.self::plate($input['plate_number']) : null;
    }

    public function existing(array $input): ?string
    {
        $plate = self::plate($input['plate_number'] ?? '');
        if ($plate === '') {
            return null;
        }
        $car = Car::withTrashed()->where('plate_number', $plate)->first();

        return $car ? "Plat {$plate} sudah terdaftar".($car->trashed() ? ' (armada terhapus)' : " ({$car->display_name})").'.' : null;
    }

    public function summary(array $input): string
    {
        return trim(($input['brand'] ?? '').' '.($input['model'] ?? '')).' · '.self::plate($input['plate_number'] ?? '');
    }

    public function create(array $validated, User $by): void
    {
        app(CarService::class)->create($validated, $by);
    }
}
