<?php

namespace App\Http\Requests\Concerns;

use App\Support\Phone;

trait NormalizesInput
{
    /** @param  list<string>  $fields */
    protected function normalizePhones(array $fields): void
    {
        $merge = [];
        foreach ($fields as $field) {
            if ($this->has($field)) {
                $merge[$field] = Phone::normalize($this->input($field));
            }
        }
        $this->merge($merge);
    }

    /** Ubah "true"/"false"/"1"/"0" dari multipart form menjadi boolean asli. */
    protected function normalizeBooleans(array $fields): void
    {
        $merge = [];
        foreach ($fields as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $merge[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            }
        }
        $this->merge($merge);
    }
}
