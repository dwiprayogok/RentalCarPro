<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use LogsActivity, SerializesDatesInDisplayTimezone;

    protected $fillable = ['group', 'key', 'value', 'type', 'label', 'description', 'is_public', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_public' => 'boolean',
        ];
    }

    /** Nilai dengan tipe yang benar (int/bool/string/json). */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'bool' => (bool) $this->value,
            'string' => (string) $this->value,
            default => $this->value,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('setting');
    }
}
