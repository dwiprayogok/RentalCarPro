<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Enums\ServiceType;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Rental extends Model
{
    use HasFactory, LogsActivity, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = [
        'code', 'customer_id', 'car_id', 'driver_id', 'booking_request_id',
        'service_type', 'status', 'start_at', 'planned_end_at', 'picked_up_at', 'returned_at',
        'daily_rate', 'billed_days', 'rental_amount', 'late_fee', 'late_extra_days', 'extra_fees_total',
        'discount', 'discount_reason', 'total_amount', 'paid_amount', 'deposit_required', 'deposit_held',
        'blacklist_override_by', 'blacklist_override_reason',
        'notes', 'cancel_reason', 'cancelled_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => RentalStatus::class,
            'start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'daily_rate' => 'integer',
            'billed_days' => 'integer',
            'rental_amount' => 'integer',
            'late_fee' => 'integer',
            'late_extra_days' => 'integer',
            'extra_fees_total' => 'integer',
            'discount' => 'integer',
            'total_amount' => 'integer',
            'paid_amount' => 'integer',
            'deposit_required' => 'integer',
            'deposit_held' => 'integer',
        ];
    }

    /** Terlambat = aktif dan sudah melewati jam kembali rencana (dihitung, bukan disimpan). */
    public function isOverdue(?Carbon $now = null): bool
    {
        return $this->status === RentalStatus::Aktif
            && ($now ?? now())->greaterThan($this->planned_end_at);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', RentalStatus::Aktif->value)->where('planned_end_at', '<', now());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class)->orderBy('paid_at');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(RentalFee::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(RentalInspection::class);
    }

    public function handoverInspection(): HasOne
    {
        return $this->hasOne(RentalInspection::class)->where('type', 'serah_terima');
    }

    public function returnInspection(): HasOne
    {
        return $this->hasOne(RentalInspection::class)->where('type', 'kembali');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'start_at', 'planned_end_at', 'returned_at', 'car_id', 'driver_id',
                'daily_rate', 'rental_amount', 'late_fee', 'extra_fees_total', 'discount', 'discount_reason',
                'total_amount', 'blacklist_override_by', 'blacklist_override_reason', 'cancel_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('rental');
    }
}
