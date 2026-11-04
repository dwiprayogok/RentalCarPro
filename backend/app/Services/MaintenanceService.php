<?php

namespace App\Services;

use App\Enums\MaintenanceType;
use App\Models\Car;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat perawatan dan menyinkronkan data jatuh tempo di mobil:
 * - odometer mobil naik bila odometer servis lebih besar;
 * - servis rutin/perbaikan/ban → next_service_date / next_service_odometer;
 * - pajak → tax_due_date; STNK → stnk_due_date.
 * Hanya catatan TERBARU (per tanggal) dari jenis tersebut yang menentukan jatuh tempo.
 */
class MaintenanceService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $by): MaintenanceRecord
    {
        return DB::transaction(function () use ($data, $by) {
            $record = MaintenanceRecord::create($data + ['created_by' => $by->id]);
            $this->syncCar($record->car_id);

            return $record;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(MaintenanceRecord $record, array $data): MaintenanceRecord
    {
        return DB::transaction(function () use ($record, $data) {
            $oldCarId = $record->car_id;
            $record->update($data);

            $this->syncCar($record->car_id);
            if ($oldCarId !== $record->car_id) {
                $this->syncCar($oldCarId);
            }

            return $record;
        });
    }

    public function delete(MaintenanceRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $record->delete();
            $this->syncCar($record->car_id);
        });
    }

    private function syncCar(int $carId): void
    {
        /** @var Car $car */
        $car = Car::query()->lockForUpdate()->findOrFail($carId);
        $records = MaintenanceRecord::query()->where('car_id', $carId)
            ->orderByDesc('performed_at')->orderByDesc('id')->get();

        $maxOdo = (int) $records->max('odometer');
        if ($maxOdo > $car->odometer) {
            $car->odometer = $maxOdo;
        }

        $serviceTypes = [MaintenanceType::ServisRutin, MaintenanceType::Perbaikan, MaintenanceType::Ban];
        $latestService = $records->first(fn ($r) => in_array($r->type, $serviceTypes, true)
            && ($r->next_due_date || $r->next_due_odometer));
        if ($latestService) {
            $car->next_service_date = $latestService->next_due_date;
            $car->next_service_odometer = $latestService->next_due_odometer;
        }

        if ($tax = $records->first(fn ($r) => $r->type === MaintenanceType::Pajak && $r->next_due_date)) {
            $car->tax_due_date = $tax->next_due_date;
        }
        if ($stnk = $records->first(fn ($r) => $r->type === MaintenanceType::Stnk && $r->next_due_date)) {
            $car->stnk_due_date = $stnk->next_due_date;
        }

        $car->save();
    }
}
