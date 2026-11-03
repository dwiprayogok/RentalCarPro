<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = ['name', 'phone', 'license_number', 'license_expires_at', 'daily_fee', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'license_expires_at' => 'date',
            'daily_fee' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
