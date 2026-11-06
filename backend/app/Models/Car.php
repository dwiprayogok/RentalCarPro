<?php

namespace App\Models;

use App\Enums\CarStatus;
use App\Enums\Transmission;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Car extends Model
{
    use HasFactory, LogsActivity, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = [
        'owner_id', 'brand', 'model', 'plate_number', 'transmission', 'year', 'color', 'seats', 'fuel_type',
        'rate_self_drive', 'rate_with_driver', 'weekend_rate_self_drive', 'weekend_rate_with_driver',
        'default_deposit', 'odometer', 'status',
        'tax_due_date', 'stnk_due_date', 'next_service_date', 'next_service_odometer', 'service_interval_km',
        'slug', 'is_published', 'description', 'features', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transmission' => Transmission::class,
            'status' => CarStatus::class,
            'year' => 'integer',
            'seats' => 'integer',
            'rate_self_drive' => 'integer',
            'rate_with_driver' => 'integer',
            'weekend_rate_self_drive' => 'integer',
            'weekend_rate_with_driver' => 'integer',
            'default_deposit' => 'integer',
            'odometer' => 'integer',
            'tax_due_date' => 'date',
            'stnk_due_date' => 'date',
            'next_service_date' => 'date',
            'is_published' => 'boolean',
            'features' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Car $car) {
            $car->plate_number = Str::upper(preg_replace('/\s+/', ' ', trim($car->plate_number)));
            if (blank($car->slug)) {
                $car->slug = static::uniqueSlug("{$car->brand} {$car->model} {$car->year}");
            }
        });
    }

    public static function uniqueSlug(string $source): string
    {
        $base = Str::slug($source);
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->brand} {$this->model}"));
    }

    /** Pemilik titipan; null = milik rental sendiri. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(CarOwner::class, 'owner_id')->withTrashed();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CarPhoto::class)->orderBy('sort_order');
    }

    public function coverPhoto(): HasOne
    {
        return $this->hasOne(CarPhoto::class)->where('is_cover', true);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('status', '!=', CarStatus::Nonaktif->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'rate_self_drive', 'rate_with_driver', 'weekend_rate_self_drive', 'weekend_rate_with_driver',
                'status', 'odometer', 'is_published', 'plate_number', 'owner_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('car');
    }
}
