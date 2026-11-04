<?php

namespace App\Models;

use App\Enums\FeeType;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RentalFee extends Model
{
    use LogsActivity, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = ['rental_id', 'type', 'amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['type' => FeeType::class, 'amount' => 'integer'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['rental_id', 'type', 'amount', 'reason'])
            ->logOnlyDirty()
            ->useLogName('rental_fee');
    }
}
