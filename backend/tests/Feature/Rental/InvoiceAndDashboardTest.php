<?php

namespace Tests\Feature\Rental;

use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceAndDashboardTest extends TestCase
{
    use RentalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeNow();
    }

    private function overdueRental(): Rental
    {
        $car = $this->car(['brand' => 'BYD', 'model' => 'Atto 3', 'plate_number' => 'F 1576 FGH']);
        $id = $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/rentals', [
            'customer_id' => $this->customer(['name' => 'Dwi Prayogo', 'phone' => '+6281385270311'])->id, 'car_id' => $car->id,
            'service_type' => 'lepas_kunci', 'start_now' => true, 'planned_end_at' => $this->wib('2026-10-22 15:00'),
            'handover' => ['odometer' => $car->odometer, 'fuel_level' => 100],
            'payments' => [['type' => 'pelunasan', 'method' => 'transfer', 'amount' => 550000, 'reference_number' => 'BCA-1']],
        ])->json('data.id');

        // Sekarang 22 Okt 18.00 WIB → terlambat 3 jam (Rp 150.000 berjalan).
        Carbon::setTestNow(Carbon::parse('2026-10-22 18:00', 'Asia/Jakarta')->utc());

        return Rental::findOrFail($id);
    }

    public function test_invoice_json_shows_running_late_fee_and_whatsapp_link(): void
    {
        $rental = $this->overdueRental();

        $res = $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/rentals/{$rental->id}/invoice")->assertOk();
        $res->assertJsonPath('data.code', 'TRX-2026-0001')
            ->assertJsonPath('data.status_label', 'Terlambat')
            ->assertJsonPath('data.payment_status_label', 'Lunas (belum termasuk denda)')
            ->assertJsonPath('data.business.name', 'RentalPro')
            ->assertJsonPath('data.car.plate_number', 'F 1576 FGH')
            ->assertJsonPath('data.service_type_label', 'Lepas Kunci')
            ->assertJsonPath('data.lines.0.detail', '1 hari × Rp 550.000')
            ->assertJsonPath('data.totals.balance', 0)
            ->assertJsonPath('data.totals.running_late_fee', 150000)
            ->assertJsonPath('data.inspections.serah_terima.fuel_label', 'Penuh')
            ->assertJsonPath('data.note', 'Denda dan biaya tambahan akan difinalisasi saat pengembalian.');

        $this->assertCount(3, $res->json('data.terms'));
        $wa = $res->json('data.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281385270311?text=', $wa);
        $msg = rawurldecode(substr($wa, strpos($wa, '=') + 1));
        $this->assertStringContainsString('No. nota: TRX-2026-0001', $msg);
        $this->assertStringContainsString('belum termasuk estimasi denda Rp 150.000', $msg);
        $this->assertStringContainsString('signature=', $msg);
    }

    public function test_invoice_after_return_lists_fees_and_deposit(): void
    {
        $rental = $this->overdueRental();
        $this->actingAs($this->userWithRole('staff'))->postJson("/api/v1/rentals/{$rental->id}/payments", ['type' => 'deposit', 'method' => 'tunai', 'amount' => 1000000])->assertCreated();
        $this->actingAs($this->userWithRole('staff'))->postJson("/api/v1/rentals/{$rental->id}/return", [
            'odometer' => 20000, 'fuel_level' => 50, 'refund_method' => 'tunai',
            'fees' => [['type' => 'bbm', 'amount' => 50000, 'reason' => 'BBM kurang ½']],
        ])->assertOk();

        $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/rentals/{$rental->id}/invoice")
            ->assertJsonPath('data.status_label', 'Selesai')
            ->assertJsonPath('data.payment_status_label', 'Lunas')
            ->assertJsonPath('data.lines.1.label', 'Denda keterlambatan')
            ->assertJsonPath('data.lines.1.detail', 'Terlambat 3 jam; 3 jam')
            ->assertJsonPath('data.lines.1.amount', 150000)
            ->assertJsonPath('data.lines.2.label', 'Biaya bbm kurang')
            ->assertJsonPath('data.totals.total', 750000)
            ->assertJsonPath('data.deposit.deducted', 200000)
            ->assertJsonPath('data.deposit.refunded', 800000)
            ->assertJsonPath('data.inspections.kembali.fuel_label', '½')
            ->assertJsonPath('data.note', null);
    }

    public function test_pdf_is_generated_server_side_and_shared_link_is_signed(): void
    {
        $rental = $this->overdueRental();

        $pdf = $this->actingAs($this->userWithRole('staff'))->get("/api/v1/rentals/{$rental->id}/invoice/pdf");
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Tombol Cetak: signed URL 2 menit, bisa dibuka tanpa sesi.
        $print = $this->actingAs($this->userWithRole('staff'))->postJson("/api/v1/rentals/{$rental->id}/invoice/print-url")->assertOk()->json('data.url');
        $this->app['auth']->forgetGuards();
        $this->get($print)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Carbon::setTestNow(now()->addMinutes(3));
        $this->getJson($print)->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson("/api/v1/rentals/{$rental->id}/invoice/print-url")->assertForbidden();

        $shared = $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/rentals/{$rental->id}/invoice")->json('data.pdf_share_url');
        $this->app['auth']->forgetGuards();
        $this->get($shared)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->getJson(strtok($shared, '?'))->assertForbidden();

        Carbon::setTestNow(now()->addDays(8));
        $this->getJson($shared)->assertForbidden();
    }

    public function test_dashboard_summarizes_today_with_running_late_fee(): void
    {
        $rental = $this->overdueRental();
        $car = $this->car();
        Rental::factory()->for($car)->status(RentalStatus::Booking)->create([
            'start_at' => Carbon::parse('2026-10-22 20:00', 'Asia/Jakarta')->utc(), 'planned_end_at' => Carbon::parse('2026-10-23 20:00', 'Asia/Jakarta')->utc(),
            'total_amount' => 550000, 'paid_amount' => 0,
        ]);

        $this->actingAs($this->userWithRole('staff'))->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.counts.aktif', 1)
            ->assertJsonPath('data.counts.terlambat', 1)
            ->assertJsonPath('data.counts.ambil_hari_ini', 1)
            ->assertJsonPath('data.counts.belum_lunas', 1)
            ->assertJsonPath('data.receivable', 550000)
            ->assertJsonPath('data.running_late_total', 150000)
            ->assertJsonPath('data.overdue.0.code', $rental->code)
            ->assertJsonPath('data.overdue.0.billing.running_late_fee', 150000);

        $this->actingAs(User::factory()->create())->getJson('/api/v1/dashboard')->assertForbidden();
    }
}
