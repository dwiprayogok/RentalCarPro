<?php

namespace App\Models;

use App\Enums\BookingRequestStatus;
use App\Enums\ServiceType;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRequest extends Model
{
    use SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'car_id', 'start_at', 'end_at', 'service_type', 'notes', 'status',
        'rejection_reason', 'handled_by', 'handled_at', 'ip_address', 'user_agent',
    ];

    protected $hidden = ['ip_address', 'user_agent'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'handled_at' => 'datetime',
            'service_type' => ServiceType::class,
            'status' => BookingRequestStatus::class,
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class)->withTrashed();
    }

    /** Sewa hasil konversi (rentals.booking_request_id). */
    public function rental(): HasOne
    {
        return $this->hasOne(Rental::class);
    }
}
