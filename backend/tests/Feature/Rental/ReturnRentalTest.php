<?php

namespace Tests\Feature\Rental;

use App\Enums\CarStatus;
use App\Enums\RiskStatus;
use App\Models\Car;
use App\Models\Customer;
use App\Models\MaintenanceRecord;
use App\Models\Rental;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReturnRentalTest extends TestCase
{
    use RentalTestHelpers;

    private Car $carModel;

    private Customer $cust;

    /** Rencana kembali: Kamis 22 Okt 2026 15.00 WIB. */
    private const PLANNED_END = '2026-10-22 15:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeNow(); // Rabu 21 Okt 15.00 WIB
    }

    /** Sewa aktif 1 hari (Rp 550.000) yang sudah lunas + deposit Rp 1.000.000 ditahan. */
    private function activeRental(): Rental
    {
        $this->carModel = $this->car(['odometer' => 10000]);
        $this->cust = $this->customer();

        $id = $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/rentals', [
            'customer_id' => $this->cust->id, 'car_id' => $this->carModel->id, 'service_type' => 'lepas_kunci',
            'start_now' => true, 'planned_end_at' => $this->wib(self::PLANNED_END),
            'handover' => ['odometer' => 10000, 'fuel_level' => 100],
            'payments' => [
                ['type' => 'pelunasan', 'method' => 'tunai', 'amount' => 550000],
                ['type' => 'deposit', 'method' => 'tunai', 'amount' => 1000000],
            ],
        ])->assertCreated()->json('data.id');

        return Rental::findOrFail($id);
    }

    private function returnAfter(Rental $rental, int $minutesLate, array $extra = []): TestResponse
    {
        Carbon::setTestNow(Carbon::parse(self::PLANNED_END, 'Asia/Jakarta')->utc()->addMinutes($minutesLate));

        return $this->actingAs($this->userWithRole('staff'))->postJson("/api/v1/rentals/{$rental->id}/return", array_merge([
            'odometer' => 10350, 'fuel_level' => 75,
            'checklist' => [['item' => 'Body & cat', 'ok' => true]],
            'refund_method' => 'tunai',
        ], $extra));
    }

    public static function lateScenarios(): array
    {
        // [menit terlambat, denda, hari tambahan, total akhir, potong deposit, deposit kembali, auto-flag]
        return [
            '1 jam: dalam toleransi' => [60, 0, 0, 550000, 0, 1000000, false],
            '5 jam: 5 x Rp 50.000' => [300, 250000, 0, 800000, 250000, 750000, true],
            '30 jam: 1 hari + 6 jam' => [1800, 850000, 1, 1400000, 850000, 150000, true],
            '12 hari: 12 hari tambahan' => [12 * 1440, 6600000, 12, 7150000, 1000000, 0, true],
        ];
    }

    #[DataProvider('lateScenarios')]
    public function test_return_scenarios(int $late, int $fee, int $extraDays, int $total, int $deduct, int $refund, bool $flag): void
    {
        $rental = $this->activeRental();

        $res = $this->returnAfter($rental, $late)->assertOk()
            ->assertJsonPath('message', "Mobil kembali. Sewa {$rental->code} selesai.")
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.late_fee', $fee)
            ->assertJsonPath('data.late_extra_days', $extraDays)
            ->assertJsonPath('data.billing.total_amount', $total)
            ->assertJsonPath('data.billing.deposit_held', 0);

        $balance = $total - 550000 - $deduct;
        $res->assertJsonPath('data.billing.balance', $balance)
            ->assertJsonPath('data.billing.paid_amount', 550000 + $deduct);

        $this->assertSame($deduct, (int) $rental->payments()->where('type', 'deposit_potong')->sum('amount'));
        $this->assertSame($refund, (int) $rental->payments()->where('type', 'deposit_kembali')->sum('amount'));

        $car = $this->carModel->fresh();
        $this->assertSame(CarStatus::Tersedia, $car->status);
        $this->assertSame(10350, $car->odometer);

        $this->assertSame($flag ? RiskStatus::Perhatian : RiskStatus::Lancar, $this->cust->fresh()->risk_status);
        if ($flag) {
            $this->assertStringContainsString("pada {$rental->code}", $this->cust->fresh()->risk_note);
        }

        $this->assertDatabaseHas('activity_log', ['log_name' => 'rental', 'event' => 'returned', 'subject_id' => $rental->id]);
    }

    public function test_hourly_fee_is_capped_at_one_day_rate(): void
    {
        $rental = $this->activeRental();

        // 20 jam x Rp 50.000 = Rp 1.000.000 → dibatasi Rp 550.000 (tarif 1 hari).
        $this->postJson("/api/v1/rentals/{$rental->id}/return/preview", []); // tanpa login: 401
        Carbon::setTestNow(Carbon::parse(self::PLANNED_END, 'Asia/Jakarta')->utc()->addHours(20));
        $this->actingAs($this->userWithRole('staff'))->postJson("/api/v1/rentals/{$rental->id}/return/preview", ['odometer' => 10300])
            ->assertOk()
            ->assertJsonPath('data.late.charged_hours', 20)
            ->assertJsonPath('data.late.capped', true)
            ->assertJsonPath('data.late.hourly_fee', 550000)
            ->assertJsonPath('data.late.total', 550000)
            ->assertJsonPath('data.late.late_label', '20 jam')
            ->assertJsonPath('data.distance_km', 300);

        $this->assertSame('aktif', $rental->fresh()->status->value, 'Preview tidak menyimpan');
        $this->assertSame(0, $rental->fees()->count());
    }

    public function test_extra_fees_with_damage_send_car_to_service_and_log_fees(): void
    {
        $rental = $this->activeRental();

        $this->returnAfter($rental, 0, [
            'fees' => [
                ['type' => 'bbm', 'amount' => 0, 'reason' => ''], // baris kosong diabaikan
                ['type' => 'kebersihan', 'amount' => 100000, 'reason' => 'Interior kotor berat'],
                ['type' => 'kerusakan', 'amount' => 400000, 'reason' => 'Spion kiri pecah'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.extra_fees_total', 500000)
            ->assertJsonPath('data.billing.total_amount', 1050000)
            ->assertJsonPath('data.fees.1.reason', 'Spion kiri pecah');

        $this->assertSame(CarStatus::Servis, $this->carModel->fresh()->status);
        $this->assertDatabaseHas('maintenance_records', ['car_id' => $this->carModel->id, 'rental_id' => $rental->id, 'type' => 'perbaikan', 'notes' => 'Spion kiri pecah']);
        $this->assertSame(2, $rental->fees()->count());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'rental_fee', 'event' => 'created']);
        // Deposit menutup 500.000, sisa 500.000 dikembalikan.
        $this->assertSame(500000, (int) $rental->payments()->where('type', 'deposit_kembali')->sum('amount'));
    }

    public function test_fee_reason_required_and_car_status_override(): void
    {
        $rental = $this->activeRental();

        $this->returnAfter($rental, 0, ['fees' => [['type' => 'kerusakan', 'amount' => 100000, 'reason' => '']]])
            ->assertStatus(422)->assertJsonPath('errors', ['fees.0.reason' => ['Alasan wajib diisi untuk setiap biaya tambahan.']]);

        // Kerusakan kecil, admin memilih mobil tetap tersedia.
        $this->returnAfter($rental, 0, ['fees' => [['type' => 'kerusakan', 'amount' => 100000, 'reason' => 'Baret kecil']], 'car_status' => 'tersedia'])
            ->assertOk();
        $this->assertSame(CarStatus::Tersedia, $this->carModel->fresh()->status);
        $this->assertSame(0, MaintenanceRecord::count());
    }

    public function test_custom_deposit_deduction_and_final_payment_leave_receivable(): void
    {
        $rental = $this->activeRental();

        // Terlambat 5 jam (denda 250.000). Admin TIDAK memotong deposit, pelanggan bayar 100.000 transfer.
        $this->returnAfter($rental, 300, [
            'deposit_deduction' => 0,
            'payment' => ['amount' => 100000, 'method' => 'transfer', 'reference_number' => 'BCA-9'],
        ])->assertOk()
            ->assertJsonPath('data.billing.balance', 150000)
            ->assertJsonPath('data.billing.payment_status_label', 'Belum lunas');

        $this->assertSame(1000000, (int) $rental->payments()->where('type', 'deposit_kembali')->sum('amount'));
        $this->assertDatabaseHas('rental_payments', ['rental_id' => $rental->id, 'type' => 'pelunasan', 'method' => 'transfer', 'amount' => 100000]);
    }

    public function test_settlement_validation(): void
    {
        $rental = $this->activeRental();

        $this->returnAfter($rental, 300, ['deposit_deduction' => 300000])
            ->assertStatus(422)->assertJsonPath('errors.deposit_deduction.0', 'Potongan deposit maksimal Rp 250.000 (deposit ditahan / sisa tagihan).');

        $this->returnAfter($rental, 300, ['deposit_deduction' => 0, 'payment' => ['amount' => 300000, 'method' => 'tunai']])
            ->assertStatus(422)->assertJsonValidationErrors(['payment.amount']);

        $this->returnAfter($rental, 0, ['refund_method' => null])
            ->assertStatus(422)->assertJsonPath('errors.refund_method.0', 'Pilih metode pengembalian deposit.');

        $this->returnAfter($rental, 0, ['odometer' => 9000])
            ->assertStatus(422)->assertJsonPath('errors.odometer.0', 'Odometer akhir tidak boleh lebih kecil dari odometer serah terima (10.000 km).');

        $this->returnAfter($rental, 0, ['returned_at' => $this->wib('2026-10-30 10:00')])
            ->assertStatus(422)->assertJsonValidationErrors(['returned_at']);

        // Semua gagal → tidak ada perubahan setengah jadi.
        $this->assertSame('aktif', $rental->fresh()->status->value);
        $this->assertSame(0, $rental->inspections()->where('type', 'kembali')->count());
        $this->assertSame(CarStatus::Disewa, $this->carModel->fresh()->status);
    }

    public function test_only_active_rentals_and_permission(): void
    {
        $rental = $this->activeRental();
        $this->returnAfter($rental, 0)->assertOk();

        $this->returnAfter($rental, 0)->assertStatus(422)->assertJsonPath('errors.status.0', 'Proses Kembali hanya untuk sewa berstatus Aktif.');

        $nobody = User::factory()->create();
        $this->actingAs($nobody)->postJson("/api/v1/rentals/{$rental->id}/return", [])->assertForbidden();
    }

    public function test_blacklisted_customer_is_not_downgraded_by_auto_flag(): void
    {
        $rental = $this->activeRental();
        $this->cust->forceFill(['risk_status' => RiskStatus::Blacklist, 'risk_note' => 'Data uji'])->save();

        $this->returnAfter($rental, 600)->assertOk();
        $this->assertSame(RiskStatus::Blacklist, $this->cust->fresh()->risk_status);
    }

    public function test_auto_flag_can_be_disabled_in_settings(): void
    {
        app(SettingService::class)->update(['auto_flag_late_customer' => false]);
        $rental = $this->activeRental();

        $this->returnAfter($rental, 600)->assertOk();
        $this->assertSame(RiskStatus::Lancar, $this->cust->fresh()->risk_status);
    }
}
