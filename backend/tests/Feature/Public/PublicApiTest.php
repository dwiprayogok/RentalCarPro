<?php

namespace Tests\Feature\Public;

use App\Enums\CarStatus;
use App\Enums\RentalStatus;
use App\Http\Resources\Public\PublicCarResource;
use App\Jobs\RevalidateLanding;
use App\Models\BookingRequest;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Rental;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    private Car $published;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', 'UTC'));

        $this->published = Car::factory()->create([
            'brand' => 'Toyota', 'model' => 'Innova', 'plate_number' => 'F 1234 RTY', 'odometer' => 55123, 'year' => 2019,
            'rate_self_drive' => 700000, 'rate_with_driver' => 900000, 'is_published' => true, 'slug' => 'toyota-innova-2019',
            'notes' => 'RAHASIA: kunci cadangan di laci', 'features' => ['AC', '7 kursi'], 'tax_due_date' => '2026-11-01',
        ]);
        $this->published->photos()->create(['path' => 'cars/innova.jpg', 'is_cover' => true]);
        Car::factory()->create(['is_published' => false, 'slug' => 'tidak-tampil', 'plate_number' => 'B 9 ZZ']);
        Car::factory()->create(['is_published' => true, 'status' => CarStatus::Nonaktif, 'slug' => 'nonaktif', 'plate_number' => 'B 8 ZZ']);
    }

    private function assertNoSensitiveData(string $json): void
    {
        foreach (['F 1234 RTY', 'plate_number', 'odometer', '55123', 'RAHASIA', 'notes', 'tax_due_date', 'created_by', 'nik', 'TRX-', 'Dwi Rahasia', '+6281399990000'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, "Public API membocorkan: {$needle}");
        }
    }

    public function test_cars_list_only_published_with_whitelisted_fields(): void
    {
        $res = $this->getJson('/api/public/cars')->assertOk()->assertJsonCount(1, 'data');

        $car = $res->json('data.0');
        $this->assertSame(PublicCarResource::FIELDS, array_keys($car), 'Hanya field daftar putih');
        $this->assertSame('toyota-innova-2019', $car['slug']);
        $this->assertContains($car['transmission'], ['Matic', 'Manual']);
        $this->assertStringEndsWith('/storage/cars/innova.jpg', $car['cover_photo_url']);
        $this->assertNoSensitiveData($res->getContent());
    }

    /**
     * Regresi: store yang men-serialize (database/redis/file) menolak unserialize objek
     * (cache.serializable_classes = false). Respons publik harus di-cache sebagai array biasa.
     */
    public function test_public_cache_works_with_serializing_store(): void
    {
        config(['cache.default' => 'database']);
        $start = urlencode(now()->addDays(2)->toIso8601String());
        $end = urlencode(now()->addDays(3)->toIso8601String());

        foreach (['/api/public/cars', "/api/public/cars?start={$start}&end={$end}", '/api/public/cars/toyota-innova-2019', '/api/public/site-settings'] as $url) {
            $first = $this->getJson($url)->assertOk()->getContent();
            $this->assertSame($first, $this->getJson($url)->assertOk()->getContent(), "Respons dari cache harus sama: {$url}");
        }
        $this->getJson("/api/public/cars?start={$start}&end={$end}")->assertJsonPath('data.0.period.available', true);
        $this->assertGreaterThan(0, DB::table('cache')->where('key', 'like', '%public:v%')->count());
    }

    public function test_detail_calendar_and_availability_hide_rental_and_customer_data(): void
    {
        $customer = Customer::factory()->create(['name' => 'Dwi Rahasia', 'phone' => '+6281399990000']);
        Rental::factory()->for($this->published)->for($customer)->status(RentalStatus::Booking)->create([
            'code' => 'TRX-2026-0042', 'start_at' => '2026-10-25 03:00:00', 'planned_end_at' => '2026-10-27 03:00:00',
        ]);

        $res = $this->getJson('/api/public/cars/toyota-innova-2019')->assertOk()
            ->assertJsonPath('data.calendar.unavailable.0.start', '2026-10-25')
            ->assertJsonPath('data.calendar.unavailable.0.end', '2026-10-27');
        $this->assertSame([...PublicCarResource::FIELDS, 'calendar'], array_keys($res->json('data')));
        $this->assertSame(['start', 'end'], array_keys($res->json('data.calendar.unavailable.0')));
        $this->assertNoSensitiveData($res->getContent());

        $this->getJson('/api/public/cars/tidak-tampil')->assertNotFound();
        $this->getJson('/api/public/cars/nonaktif')->assertNotFound();

        $busy = $this->getJson('/api/public/availability?car=toyota-innova-2019&start=2026-10-26T10:00:00%2B07:00&end=2026-10-28T10:00:00%2B07:00')
            ->assertOk()->assertJsonPath('data.available', false);
        $this->assertSame(['available', 'service', 'days', 'estimated_total', 'deposit'], array_keys($busy->json('data')));
        $this->assertNoSensitiveData($busy->getContent());

        $this->getJson('/api/public/availability?car=toyota-innova-2019&service=dengan_driver&start=2026-11-02T10:00:00%2B07:00&end=2026-11-04T10:00:00%2B07:00')
            ->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.days', 2)->assertJsonPath('data.estimated_total', 1800000);

        // Daftar dengan rentang tanggal → status per mobil.
        $this->getJson('/api/public/cars?start=2026-10-26T10:00:00%2B07:00&end=2026-10-27T10:00:00%2B07:00')
            ->assertJsonPath('data.0.period.available', false);
    }

    public function test_site_settings_only_public_values(): void
    {
        app(SettingService::class)->update(['business_whatsapp' => '081234567890', 'invoice_footer' => 'INTERNAL-FOOTER']);

        $res = $this->getJson('/api/public/site-settings')->assertOk()
            ->assertJsonPath('data.business.whatsapp_url', 'https://wa.me/6281234567890')
            ->assertJsonStructure(['data' => ['business', 'hero' => ['title', 'subtitle'], 'how_to', 'terms', 'faq', 'testimonials', 'rental_rules']]);
        $this->assertStringNotContainsString('INTERNAL-FOOTER', $res->getContent());
        $this->assertStringNotContainsString('booking_buffer', $res->getContent());
    }

    public function test_booking_request_lands_in_admin_with_honeypot_and_validation(): void
    {
        $payload = [
            'name' => '  Rina   Kartika ', 'phone' => '0812-3456-7890', 'car' => 'toyota-innova-2019',
            'start_at' => '2026-11-02T10:00:00+07:00', 'end_at' => '2026-11-04T10:00:00+07:00', 'service_type' => 'lepas_kunci',
            'notes' => '<b>Untuk mudik</b>',
        ];

        $this->postJson('/api/public/booking-requests', $payload)->assertCreated()
            ->assertJsonPath('period.available', true)
            ->assertJsonPath('period.estimated_total', 1400000);

        $req = BookingRequest::firstOrFail();
        $this->assertSame('Rina Kartika', $req->name);
        $this->assertSame('+6281234567890', $req->phone);
        $this->assertSame('Untuk mudik', $req->notes);
        $this->assertSame('baru', $req->status->value);
        $this->assertSame($this->published->id, $req->car_id);

        // Masuk ke admin (Permintaan Booking).
        $this->actingAs($this->userWithRole('staff'))->getJson('/api/v1/booking-requests')
            ->assertOk()->assertJsonPath('data.0.name', 'Rina Kartika')->assertJsonPath('data.0.car.name', 'Toyota Innova');

        // Honeypot: balasan sukses, tapi tidak disimpan.
        $this->postJson('/api/public/booking-requests', $payload + ['website' => 'http://spam'])->assertCreated();
        $this->assertSame(1, BookingRequest::count());

        // Validasi.
        $this->postJson('/api/public/booking-requests', array_merge($payload, ['phone' => '021555', 'car' => 'tidak-tampil', 'start_at' => '2026-10-20T10:00:00+07:00']))
            ->assertStatus(422)->assertJsonValidationErrors(['phone', 'car'])
            ->assertJsonPath('errors.car.0', 'Mobil yang dipilih tidak tersedia untuk dipesan.');
        $this->postJson('/api/public/booking-requests', array_merge($payload, ['start_at' => '2026-10-20T10:00:00+07:00', 'phone' => '081299990001']))
            ->assertStatus(422)->assertJsonPath('errors.start_at.0', 'Waktu mulai minimal 30 menit dari sekarang.');
    }

    public function test_booking_rate_limits_per_phone_and_ip(): void
    {
        $base = ['name' => 'Spam', 'start_at' => '2026-11-02T10:00:00+07:00', 'end_at' => '2026-11-03T10:00:00+07:00', 'service_type' => 'lepas_kunci'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/public/booking-requests', $base + ['phone' => '081200000001'])->assertCreated();
        }
        $this->postJson('/api/public/booking-requests', $base + ['phone' => '081200000001'])->assertStatus(429)
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Terlalu banyak percobaan.'));

        // IP yang sama, nomor berbeda: dibatasi 5 per 10 menit.
        $this->postJson('/api/public/booking-requests', $base + ['phone' => '081200000002'])->assertCreated();
        $this->postJson('/api/public/booking-requests', $base + ['phone' => '081200000003'])->assertCreated();
        $this->postJson('/api/public/booking-requests', $base + ['phone' => '081200000004'])->assertStatus(429);
        $this->assertSame(5, BookingRequest::count());
    }

    public function test_public_get_rate_limit(): void
    {
        config(['rentalpro.public_rate_limit' => 3]);
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/public/cars')->assertOk();
        }
        $this->getJson('/api/public/cars')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_cache_is_invalidated_and_landing_revalidated_when_admin_changes_data(): void
    {
        $this->getJson('/api/public/cars')->assertJsonPath('data.0.rate_self_drive', 700000);

        Bus::fake([RevalidateLanding::class]);
        $this->actingAs($this->userWithRole('admin'))->putJson("/api/v1/cars/{$this->published->id}", [
            'brand' => 'Toyota', 'model' => 'Innova', 'plate_number' => 'F 1234 RTY', 'transmission' => 'matic', 'year' => 2019,
            'rate_self_drive' => 750000, 'rate_with_driver' => 950000, 'odometer' => 55123, 'status' => 'tersedia', 'is_published' => true,
        ])->assertOk();
        Bus::assertDispatched(RevalidateLanding::class);

        $this->getJson('/api/public/cars')->assertJsonPath('data.0.rate_self_drive', 750000);

        // Unpublish → hilang dari publik.
        $this->published->forceFill(['is_published' => false])->save();
        $this->getJson('/api/public/cars')->assertJsonCount(0, 'data');
        $this->getJson('/api/public/cars/toyota-innova-2019')->assertNotFound();
    }

    public function test_revalidate_webhook_posts_to_landing_with_secret(): void
    {
        config(['services.landing.revalidate_url' => 'https://landing.test/api/revalidate', 'services.landing.revalidate_secret' => 's3cret']);
        Http::fake(['landing.test/*' => Http::response(['revalidated' => true])]);

        (new RevalidateLanding('car'))->handle();

        Http::assertSent(fn ($r) => $r->url() === 'https://landing.test/api/revalidate'
            && $r->hasHeader('X-Revalidate-Secret', 's3cret')
            && $r['tags'] === ['public']);

        config(['services.landing.revalidate_url' => null]);
        Http::fake();
        (new RevalidateLanding('x'))->handle();
        Http::assertNothingSent();
    }

    public function test_landing_down_does_not_break_admin_save_on_sync_queue(): void
    {
        config(['queue.default' => 'sync', 'services.landing.revalidate_url' => 'https://landing.test/api/revalidate', 'services.landing.revalidate_secret' => 's3cret']);
        Http::fake(['landing.test/*' => Http::response('down', 503)]);

        $this->actingAs($this->userWithRole('owner'))
            ->putJson('/api/v1/settings', ['settings' => ['landing_hero_title' => 'Sewa mobil nyaman di Bogor']])
            ->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://landing.test/api/revalidate');
        $this->getJson('/api/public/site-settings')->assertJsonPath('data.hero.title', 'Sewa mobil nyaman di Bogor');
    }

    public function test_landing_settings_require_landing_manage(): void
    {
        $payload = ['settings' => ['landing_hero_title' => 'Sewa mobil murah di Bogor']];
        $this->actingAs($this->userWithRole('admin'))->putJson('/api/v1/settings', $payload)->assertForbidden();
        $this->actingAs($this->userWithRole('owner'))->putJson('/api/v1/settings', $payload)->assertOk();
        $this->getJson('/api/public/site-settings')->assertJsonPath('data.hero.title', 'Sewa mobil murah di Bogor');

        $this->actingAs($this->userWithRole('owner'))->putJson('/api/v1/settings', ['settings' => [
            'landing_faq' => [['q' => 'Pendek', 'a' => '']],
            'landing_testimonials' => [['name' => 'A', 'text' => 'Mantap sekali', 'rating' => 9]],
            'landing_maps_url' => 'javascript:alert(1)',
        ]])->assertStatus(422)->assertJsonValidationErrors(['settings.landing_faq.0.a', 'settings.landing_testimonials.0.rating', 'settings.landing_maps_url']);
    }
}
