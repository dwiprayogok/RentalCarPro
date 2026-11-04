<?php

return [
    // Batas permintaan Public API (landing) per IP per menit.
    'public_rate_limit' => (int) env('PUBLIC_API_RATE_LIMIT', 120),
    // Form permintaan sewa: per IP per 10 menit & per nomor HP per jam.
    'booking_rate_ip' => (int) env('PUBLIC_BOOKING_RATE_IP', 5),
    'booking_rate_phone' => (int) env('PUBLIC_BOOKING_RATE_PHONE', 3),
    // Lama cache respons publik (detik). Cache juga dibuang otomatis saat data berubah.
    'public_cache_ttl' => (int) env('PUBLIC_CACHE_TTL', 600),
];
