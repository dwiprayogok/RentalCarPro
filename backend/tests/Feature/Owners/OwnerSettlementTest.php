<?php

namespace Tests\Feature\Owners;

use App\Models\Car;
use App\Models\CarOwner;
use App\Models\Expense;
use App\Models\MaintenanceRecord;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerSettlementTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-05 03:00:00', 'UTC')); // 10.00 WIB
        Storage::fake('private');
        $this->owner = $this->userWithRole('owner');
    }

    /** Pendapatan diterima di Oktober (WIB) untuk satu mobil. */
    private function income(Car $car, int $amount, string $paidAtUtc = '2026-10-10 03:00:00'): void
    {
        $rental = Rental::factory()->for($car)->create(['start_at' => $paidAtUtc, 'planned_end_at' => Carbon::parse($paidAtUtc)->addDay()]);
        $rental->payments()->create(['type' => 'pelunasan', 'method' => 'transfer', 'amount' => $amount, 'paid_at' => $paidAtUtc]);
        $rental->payments()->create(['type' => 'deposit', 'method' => 'tunai', 'amount' => 999999, 'paid_at' => $paidAtUtc]); // titipan, bukan pendapatan
    }

    public function test_owner_crud_validation_and_car_assignment(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/car-owners', [
            'name' => 'H', 'phone' => '123', 'bank_name' => 'BCA', 'share_basis' => 'separuh', 'share_percent' => 150,
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'bank_account_number', 'share_basis', 'share_percent'])
            ->assertJsonPath('errors.share_percent.0', 'Persentase bagian pemilik maksimal 100%.');

        $id = $this->actingAs($this->owner)->postJson('/api/v1/car-owners', [
            'name' => 'Hendra Wijaya', 'phone' => '0812-7777-8888', 'bank_name' => 'BCA', 'bank_account_number' => '123-456-7890',
            'bank_account_name' => 'Hendra', 'share_basis' => 'bersih', 'share_percent' => 62.5,
        ])->assertCreated()
            ->assertJsonPath('data.phone', '+6281277778888')
            ->assertJsonPath('data.bank_account_number', '1234567890')
            ->assertJsonPath('data.share_label', '62,5% laba bersih')
            ->json('data.id');

        $car = Car::factory()->create(['plate_number' => 'F 1234 RTY']);
        $payload = Car::query()->find($car->id)->only(['brand', 'model', 'plate_number', 'year', 'rate_self_drive', 'rate_with_driver', 'odometer'])
            + ['transmission' => 'matic', 'status' => 'tersedia', 'owner_id' => $id];
        $this->actingAs($this->owner)->putJson("/api/v1/cars/{$car->id}", $payload)->assertOk()
            ->assertJsonPath('data.owner.name', 'Hendra Wijaya')->assertJsonPath('data.owner.share_label', '62,5% laba bersih');
        Car::factory()->create();

        $this->actingAs($this->owner)->getJson('/api/v1/cars?owner=titipan')->assertJsonCount(1, 'data');
        $this->actingAs($this->owner)->getJson('/api/v1/cars?owner=sendiri')->assertJsonCount(1, 'data');
        $this->actingAs($this->owner)->getJson('/api/v1/car-owners')->assertOk()
            ->assertJsonPath('data.0.cars_count', 1)->assertJsonPath('data.0.unpaid_total', 0);

        // Tidak bisa dihapus selama masih punya mobil.
        $this->actingAs($this->owner)->deleteJson("/api/v1/car-owners/{$id}")->assertStatus(422)->assertJsonValidationErrors('owner');
        $this->assertDatabaseHas('activity_log', ['log_name' => 'car_owner', 'event' => 'created', 'subject_id' => $id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'car', 'subject_id' => $car->id, 'event' => 'updated']);
    }

    public function test_gross_settlement_snapshot_payment_and_slip(): void
    {
        $o = CarOwner::factory()->create(['name' => 'Siti', 'phone' => '+6285711112222', 'share_basis' => 'kotor', 'share_percent' => 70]);
        $a = Car::factory()->create(['owner_id' => $o->id]);
        $b = Car::factory()->create(['owner_id' => $o->id]);
        $own = Car::factory()->create(); // milik rental, tidak ikut
        $this->income($a, 2000000);
        $this->income($b, 1000000);
        $this->income($a, 500000, '2026-10-31 18:00:00'); // 1 Nov 01.00 WIB → di luar Oktober
        $this->income($own, 5000000);
        MaintenanceRecord::factory()->for($a)->create(['cost' => 300000, 'performed_at' => '2026-10-15']);
        Expense::factory()->for($b)->create(['amount' => 100000, 'spent_at' => '2026-10-20']);

        $preview = $this->actingAs($this->owner)->getJson("/api/v1/car-owners/{$o->id}/settlement-preview?from=2026-10-01&to=2026-10-31")->assertOk();
        $preview->assertJsonPath('data.income', 3000000)
            ->assertJsonPath('data.maintenance', 300000)
            ->assertJsonPath('data.expenses', 100000)
            ->assertJsonPath('data.net', 2600000)
            ->assertJsonPath('data.base_amount', 3000000)   // kotor: biaya ditanggung rental
            ->assertJsonPath('data.owner_amount', 2100000)
            ->assertJsonPath('data.business_amount', 900000);
        $this->assertCount(2, $preview->json('data.lines'));

        $res = $this->actingAs($this->owner)->postJson('/api/v1/settlements', ['owner_id' => $o->id, 'from' => '2026-10-01', 'to' => '2026-10-31', 'notes' => 'Oktober'])
            ->assertCreated()->assertJsonPath('data.code', 'SET-2026-0001')->assertJsonPath('data.status', 'belum_dibayar')
            ->assertJsonPath('data.owner_amount', 2100000);
        $id = $res->json('data.id');
        $this->assertStringStartsWith('https://wa.me/6285711112222?text=', $res->json('data.whatsapp_url'));
        $this->assertStringContainsString(rawurlencode('Rp 2.100.000'), $res->json('data.whatsapp_url'));

        // Snapshot: data baru di periode yang sama tidak mengubah setoran.
        $this->income($a, 700000);
        $this->actingAs($this->owner)->getJson("/api/v1/settlements/{$id}")->assertJsonPath('data.income', 3000000)->assertJsonPath('data.lines.0.income', $res->json('data.lines.0.income'));

        // Periode tumpang tindih ditolak.
        $this->actingAs($this->owner)->postJson('/api/v1/settlements', ['owner_id' => $o->id, 'from' => '2026-10-31', 'to' => '2026-11-04'])
            ->assertStatus(422)->assertJsonPath('errors.period.0', 'Periode ini tumpang tindih dengan setoran SET-2026-0001 (1 Okt 2026 – 31 Okt 2026).');
        $this->actingAs($this->owner)->postJson('/api/v1/settlements', ['owner_id' => $o->id, 'from' => '2026-11-01', 'to' => '2026-11-30'])
            ->assertStatus(422)->assertJsonPath('errors.to.0', 'Periode setoran tidak boleh melewati hari ini.');

        // Bayar dengan bukti privat.
        $paid = $this->actingAs($this->owner)->post("/api/v1/settlements/{$id}/pay", [
            'method' => 'transfer', 'reference' => 'TRF-001', 'paid_at' => '2026-11-04T14:30', 'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.status', 'dibayar')->assertJsonPath('data.paid_at', '2026-11-04T14:30:00+07:00')
            ->assertJsonPath('data.can.pay', false)->assertJsonPath('data.can.delete', false);
        $this->get($paid->json('data.proof_url'))->assertOk();
        $this->get(route('v1.settlements.proof', ['settlement' => $id]))->assertForbidden();

        // Sudah dibayar: tidak bisa dibayar ulang / dibatalkan, bahkan oleh owner & admin.
        foreach ([$this->owner, $this->userWithRole('admin')] as $u) {
            $this->actingAs($u)->postJson("/api/v1/settlements/{$id}/pay", ['method' => 'tunai'])
                ->assertStatus(422)->assertJsonPath('errors.settlement.0', 'Setoran SET-2026-0001 sudah tercatat dibayar.');
            $this->actingAs($u)->deleteJson("/api/v1/settlements/{$id}")
                ->assertStatus(422)->assertJsonPath('errors.settlement.0', 'Setoran SET-2026-0001 sudah dibayar dan tidak bisa dibatalkan.');
        }
        $this->assertSame(1, Storage::disk('private')->allFiles() ? count(Storage::disk('private')->allFiles()) : 0, 'bukti dari percobaan bayar ulang tidak tertinggal');

        // Slip PDF: login & signed URL.
        $this->actingAs($this->owner)->get("/api/v1/settlements/{$id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $url = $this->actingAs($this->owner)->postJson("/api/v1/settlements/{$id}/print-url")->json('data.url');
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('v1.settlements.shared', ['settlement' => $id]))->assertForbidden();

        $this->assertDatabaseHas('activity_log', ['log_name' => 'owner_settlement', 'subject_id' => $id, 'event' => 'updated']);
    }

    public function test_net_basis_with_loss_pays_zero_and_unpaid_can_be_cancelled(): void
    {
        $o = CarOwner::factory()->net(60)->create();
        $car = Car::factory()->create(['owner_id' => $o->id]);
        $this->income($car, 1000000);
        MaintenanceRecord::factory()->for($car)->create(['cost' => 1500000, 'performed_at' => '2026-10-05']);

        $res = $this->actingAs($this->owner)->postJson('/api/v1/settlements', ['owner_id' => $o->id, 'from' => '2026-10-01', 'to' => '2026-10-31'])
            ->assertCreated()->assertJsonPath('data.base_amount', -500000)->assertJsonPath('data.owner_amount', 0)->assertJsonPath('data.business_amount', -500000);

        $this->actingAs($this->owner)->deleteJson('/api/v1/settlements/'.$res->json('data.id'))->assertOk();
        $this->assertSoftDeleted('owner_settlements', ['id' => $res->json('data.id')]);
        // Setelah dibatalkan, periode yang sama boleh dibuat ulang.
        $this->actingAs($this->owner)->postJson('/api/v1/settlements', ['owner_id' => $o->id, 'from' => '2026-10-01', 'to' => '2026-10-31'])->assertCreated();

        // Rounding: 60% dari Rp 333.333 = Rp 200.000 (dibulatkan).
        $o2 = CarOwner::factory()->net(60)->create();
        $c2 = Car::factory()->create(['owner_id' => $o2->id]);
        $this->income($c2, 333333);
        $this->actingAs($this->owner)->getJson("/api/v1/car-owners/{$o2->id}/settlement-preview?from=2026-10-01&to=2026-10-31")->assertJsonPath('data.owner_amount', 200000);

        // Pemilik tanpa mobil tidak bisa dibuatkan setoran.
        $empty = CarOwner::factory()->create();
        $this->actingAs($this->owner)->postJson('/api/v1/settlements', ['owner_id' => $empty->id, 'from' => '2026-10-01', 'to' => '2026-10-31'])
            ->assertStatus(422)->assertJsonPath('errors.owner_id.0', 'Pemilik ini belum punya mobil titipan.');
    }

    public function test_list_unpaid_summary_and_staff_has_no_access(): void
    {
        $o = CarOwner::factory()->create();
        $car = Car::factory()->create(['owner_id' => $o->id]);
        $this->income($car, 1000000);
        $this->actingAs($this->owner)->postJson('/api/v1/settlements', ['owner_id' => $o->id, 'from' => '2026-10-01', 'to' => '2026-10-31'])->assertCreated();

        $this->actingAs($this->owner)->getJson('/api/v1/settlements?status=belum_dibayar')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.unpaid.total', 700000)->assertJsonPath('data.0.owner.name', $o->name);
        $this->actingAs($this->owner)->getJson('/api/v1/car-owners')->assertJsonPath('data.0.unpaid_total', 700000)->assertJsonPath('data.0.unpaid_count', 1)
            ->assertJsonPath('data.0.last_period_to', '2026-10-31');

        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->getJson('/api/v1/car-owners')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/v1/settlements')->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->getJson('/api/v1/settlements')->assertOk();

        // Laporan laba menampilkan nama pemilik titipan.
        $this->actingAs($this->owner)->getJson('/api/v1/reports/profit?from=2026-10-01&to=2026-10-31')->assertJsonPath('data.0.owner', $o->name);
    }
}
