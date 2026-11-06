<?php

namespace App\Services;

use App\Jobs\RevalidateLanding;
use Illuminate\Support\Facades\Cache;

/**
 * Cache respons Public API dengan kunci berversi. Saat data publik berubah (mobil, foto,
 * pengaturan publik, hari libur, jadwal sewa), versi dinaikkan sehingga semua cache lama
 * otomatis tidak terpakai (tanpa perlu cache tags), lalu landing diminta revalidate (ISR).
 */
class PublicCatalog
{
    private const VERSION_KEY = 'public:version';

    public function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }

    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        return Cache::remember('public:v'.$this->version().':'.$key, $ttl ?? config('rentalpro.public_cache_ttl', 600), $callback);
    }

    /** Buang semua cache publik & jadwalkan revalidate landing (digabung bila beruntun). */
    public function invalidate(string $reason = ''): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
        RevalidateLanding::dispatch($reason)->afterCommit();
    }
}
