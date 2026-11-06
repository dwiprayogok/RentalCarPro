<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\BookingRequestStatus;
use App\Enums\CarStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreBookingRequestRequest;
use App\Http\Resources\Public\PublicCarResource;
use App\Models\BookingRequest;
use App\Models\Car;
use App\Models\Rental;
use App\Models\Setting;
use App\Services\PublicCatalog;
use App\Services\Rental\AvailabilityService;
use App\Services\Rental\PricingService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Public API untuk landing page (tanpa login, rate limited, di-cache).
 * Hanya mobil yang di-publish & tidak nonaktif. Tidak pernah mengeluarkan plat, odometer,
 * data pelanggan, kode nota, atau informasi internal lain.
 */
class PublicController extends Controller
{
    public function __construct(
        private readonly PublicCatalog $catalog,
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {}

    /** GET /api/public/cars?transmission=&seats=&service=&sort=&start=&end= */
    public function cars(Request $request): JsonResponse
    {
        $f = $request->validate([
            'transmission' => ['nullable', 'in:matic,manual'],
            'seats' => ['nullable', 'integer', 'min:2', 'max:20'],
            'sort' => ['nullable', 'in:price_asc,price_desc,name'],
            'service' => ['nullable', Rule::enum(ServiceType::class)],
            'start' => ['nullable', 'required_with:end', 'date'],
            'end' => ['nullable', 'required_with:start', 'date', 'after:start'],
        ], [], ['start' => 'waktu ambil', 'end' => 'waktu kembali', 'seats' => 'jumlah kursi', 'transmission' => 'transmisi', 'service' => 'layanan']);

        // Cache hanya berisi array biasa (bukan model): store cache Laravel menolak unserialize objek.
        $data = $this->catalog->remember('cars:'.md5(json_encode(array_intersect_key($f, array_flip(['transmission', 'seats', 'sort'])))), fn () => PublicCarResource::collection(
            $this->publishedQuery()
                ->when($f['transmission'] ?? null, fn ($q, $t) => $q->where('transmission', $t))
                ->when($f['seats'] ?? null, fn ($q, $s) => $q->where('seats', '>=', $s))
                ->tap(fn ($q) => match ($f['sort'] ?? 'price_asc') {
                    'price_desc' => $q->orderByDesc('rate_self_drive'),
                    'name' => $q->orderBy('brand')->orderBy('model'),
                    default => $q->orderBy('rate_self_drive'),
                })
                ->get()
        )->resolve($request));

        // Cek ketersediaan untuk rentang tanggal (tidak di-cache: jadwal berubah cepat).
        if (! empty($f['start'])) {
            $start = Carbon::parse($f['start'])->utc();
            $end = Carbon::parse($f['end'])->utc();
            $service = ServiceType::tryFrom($f['service'] ?? '') ?? ServiceType::LepasKunci;
            $cars = Car::query()->published()->whereIn('slug', array_column($data, 'slug'))->get()->keyBy('slug');
            foreach ($data as $i => $row) {
                if ($car = $cars->get($row['slug'])) {
                    $data[$i]['period'] = $this->periodInfo($car, $service, $start, $end);
                }
            }
        }

        return response()->json(['data' => $data])->header('Cache-Control', 'public, max-age=60');
    }

    /** GET /api/public/cars/{slug} — detail + galeri + kalender ketersediaan publik 60 hari. */
    public function car(Request $request, string $slug): JsonResponse
    {
        $cached = $this->catalog->remember("car:{$slug}", function () use ($slug, $request) {
            $car = $this->publishedQuery()->where('slug', $slug)->first();

            return $car ? ['id' => $car->id, 'data' => (new PublicCarResource($car))->resolve($request)] : false;
        });
        abort_if(! $cached, 404, 'Mobil tidak ditemukan.');

        // id hanya untuk kunci cache/kalender internal, tidak ikut dikirim.
        $calendar = $this->catalog->remember("calendar:{$cached['id']}:".now()->format('YmdH'), fn () => $this->publicCalendar(Car::query()->findOrFail($cached['id']), 60), 300);

        return response()->json(['data' => $cached['data'] + ['calendar' => $calendar]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /** GET /api/public/availability?car=slug&start=&end=&service= — cek satu mobil + estimasi harga. */
    public function availability(Request $request): JsonResponse
    {
        $f = $request->validate([
            'car' => ['required', 'string', 'max:120'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'service' => ['nullable', Rule::enum(ServiceType::class)],
        ], [], ['car' => 'mobil', 'start' => 'waktu ambil', 'end' => 'waktu kembali', 'service' => 'layanan']);

        $car = $this->publishedQuery()->where('slug', $f['car'])->first();
        abort_if(! $car, 404, 'Mobil tidak ditemukan.');

        $service = ServiceType::tryFrom($f['service'] ?? '') ?? ServiceType::LepasKunci;

        return response()->json(['data' => $this->periodInfo($car, $service, Carbon::parse($f['start'])->utc(), Carbon::parse($f['end'])->utc())]);
    }

    /** GET /api/public/site-settings — identitas usaha & konten landing (hanya setting is_public). */
    public function siteSettings(SettingService $settings): JsonResponse
    {
        $data = $this->catalog->remember('site-settings', function () use ($settings) {
            $public = Setting::query()->where('is_public', true)->pluck('key');
            $all = $settings->all();
            $v = fn (string $k, $d = null) => $public->contains($k) ? ($all[$k] ?? $d) : $d;
            $wa = (string) $v('business_whatsapp', '');

            return [
                'business' => [
                    'name' => $v('business_name', config('app.name')),
                    'tagline' => $v('business_tagline'),
                    'address' => $v('business_address'),
                    'whatsapp' => $wa ?: null,
                    'whatsapp_url' => $wa ? 'https://wa.me/'.preg_replace('/\D/', '', $wa) : null,
                    'city' => $v('landing_city'),
                    'operating_hours' => $v('landing_operating_hours'),
                    'maps_url' => $v('landing_maps_url') ?: null,
                ],
                'hero' => ['title' => $v('landing_hero_title'), 'subtitle' => $v('landing_hero_subtitle')],
                'how_to' => $v('landing_how_to', []),
                'terms' => $v('landing_terms', []),
                'faq' => $v('landing_faq', []),
                'testimonials' => $v('landing_testimonials', []),
                // Aturan publik yang membantu calon penyewa (bukan data internal).
                'rental_rules' => [
                    'grace_hours' => round($settings->int('grace_period_minutes', 120) / 60, 1),
                    'late_fee_per_hour' => $settings->int('late_fee_per_hour', 50000),
                ],
            ];
        });

        return response()->json(['data' => $data])->header('Cache-Control', 'public, max-age=300');
    }

    /** POST /api/public/booking-requests — form permintaan sewa. */
    public function storeBooking(StoreBookingRequestRequest $request): JsonResponse
    {
        $settings = app(SettingService::class);
        $wa = preg_replace('/\D/', '', (string) $settings->get('business_whatsapp', ''));
        $message = 'Terima kasih! Permintaan Anda sudah kami terima. Tim kami akan menghubungi lewat WhatsApp untuk konfirmasi.';

        // Honeypot terisi → bot. Balas sukses agar bot tidak belajar, tapi jangan simpan.
        if ($request->isBot()) {
            return response()->json(['message' => $message], 201);
        }

        $data = $request->validated();
        $car = isset($data['car']) ? Car::query()->where('slug', $data['car'])->first() : null;
        $start = Carbon::parse($data['start_at'])->utc();
        $end = Carbon::parse($data['end_at'])->utc();
        $service = ServiceType::from($data['service_type']);

        BookingRequest::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'car_id' => $car?->id,
            'start_at' => $start,
            'end_at' => $end,
            'service_type' => $service,
            'notes' => $data['notes'] ?? null,
            'status' => BookingRequestStatus::Baru,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        $tz = config('app.display_timezone');
        $text = "Halo, saya {$data['name']}. Saya baru mengirim permintaan sewa"
            .($car ? " {$car->display_name}" : '')
            .' untuk '.$start->copy()->setTimezone($tz)->locale('id')->translatedFormat('j M Y H.i').' – '
            .$end->copy()->setTimezone($tz)->locale('id')->translatedFormat('j M Y H.i').' WIB ('.$service->label().').';

        return response()->json([
            'message' => $message,
            'period' => $car ? $this->periodInfo($car, $service, $start, $end) : null,
            'whatsapp_url' => $wa ? "https://wa.me/{$wa}?text=".rawurlencode($text) : null,
        ], 201);
    }

    private function publishedQuery()
    {
        return Car::query()->published()->with('photos');
    }

    /** Ketersediaan & estimasi harga untuk satu rentang, tanpa detail sewa lain. */
    private function periodInfo(Car $car, ServiceType $service, Carbon $start, Carbon $end): array
    {
        $available = $start->gt(now()) && $this->availability->carConflicts($car->id, $start, $end)->isEmpty()
            && ! ($car->status === CarStatus::Servis && $start->lt(now()->addDay()));
        $quote = $this->pricing->quote($car, $service, $start, $end);

        return [
            'available' => $available,
            'service' => $service->value,
            'days' => $quote->billedDays,
            'estimated_total' => $quote->rentalAmount,
            'deposit' => $car->default_deposit,
        ];
    }

    /**
     * Kalender publik: hanya rentang tanggal (WIB) yang tidak tersedia, tanpa kode nota/pelanggan.
     *
     * @return array{from: string, to: string, unavailable: list<array{start: string, end: string}>}
     */
    private function publicCalendar(Car $car, int $days): array
    {
        $tz = config('app.display_timezone');
        $from = now()->startOfHour();
        $to = now()->addDays($days);
        $buffer = $this->availability->bufferMinutes();

        $ranges = $this->availability->carConflicts($car->id, $from, $to)
            ->map(fn (Rental $r) => [
                'start' => $r->start_at->copy()->subMinutes($buffer)->max($from)->setTimezone($tz)->toDateString(),
                'end' => $this->availability->effectiveEnd($r)->addMinutes($buffer)->setTimezone($tz)->toDateString(),
            ])->values()->all();

        return ['from' => $from->copy()->setTimezone($tz)->toDateString(), 'to' => $to->copy()->setTimezone($tz)->toDateString(), 'unavailable' => $ranges];
    }
}
