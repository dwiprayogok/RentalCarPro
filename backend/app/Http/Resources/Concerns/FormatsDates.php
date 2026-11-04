<?php

namespace App\Http\Resources\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

trait FormatsDates
{
    /** Waktu absolut dalam WIB, ISO-8601 dengan offset. */
    protected function wib(?DateTimeInterface $date): ?string
    {
        return $date ? Carbon::instance($date)->setTimezone(config('app.display_timezone'))->toIso8601String() : null;
    }

    /** Tanggal kalender (tanpa jam), Y-m-d. */
    protected function dateOnly(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }
}
