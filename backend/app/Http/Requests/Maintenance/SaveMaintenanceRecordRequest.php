<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenanceType;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('maintenance');

        return $record instanceof MaintenanceRecord
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', MaintenanceRecord::class);
    }

    public function rules(): array
    {
        return [
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(MaintenanceType::class)],
            'title' => ['required', 'string', 'max:150'],
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'cost' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'next_due_date' => ['nullable', 'date', 'after:performed_at'],
            'next_due_odometer' => ['nullable', 'integer', 'min:0', 'max:5000000', Rule::when($this->filled('odometer'), 'gt:odometer')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'car_id' => 'mobil',
            'type' => 'jenis perawatan',
            'title' => 'judul',
            'performed_at' => 'tanggal pengerjaan',
            'odometer' => 'odometer',
            'cost' => 'biaya',
            'vendor' => 'bengkel/vendor',
            'next_due_date' => 'jatuh tempo berikutnya',
            'next_due_odometer' => 'odometer servis berikutnya',
            'notes' => 'catatan',
        ];
    }
}
