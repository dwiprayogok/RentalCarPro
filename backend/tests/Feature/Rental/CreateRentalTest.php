<?php

namespace Tests\Feature\Rental;

use App\Enums\CarStatus;
use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Rental;
use App\Services\SettingService;
use Tests\TestCase;

class CreateRentalTest extends TestCase
{
    use RentalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeNow();
    }

    public function test_staff_creates_booking_with_numbered_invoice_and_server_side_totals(): void
    {
        $car = $this->car();
        $customer = $this->customer();

        $res = $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/rentals', $this->payload(
            $car, $customer, '2026-10-22 10:00', '2026-10-24 11:30', [
                // Klien mencoba mengirim total palsu: harus diabaikan.
                'total_amount' => 1,
                'payments' => [['type' => 'dp', 'method' => 'transfer', 'amount' => 300000, 'reference_number' => 'BCA-001']],
            ],
        ));

        $res->assertCreated()
            ->assertJsonPath('message', 'Sewa TRX-2026-0001 berhasil dibuat.')
            ->assertJsonPath('data.code', 'TRX-2026-0001')
            ->assertJsonPath('data.status', 'booking')
            ->assertJsonPath('data.billed_days', 2) // 49,5 jam: dalam toleransi 2 jam dari 48 jam
            ->assertJsonPath('data.daily_rate', 550000)
            ->assertJsonPath('data.rental_amount', 1100000)
            ->assertJsonPath('data.billing.total_amount', 1100000)
            ->assertJsonPath('data.billing.paid_amount', 300000)
            ->assertJsonPath('data.billing.balance', 800000)
            ->assertJsonPath('data.billing.payment_status_label', 'Belum lunas')
            ->assertJsonPath('data.billing.deposit_required', 1000000)
            ->assertJsonPath('data.start_at', '2026-10-22T10:00:00+07:00')
            ->assertJsonPath('data.price_breakdown.lines.0.date', '2026-10-22');

        $this->assertSame(CarStatus::Tersedia, $car->fresh()->status, 'Booking tidak mengubah status mobil');
        $this->assertDatabaseHas('rentals', ['code' => 'TRX-2026-0001', 'start_at' => '2026-10-22 03:00:00']);
    }

    public function test_start_now_creates_active_rental_with_handover_and_marks_car_rented(): void
    {
        $car = $this->car(['odometer' => 10000]);

        $res = $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/rentals', [
            'customer_id' => $this->customer()->id,
            'car_id' => $car->id,
            'service_type' => 'lepas_kunci',
            'start_now' => true,
            'planned_end_at' => $this->wib('2026-10-22 15:00'),
            'handover' => [
                'odometer' => 10050,
                'fuel_level' => 75,
                'checklist' => [['item' => 'Ban', 'ok' => true], ['item' => 'Body', 'ok' => false, 'note' => 'Baret pintu kiri']],
                'notes' => 'Kunci + STNK diserahkan',
            ],
            'payments' => [
                ['type' => 'pelunasan', 'method' => 'tunai', 'amount' => 550000],
                ['type' => 'deposit', 'method' => 'tunai', 'amount' => 1000000],
            ],
        ])->assertCreated();

        $res->assertJsonPath('data.status', 'aktif')
            ->assertJsonPath('data.billed_days', 1)
            ->assertJsonPath('data.billing.payment_status_label', 'Lunas')
            ->assertJsonPath('data.billing.paid_amount', 550000)
            ->assertJsonPath('data.billing.deposit_held', 1000000)
            ->assertJsonPath('data.inspections.serah_terima.odometer', 10050)
            ->assertJsonPath('data.inspections.serah_terima.checklist.1.note', 'Baret pintu kiri');

        $car->refresh();
        $this->assertSame(CarStatus::Disewa, $car->status);
        $this->assertSame(10050, $car->odometer);
    }

    public function test_handover_odometer_cannot_be_below_car_odometer(): void
    {
        $car = $this->car(['odometer' => 10000]);

        $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/rentals', [
            'customer_id' => $this->customer()->id, 'car_id' => $car->id, 'service_type' => 'lepas_kunci',
            'start_now' => true, 'planned_end_at' => $this->wib('2026-10-22 15:00'),
            'handover' => ['odometer' => 9000, 'fuel_level' => 50],
        ])->assertStatus(422)->assertJsonValidationErrors(['handover.odometer']);

        $this->assertSame(0, Rental::count(), 'Tidak ada sewa setengah jadi');
        $this->assertSame(CarStatus::Tersedia, $car->fresh()->status);
    }

    public function test_overlapping_schedule_is_rejected_with_buffer(): void
    {
        $car = $this->car();
        $staff = $this->userWithRole('staff');
        $create = fn (string $s, string $e) => $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), $s, $e));

        $create('2026-10-22 10:00', '2026-10-23 10:00')->assertCreated();

        // Tumpang tindih langsung
        $create('2026-10-22 18:00', '2026-10-24 10:00')
            ->assertStatus(422)
            ->assertJsonPath('errors.car_id.0', 'Mobil ini sudah terjadwal pada waktu tersebut: TRX-2026-0001 (22 Okt 10.00 – 23 Okt 10.00 WIB). Jeda antar sewa minimal 2 jam.');

        // Mulai 1 jam setelah selesai: kena buffer 2 jam
        $create('2026-10-23 11:00', '2026-10-24 11:00')->assertStatus(422);
        // Selesai 1 jam sebelum mulai sewa lain: kena buffer
        $create('2026-10-21 16:00', '2026-10-22 09:00')->assertStatus(422);

        // Tepat 2 jam setelahnya: boleh
        $create('2026-10-23 12:00', '2026-10-24 12:00')->assertCreated()->assertJsonPath('data.code', 'TRX-2026-0002');

        // Mobil lain di jam yang sama: boleh
        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($this->car(), $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00'))
            ->assertCreated();
    }

    public function test_buffer_comes_from_settings(): void
    {
        app(SettingService::class)->update(['booking_buffer_minutes' => 0]);
        $car = $this->car();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00'))->assertCreated();
        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-23 10:00', '2026-10-24 10:00'))->assertCreated();
    }

    public function test_cancelled_and_finished_rentals_do_not_block(): void
    {
        $car = $this->car();
        Rental::factory()->for($car)->status(RentalStatus::Batal)->create(['start_at' => '2026-10-22 03:00:00', 'planned_end_at' => '2026-10-23 03:00:00']);
        Rental::factory()->for($car)->status(RentalStatus::Selesai)->create(['start_at' => '2026-10-22 03:00:00', 'planned_end_at' => '2026-10-23 03:00:00']);

        $this->actingAs($this->userWithRole('staff'))
            ->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00'))
            ->assertCreated();
    }

    public function test_overdue_active_rental_keeps_blocking_until_returned(): void
    {
        $car = $this->car();
        // Aktif, seharusnya kembali kemarin (sekarang terlambat).
        Rental::factory()->for($car)->status(RentalStatus::Aktif)->create([
            'code' => 'TRX-2026-0099', 'start_at' => '2026-10-18 03:00:00', 'planned_end_at' => '2026-10-20 03:00:00',
        ]);

        // Booking untuk 1 jam dari sekarang tetap ditolak: mobil belum kembali.
        $this->actingAs($this->userWithRole('staff'))
            ->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-21 16:00', '2026-10-22 16:00'))
            ->assertStatus(422)->assertJsonValidationErrors(['car_id']);
    }

    public function test_blacklisted_customer_rejected_and_only_owner_can_override_with_reason(): void
    {
        $car = $this->car();
        $blacklisted = Customer::factory()->selfDriveReady()->blacklisted()->create();
        $payload = $this->payload($car, $blacklisted, '2026-10-22 10:00', '2026-10-23 10:00');

        foreach (['staff', 'admin'] as $role) {
            $this->actingAs($this->userWithRole($role))->postJson('/api/v1/rentals', $payload + ['blacklist_override_reason' => 'Sudah melunasi tunggakan lama.'])
                ->assertStatus(422)
                ->assertJsonPath('errors.customer_id.0', 'Pelanggan ini berstatus Blacklist dan tidak bisa membuat sewa baru. Hanya pemilik yang dapat memberi pengecualian.');
        }

        $owner = $this->userWithRole('owner');
        $this->actingAs($owner)->postJson('/api/v1/rentals', $payload)
            ->assertStatus(422)->assertJsonPath('errors.blacklist_override_reason.0', 'Alasan pengecualian wajib diisi untuk pelanggan Blacklist.');

        $this->actingAs($owner)->postJson('/api/v1/rentals', $payload + ['blacklist_override_reason' => 'Sudah melunasi tunggakan lama.'])
            ->assertCreated()
            ->assertJsonPath('data.blacklist_override.reason', 'Sudah melunasi tunggakan lama.')
            ->assertJsonPath('data.blacklist_override.by', $owner->name);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'rental', 'event' => 'blacklist_override', 'causer_id' => $owner->id]);
    }

    public function test_self_drive_requires_complete_customer_data(): void
    {
        $incomplete = Customer::factory()->create(); // tanpa SIM/alamat/kontak darurat

        $this->actingAs($this->userWithRole('staff'))
            ->postJson('/api/v1/rentals', $this->payload($this->car(), $incomplete, '2026-10-22 10:00', '2026-10-23 10:00'))
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', 'Data pelanggan belum lengkap untuk layanan Lepas Kunci: Nomor SIM, Masa berlaku SIM, Alamat domisili, Kontak darurat.');

        // Dengan driver: data SIM pelanggan tidak wajib.
        $this->actingAs($this->userWithRole('staff'))
            ->postJson('/api/v1/rentals', $this->payload($this->car(), $incomplete, '2026-10-22 10:00', '2026-10-23 10:00', [
                'service_type' => 'dengan_driver', 'driver_id' => Driver::factory()->create()->id,
            ]))
            ->assertCreated()->assertJsonPath('data.daily_rate', 750000);
    }

    public function test_driver_rules(): void
    {
        $staff = $this->userWithRole('staff');
        $driver = Driver::factory()->create(['name' => 'Budi']);
        $base = fn ($car, array $extra = []) => $this->payload($car, $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00', $extra + ['service_type' => 'dengan_driver']);

        $this->actingAs($staff)->postJson('/api/v1/rentals', $base($this->car()))
            ->assertStatus(422)->assertJsonPath('errors.driver_id.0', 'Pilih driver untuk layanan Dengan Driver.');

        $this->actingAs($staff)->postJson('/api/v1/rentals', $base($this->car(), ['driver_id' => $driver->id]))->assertCreated();

        // Driver sama, mobil lain, jam bentrok → ditolak.
        $this->actingAs($staff)->postJson('/api/v1/rentals', $base($this->car(), ['driver_id' => $driver->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['driver_id']);
        $this->assertStringStartsWith('Driver Budi sudah terjadwal', $this->actingAs($staff)->postJson('/api/v1/rentals', $base($this->car(), ['driver_id' => $driver->id]))->json('errors.driver_id.0'));

        $inactive = Driver::factory()->create(['is_active' => false]);
        $this->actingAs($staff)->postJson('/api/v1/rentals', $base($this->car(), ['driver_id' => $inactive->id]))
            ->assertStatus(422)->assertJsonPath('errors.driver_id.0', 'Driver ini sedang nonaktif.');

        // Lepas kunci tidak boleh membawa driver.
        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($this->car(), $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00', ['driver_id' => $driver->id]))
            ->assertStatus(422)->assertJsonPath('errors.driver_id.0', 'Layanan Lepas Kunci tidak memakai driver.');
    }

    public function test_schedule_validation_and_car_status(): void
    {
        $staff = $this->userWithRole('staff');
        $car = $this->car();

        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-22 10:00', '2026-10-22 09:00'))
            ->assertStatus(422)->assertJsonPath('errors.planned_end_at.0', 'Jam kembali harus setelah waktu mulai sewa.');

        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-20 10:00', '2026-10-22 10:00'))
            ->assertStatus(422)->assertJsonValidationErrors(['start_at']);

        $this->actingAs($staff)->postJson('/api/v1/rentals', ['start_now' => true, 'car_id' => $car->id])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id', 'service_type', 'planned_end_at', 'handover']);

        $off = $this->car(['status' => CarStatus::Nonaktif]);
        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($off, $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00'))
            ->assertStatus(422)->assertJsonPath('errors.car_id.0', 'Mobil ini nonaktif dan tidak bisa disewakan.');
    }

    public function test_discount_requires_permission_and_reason(): void
    {
        $car = $this->car();
        $payload = $this->payload($car, $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00', ['discount' => 50000]);

        $this->actingAs($this->userWithRole('admin'))->postJson('/api/v1/rentals', $payload)
            ->assertStatus(422)->assertJsonPath('errors.discount_reason.0', 'Alasan diskon wajib diisi.');

        $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/rentals', $payload + ['discount_reason' => 'Langganan'])
            ->assertStatus(422)->assertJsonPath('errors.discount.0', 'Anda tidak berwenang memberi diskon.');

        $this->actingAs($this->userWithRole('admin'))->postJson('/api/v1/rentals', $payload + ['discount_reason' => 'Langganan'])
            ->assertCreated()->assertJsonPath('data.billing.total_amount', 500000);
    }

    public function test_numbers_stay_sequential_when_creation_fails(): void
    {
        $car = $this->car();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00'))
            ->assertJsonPath('data.code', 'TRX-2026-0001');
        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-22 12:00', '2026-10-23 12:00'))
            ->assertStatus(422);
        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($this->car(), $this->customer(), '2026-10-22 12:00', '2026-10-23 12:00'))
            ->assertJsonPath('data.code', 'TRX-2026-0002');
    }

    public function test_quote_returns_live_summary_and_conflicts_without_saving(): void
    {
        $car = $this->car();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-22 10:00', '2026-10-23 10:00'))->assertCreated();

        $this->actingAs($staff)->postJson('/api/v1/rentals/quote', [
            'car_id' => $car->id, 'service_type' => 'lepas_kunci',
            'start_at' => $this->wib('2026-10-25 10:00'), 'planned_end_at' => $this->wib('2026-10-28 10:00'), 'discount' => 100000,
        ])->assertOk()
            ->assertJsonPath('data.pricing.billed_days', 3)
            ->assertJsonPath('data.rental_amount', 1650000)
            ->assertJsonPath('data.total_amount', 1550000)
            ->assertJsonPath('data.deposit_required', 1000000)
            ->assertJsonPath('data.available', true);

        $this->actingAs($staff)->postJson('/api/v1/rentals/quote', [
            'car_id' => $car->id, 'service_type' => 'lepas_kunci',
            'start_at' => $this->wib('2026-10-22 20:00'), 'planned_end_at' => $this->wib('2026-10-24 10:00'),
            'customer_id' => Customer::factory()->blacklisted()->create()->id,
        ])->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.errors.customer_id', 'Pelanggan ini berstatus Blacklist dan tidak bisa membuat sewa baru.');

        $this->assertSame(1, Rental::count());
    }
}
