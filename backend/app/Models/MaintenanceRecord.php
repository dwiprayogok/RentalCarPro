<?php

namespace App\Models;

use App\Enums\MaintenanceType;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRecord extends Model
{
    use HasFactory, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = [
        'car_id', 'rental_id', 'type', 'title', 'performed_at', 'odometer', 'cost', 'vendor',
        'next_due_date', 'next_due_odometer', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'performed_at' => 'date',
            'odometer' => 'integer',
            'cost' => 'integer',
            'next_due_date' => 'date',
            'next_due_odometer' => 'integer',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class)->withTrashed();
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
