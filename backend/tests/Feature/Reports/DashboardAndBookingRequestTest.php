<?php

namespace Tests\Feature\Reports;

use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Carbon;
use Tests\Feature\Rental\RentalTestHelpers;
use Tests\TestCase;

class DashboardAndBookingRequestTest extends TestCase
{
    use RentalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', 'UTC'));
        $this->seed([DemoUserSeeder::class, DemoDataSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@rentalpro.test')->first();
    }

    public function test_dashboard_without_overdue_shows_calm_banner_and_kpis(): void
    {
        $res = $this->actingAs($this->owner())->getJson('/api/v1/dashboard')->assertOk();

        $res->assertJsonPath('data.banner.tone', 'warning') // ada pengingat STNK lewat jatuh tempo dari seeder
            ->assertJsonPath('data.kpi.active.count', 1)
            ->assertJsonPath('data.kpi.need_return.overdue', 0)
            ->assertJsonCount(7, 'data.cash_flow')
            ->assertJsonPath('data.cash_flow.6.date', '2026-10-21')
            ->assertJsonPath('data.new_booking_requests', 2);

        // KPI pembayaran bulan ini = jumlah pendapatan sejak 1 Okt WIB.
        $from = Carbon::parse('2026-10-01', 'Asia/Jakarta')->utc();
        $expected = (int) RentalPayment::where('paid_at', '>=', $from)->get()->sum(fn ($p) => $p->type->billEffect() * $p->amount);
        $res->assertJsonPath('data.kpi.payments_month.amount', $expected);

        $types = array_column($res->json('data.reminders'), 'type');
        $this->assertContains('pajak', $types);
        $this->assertContains('stnk', $types);
        $this->assertContains('servis', $types);
        $this->assertTrue(collect($res->json('data.reminders'))->firstWhere('type', 'stnk')['overdue']);
        $this->assertCount(3, $res->json('data.featured_cars'));
        $this->assertCount(5, $res->json('data.recent_rentals'));
    }

    public function test_banner_warns_about_overdue_with_running_fee(): void
    {
        Rental::where('status', 'aktif')->first()->forceFill(['planned_end_at' => now()->subHours(5)])->save();

        $this->actingAs($this->owner())->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.banner.tone', 'danger')
            ->assertJsonPath('data.banner.title', '1 sewa terlambat dikembalikan')
            ->assertJsonPath('data.banner.action.to', '/transaksi?view=terlambat')
            ->assertJsonPath('data.running_late_total', 250000)
            ->assertJsonPath('data.kpi.need_return.overdue', 1);
        $this->assertStringContainsString('Estimasi denda berjalan Rp 250.000', $this->actingAs($this->owner())->getJson('/api/v1/dashboard')->json('data.banner.message'));
    }

    public function test_booking_request_list_and_status_rules(): void
    {
        $staff = $this->userWithRole('staff');
        $res = $this->actingAs($staff)->getJson('/api/v1/booking-requests')->assertOk()
            ->assertJsonPath('meta.counts', ['semua' => 4, 'baru' => 2, 'dihubungi' => 1, 'dikonfirmasi' => 0, 'ditolak' => 1])
            ->assertJsonPath('data.0.status', 'baru');

        // Yuni sudah pelanggan → dicocokkan lewat nomor HP.
        $yuni = collect($res->json('data'))->firstWhere('name', 'Yuni Wahyuni');
        $this->assertSame('Yuni Wahyuni', $yuni['matched_customer']['name']);
        $this->assertNull(collect($res->json('data'))->firstWhere('name', 'Rina Kartika')['matched_customer']);

        $rina = BookingRequest::where('name', 'Rina Kartika')->first();
        $this->actingAs($staff)->patchJson("/api/v1/booking-requests/{$rina->id}", ['status' => 'dihubungi'])
            ->assertOk()->assertJsonPath('data.status', 'dihubungi')->assertJsonPath('data.handled_by', $staff->name);
        $this->actingAs($staff)->patchJson("/api/v1/booking-requests/{$rina->id}", ['status' => 'ditolak'])
            ->assertStatus(422)->assertJsonValidationErrors(['rejection_reason']);
        $this->actingAs($staff)->patchJson("/api/v1/booking-requests/{$rina->id}", ['status' => 'dikonfirmasi'])
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'booking_request', 'subject_id' => $rina->id]);
    }

    public function test_convert_booking_request_to_rental(): void
    {
        $req = BookingRequest::where('name', 'Rina Kartika')->first();
        $customer = Customer::factory()->selfDriveReady()->create(['name' => 'Rina Kartika', 'phone' => $req->phone]);
        $payload = [
            'customer_id' => $customer->id, 'car_id' => $req->car_id, 'service_type' => 'lepas_kunci',
            'start_at' => $req->start_at->toIso8601String(), 'planned_end_at' => $req->end_at->toIso8601String(),
            'booking_request_id' => $req->id,
        ];

        // Staff punya rental.create tapi tidak punya booking_request.convert.
        $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/rentals', $payload)
            ->assertStatus(422)->assertJsonValidationErrors(['booking_request_id']);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->postJson('/api/v1/rentals', $payload)->assertCreated();

        $req->refresh();
        $this->assertSame('dikonfirmasi', $req->status->value);
        $this->assertSame($admin->id, $req->handled_by);
        $this->assertSame($req->id, Rental::where('customer_id', $customer->id)->value('booking_request_id'));

        // Tidak bisa dikonversi dua kali.
        $this->actingAs($admin)->postJson('/api/v1/rentals', array_merge($payload, [
            'start_at' => $req->start_at->copy()->addDays(10)->toIso8601String(), 'planned_end_at' => $req->end_at->copy()->addDays(10)->toIso8601String(),
        ]))->assertStatus(422)->assertJsonPath('errors.booking_request_id.0', 'Permintaan booking ini sudah diproses (dikonfirmasi atau ditolak).');

        $this->actingAs($admin)->getJson("/api/v1/booking-requests/{$req->id}")
            ->assertJsonPath('data.rental.id', Rental::where('booking_request_id', $req->id)->value('id'))
            ->assertJsonPath('data.can.convert', false);
    }

    public function test_rejected_request_cannot_be_converted(): void
    {
        $req = BookingRequest::where('status', 'ditolak')->first();
        $this->actingAs($this->owner())->postJson('/api/v1/rentals', [
            'customer_id' => $this->customer()->id, 'car_id' => $this->car()->id, 'service_type' => 'lepas_kunci',
            'start_at' => $this->wib('2026-11-10 10:00'), 'planned_end_at' => $this->wib('2026-11-11 10:00'), 'booking_request_id' => $req->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['booking_request_id']);
    }

    public function test_booking_requests_require_permission(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/booking-requests')->assertForbidden();
    }
}
