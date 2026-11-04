<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Tanggal disimpan UTC di database, tetapi diserialisasi ke JSON dalam zona tampilan
 * (default Asia/Jakarta / WIB) dengan offset eksplisit, mis. 2026-10-21T15:41:00+07:00.
 * Frontend dan Flutter tetap menerima waktu absolut yang tidak ambigu.
 */
trait SerializesDatesInDisplayTimezone
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->setTimezone(config('app.display_timezone', 'Asia/Jakarta'))
            ->toIso8601String();
    }
}
