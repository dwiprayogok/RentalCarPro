<?php

namespace App\Services\Import;

/** Satu kolom template import. */
final class Column
{
    /**
     * @param  'text'|'int'|'money'|'date'|'enum'|'bool'|'phone'|'digits'  $type
     * @param  array<string, string>  $options  label tampilan → nilai API (untuk enum)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly string $hint = '',
        public readonly string $example = '',
        public readonly mixed $default = null,
    ) {}

    public function header(): string
    {
        return $this->label.($this->required ? ' *' : '');
    }
}
