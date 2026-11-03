<?php

namespace App\Http\Requests\Concerns;

trait PaginatesRequest
{
    public function perPage(int $default = 15): int
    {
        return max(1, min(100, $this->integer('per_page', $default)));
    }
}
