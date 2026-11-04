<?php

namespace Tests\Feature\Rental;

use App\Enums\CarStatus;
use App\Enums\RentalStatus;
use App\Models\Rental;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalLifecycleTest extends TestCase
{
    use RentalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeNow();
    }

    private function booking(array $extra = []): array
    {
        $car = $this->car();
        $res = $this->actingAs($this->userWithRole('staff'))
            ->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-21 16:00', '2026-10-23 16:00', $extra))
            ->assertCreated();

        return [$res->json('data.id'), $car];
    }

    public function test_handover_activates_booking_and_photos_are_private(): void
    {
        Storage::fake('private');
        [$id, $car] = $this->booking();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->postJson("/api/v1/rentals/{$id}/handover", ['odometer' => 10120, 'fuel_level' => 100, 'checklist' => [['item' => 'AC', 'ok' => true]]])
            ->assertOk()
            ->assertJsonPath('message', 'Serah terima tercatat. Sewa kini Aktif.')
            ->assertJsonPath('data.status', 'aktif');

        $this->assertSame(CarStatus::Disewa, $car->fresh()->status);
        $this->assertSame(10120, $car->fresh()->odometer);

        // Serah terima kedua ditolak.
        $this->actingAs($staff)->postJson("/api/v1/rentals/{$id}/handover", ['odometer' => 10120, 'fuel_level' => 100])
            ->assertStatus(422)->assertJsonPath('errors.status.0', 'Serah terima hanya untuk sewa berstatus Booking.');

        $res = $this->actingAs($staff)->post("/api/v1/rentals/{$id}/inspections/serah_terima/photos", [
            'photos' => [UploadedFile::fake()->image('depan.jpg'), UploadedFile::fake()->image('dasbor.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();

        $urls = $res->json('data.inspections.serah_terima.photo_urls');
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('signature=', $urls[0]);
        Storage::disk('private')->assertExists(Rental::find($id)->inspections()->first()->photos);

        $this->app['auth']->forgetGuards();
        $this->get($urls[0])->assertOk();
        $this->getJson(strtok($urls[0], '?'))->assertForbidden();
    }

    public function test_cancel_only_for_booking_and_frees_schedule(): void
    {
        [$id, $car] = $this->booking();

        $this->actingAs($this->userWithRole('staff'))->postJson("/api/v1/rentals/{$id}/cancel", ['reason' => 'Pelanggan batal'])
            ->assertForbidden(); // staff tidak punya rental.cancel

        $this->actingAs($this->userWithRole('admin'))->postJson("/api/v1/rentals/{$id}/cancel", ['reason' => 'Pelanggan batal'])
            ->assertOk()->assertJsonPath('data.status', 'batal')->assertJsonPath('data.billing.payment_status_label', 'Batal');

        // Jadwal yang sama kini bisa dipesan.
        $this->actingAs($this->userWithRole('staff'))
            ->postJson('/api/v1/rentals', $this->payload($car, $this->customer(), '2026-10-21 16:00', '2026-10-23 16:00'))
            ->assertCreated();
    }

    public function test_payments_are_computed_server_side_with_deposit_kept_separate(): void
    {
        Storage::fake('private');
        [$id] = $this->booking(); // 2 hari x 550.000 = 1.100.000, deposit 1.000.000
        $staff = $this->userWithRole('staff');
        $pay = fn (array $d) => $this->actingAs($staff)->postJson("/api/v1/rentals/{$id}/payments", $d);

        $pay(['type' => 'dp', 'method' => 'tunai', 'amount' => 500000])
            ->assertCreated()
            ->assertJsonPath('message', 'DP sebesar Rp 500.000 tercatat.')
            ->assertJsonPath('data.billing.balance', 600000)
            ->assertJsonPath('data.billing.payment_status_label', 'Belum lunas');

        $pay(['type' => 'pelunasan', 'method' => 'tunai', 'amount' => 700000])
            ->assertStatus(422)->assertJsonPath('errors.amount.0', 'Jumlah melebihi sisa tagihan (Rp 600.000). Catat kelebihan sebagai deposit bila perlu.');

        $pay(['type' => 'pelunasan', 'method' => 'transfer', 'amount' => 600000])
            ->assertStatus(422)->assertJsonPath('errors.reference_number.0', 'Isi nomor referensi atau unggah bukti bayar untuk pembayaran Transfer/QRIS.');

        $res = $this->actingAs($staff)->post("/api/v1/rentals/{$id}/payments", [
            'type' => 'pelunasan', 'method' => 'transfer', 'amount' => 600000, 'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.billing.balance', 0)
            ->assertJsonPath('data.billing.payment_status_label', 'Lunas');

        $proofUrl = $res->json('data.payments.1.proof_url');
        $this->assertStringContainsString('signature=', $proofUrl);
        $this->assertNull($res->json('data.payments.0.proof_url'));

        // Deposit tidak mempengaruhi status lunas, dan dibatasi sisa deposit.
        $pay(['type' => 'deposit', 'method' => 'qris', 'amount' => 1200000, 'reference_number' => 'QR1'])
            ->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $pay(['type' => 'deposit', 'method' => 'qris', 'amount' => 1000000, 'reference_number' => 'QR1'])
            ->assertCreated()
            ->assertJsonPath('data.billing.paid_amount', 1100000)
            ->assertJsonPath('data.billing.deposit_held', 1000000)
            ->assertJsonPath('data.billing.deposit_outstanding', 0);

        $pay(['type' => 'dp', 'method' => 'tunai', 'amount' => 1])->assertStatus(422)->assertJsonPath('errors.amount.0', 'Tagihan sewa ini sudah lunas.');

        // Refund belum di phase ini.
        $pay(['type' => 'refund', 'method' => 'tunai', 'amount' => 1])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_payment_permissions_and_delete_recalculates(): void
    {
        [$id] = $this->booking();
        $rental = Rental::find($id);
        $this->actingAs($this->userWithRole('staff'))->postJson("/api/v1/rentals/{$id}/payments", ['type' => 'dp', 'method' => 'tunai', 'amount' => 400000])->assertCreated();
        $payment = $rental->payments()->first();

        $this->actingAs($this->userWithRole('staff'))->deleteJson("/api/v1/rentals/{$id}/payments/{$payment->id}")->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->deleteJson("/api/v1/rentals/{$id}/payments/{$payment->id}")
            ->assertOk()->assertJsonPath('data.billing.paid_amount', 0)->assertJsonPath('data.billing.payment_status_label', 'Belum bayar');
        $this->assertSoftDeleted($payment);
    }

    public function test_overdue_rental_shows_running_late_fee_and_lunas_label(): void
    {
        $car = $this->car();
        $rental = Rental::factory()->for($car)->status(RentalStatus::Aktif)->create([
            'start_at' => '2026-10-20 03:00:00', 'planned_end_at' => '2026-10-21 03:00:00', // 10.00 WIB kemarin s/d hari ini 10.00
            'daily_rate' => 550000, 'total_amount' => 550000, 'paid_amount' => 550000,
        ]);

        // Sekarang 15.00 WIB → terlambat 5 jam = Rp 250.000
        $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/rentals/{$rental->id}")
            ->assertOk()
            ->assertJsonPath('data.display_status', 'terlambat')
            ->assertJsonPath('data.display_status_label', 'Terlambat')
            ->assertJsonPath('data.billing.running_late_fee', 250000)
            ->assertJsonPath('data.billing.estimated_due', 250000)
            ->assertJsonPath('data.billing.payment_status_label', 'Lunas (belum termasuk denda)');

        Carbon::setTestNow(Carbon::parse('2026-10-21 04:30:00', 'UTC')); // 11.30 WIB: dalam toleransi
        $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/rentals/{$rental->id}")
            ->assertJsonPath('data.display_status', 'terlambat')
            ->assertJsonPath('data.billing.running_late_fee', 0)
            ->assertJsonPath('data.billing.payment_status_label', 'Lunas');
    }
}
