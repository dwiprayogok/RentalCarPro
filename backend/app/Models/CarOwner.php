<?php

namespace App\Models;

use App\Enums\ShareBasis;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Pemilik mobil titipan. */
class CarOwner extends Model
{
    use HasFactory, LogsActivity, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'bank_name', 'bank_account_number', 'bank_account_name',
        'share_basis', 'share_percent', 'notes', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'share_basis' => ShareBasis::class,
            'share_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class, 'owner_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(OwnerSettlement::class, 'owner_id');
    }

    public function shareLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->share_percent, 2, ',', '.'), '0'), ',').'% '.$this->share_basis->short();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'bank_name', 'bank_account_number', 'bank_account_name', 'share_basis', 'share_percent', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('car_owner');
    }
}
