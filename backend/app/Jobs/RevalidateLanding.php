<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Webhook ke landing (Next.js) untuk revalidate ISR tag "public".
 * ShouldBeUnique: banyak perubahan dalam 10 detik digabung menjadi satu panggilan.
 */
class RevalidateLanding implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public int $uniqueFor = 10;

    public function __construct(public string $reason = '') {}

    public function handle(): void
    {
        $url = config('services.landing.revalidate_url');
        $secret = config('services.landing.revalidate_secret');
        if (! $url || ! $secret) {
            return; // landing belum dikonfigurasi
        }

        $response = Http::timeout(5)
            ->withHeaders(['X-Revalidate-Secret' => $secret])
            ->acceptJson()
            ->post($url, ['tags' => ['public'], 'reason' => $this->reason]);

        if ($response->failed()) {
            Log::warning('Revalidate landing gagal', ['status' => $response->status()]);
            $response->throw();
        }
    }
}
