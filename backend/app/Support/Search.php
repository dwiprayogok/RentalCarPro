<?php

namespace App\Support;

final class Search
{
    /** Pola LIKE "%kata%" dengan wildcard pengguna di-escape. */
    public static function like(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }
}
