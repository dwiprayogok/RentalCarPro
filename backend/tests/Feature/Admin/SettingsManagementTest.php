<?php

namespace Tests\Feature\Admin;

use App\Enums\ServiceType;
use App\Models\Car;
use App\Services\Rental\PricingService;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    public function test_owner_updates_rules_and_identity_and_they_take_effect(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->putJson('/api/v1/settings', ['settings' => [
            'grace_period_minutes' => 60,
            'late_fee_per_hour' => 75000,
            'late_fee_cap_days' => 2,
            'booking_buffer_minutes' => 90,
            'weekend_rate_enabled' => true,
            'weekend_days' => [5, 6, 7],
            'business_name' => 'Rental Maju Jaya',
            'business_whatsapp' => '0812 3456 7890',
            'invoice_footer' => 'Hati-hati di jalan.',
        ]])->assertOk()
            ->assertJsonFragment(['key' => 'business_whatsapp', 'value' => '+6281234567890'])
            ->assertJsonFragment(['key' => 'weekend_days', 'value' => [5, 6, 7]]);

        // Perhitungan langsung memakai nilai baru (cache settings di-flush).
        $pricing = app(PricingService::class);
        $start = Carbon::parse('2026-10-21 08:00', 'UTC');
        $this->assertSame(2, $pricing->billedDays($start, $start->copy()->addMinutes(1441 + 60)));
        $fee = $pricing->lateFee($start, $start->copy()->addHours(20), 550000);
        // 20 jam × Rp 75.000 = Rp 1.500.000 → dibatasi 2 × tarif harian = Rp 1.100.000.
        $this->assertTrue($fee->capped);
        $this->assertSame(1100000, $fee->hourlyFee);
        $this->assertSame(90, app(SettingService::class)->int('booking_buffer_minutes'));
    }

    public function test_validation_ranges_in_indonesian(): void
    {
        $owner = $this->userWithRole('owner');

        $res = $this->actingAs($owner)->putJson('/api/v1/settings', ['settings' => [
            'grace_period_minutes' => 3000,
            'late_fee_cap_days' => 0,
            'late_fee_per_hour' => -1,
            'weekend_days' => [0, 8],
            'business_whatsapp' => '021555',
            'business_name' => '',
        ]])->assertStatus(422);

        $errors = $res->json('errors');
        $this->assertSame('masa toleransi (menit) harus bernilai antara 0 sampai 720.', $errors['settings.grace_period_minutes'][0]);
        $this->assertArrayHasKey('settings.late_fee_cap_days', $errors);
        $this->assertArrayHasKey('settings.late_fee_per_hour', $errors);
        $this->assertArrayHasKey('settings.weekend_days.0', $errors);
        $this->assertStringStartsWith('Format nomor whatsapp usaha tidak valid', $errors['settings.business_whatsapp'][0]);
        $this->assertArrayHasKey('settings.business_name', $errors);

        // Tidak ada yang tersimpan sebagian.
        $this->assertSame(120, app(SettingService::class)->int('grace_period_minutes'));
    }

    public function test_holidays_affect_weekend_pricing_and_are_logged(): void
    {
        $owner = $this->userWithRole('owner');
        app(SettingService::class)->update(['weekend_rate_enabled' => true]);
        $car = Car::factory()->create(['rate_self_drive' => 500000, 'weekend_rate_self_drive' => 650000]);
        $start = Carbon::parse('2026-12-24 10:00', 'Asia/Jakarta')->utc(); // Kamis
        $quote = fn () => app(PricingService::class)->quote($car, ServiceType::LepasKunci, $start, $start->copy()->addDays(2))->rentalAmount;

        $this->assertSame(1000000, $quote());

        $id = $this->actingAs($owner)->postJson('/api/v1/holidays', ['date' => '2026-12-25', 'name' => 'Hari Natal'])
            ->assertCreated()->json('data.id');
        $this->assertSame(500000 + 650000, $quote());

        $this->actingAs($owner)->postJson('/api/v1/holidays', ['date' => '2026-12-25', 'name' => 'Duplikat'])
            ->assertStatus(422)->assertJsonPath('errors.date.0', 'Tanggal ini sudah terdaftar sebagai hari libur.');
        $this->actingAs($owner)->getJson('/api/v1/holidays?year=2026')->assertOk()->assertJsonPath('data.0.name', 'Hari Natal');

        $this->actingAs($owner)->deleteJson("/api/v1/holidays/{$id}")->assertOk();
        $this->assertDatabaseHas('activity_log', ['log_name' => 'setting', 'event' => 'deleted', 'causer_id' => $owner->id]);
    }
}
