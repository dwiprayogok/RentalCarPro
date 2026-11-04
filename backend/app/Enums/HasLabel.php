<?php

namespace App\Enums;

/**
 * Helper bersama untuk enum: label Bahasa Indonesia dan daftar nilai.
 */
trait HasLabel
{
    abstract public function label(): string;

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
