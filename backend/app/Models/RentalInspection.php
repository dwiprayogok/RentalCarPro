<?php

namespace App\Models;

use App\Enums\InspectionType;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalInspection extends Model
{
    use SerializesDatesInDisplayTimezone;

    protected $fillable = [
        'rental_id', 'type', 'odometer', 'fuel_level', 'checklist', 'photos', 'notes', 'inspected_at', 'inspected_by',
    ];

    protected $hidden = ['photos'];

    protected function casts(): array
    {
        return [
            'type' => InspectionType::class,
            'odometer' => 'integer',
            'fuel_level' => 'integer',
            'checklist' => 'array',
            'photos' => 'array',
            'inspected_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
