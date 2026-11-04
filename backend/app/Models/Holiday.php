<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Holiday extends Model
{
    protected $fillable = ['date', 'name'];

    /** Selalu disimpan "YYYY-MM-DD" (tanpa jam) agar konsisten di MySQL & SQLite dan cocok dengan aturan unique. */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v ? Carbon::parse($v)->startOfDay() : null,
            set: fn ($v) => $v ? Carbon::parse($v)->toDateString() : null,
        );
    }
}
