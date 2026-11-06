<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ShareBasis;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Setoran bagi hasil ke pemilik mobil titipan untuk satu periode (snapshot). */
class OwnerSettlement extends Model
{
    use LogsActivity, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = [
        'code', 'owner_id', 'period_from', 'period_to', 'share_basis', 'share_percent',
        'income', 'maintenance', 'expenses', 'base_amount', 'owner_amount', 'business_amount', 'lines', 'notes',
        'paid_at', 'paid_method', 'paid_reference', 'paid_proof_path', 'paid_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'share_basis' => ShareBasis::class,
            'share_percent' => 'decimal:2',
            'income' => 'integer',
            'maintenance' => 'integer',
            'expenses' => 'integer',
            'base_amount' => 'integer',
            'owner_amount' => 'integer',
            'business_amount' => 'integer',
            'lines' => 'array',
            'paid_at' => 'datetime',
            'paid_method' => PaymentMethod::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(CarOwner::class, 'owner_id')->withTrashed();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'owner_id', 'period_from', 'period_to', 'owner_amount', 'paid_at', 'paid_method', 'paid_reference'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('owner_settlement');
    }
}
