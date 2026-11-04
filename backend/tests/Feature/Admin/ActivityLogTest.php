<?php

namespace Tests\Feature\Admin;

use App\Models\Car;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    public function test_who_changed_what_and_when_with_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00', 'UTC'));
        $owner = $this->userWithRole('owner', ['name' => 'Bu Owner']);
        $admin = $this->userWithRole('admin', ['name' => 'Pak Admin']);
        $car = Car::factory()->create(['brand' => 'Toyota', 'model' => 'Innova', 'plate_number' => 'F 1234 RTY', 'rate_self_drive' => 700000, 'rate_with_driver' => 900000]);
        $customer = Customer::factory()->create(['name' => 'Dwi Prayogo']);

        $this->actingAs($admin)->putJson("/api/v1/cars/{$car->id}", [
            'brand' => 'Toyota', 'model' => 'Innova', 'plate_number' => 'F 1234 RTY', 'transmission' => 'matic', 'year' => 2019,
            'rate_self_drive' => 750000, 'rate_with_driver' => 950000, 'odometer' => $car->odometer, 'status' => 'tersedia',
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-22 08:00', 'UTC'));
        $this->actingAs($admin)->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Dwi Prayogo', 'phone' => $customer->phone, 'risk_status' => 'perhatian', 'risk_note' => 'Telat 2 kali.',
        ])->assertOk();
        $this->actingAs($owner)->putJson('/api/v1/settings', ['settings' => ['late_fee_per_hour' => 60000]])->assertOk();

        $res = $this->actingAs($owner)->getJson('/api/v1/activity-log')->assertOk();
        $rows = collect($res->json('data'));

        $carLog = $rows->first(fn ($r) => $r['module'] === 'car' && $r['event'] === 'updated');
        $this->assertSame('Pak Admin', $carLog['causer']['name']);
        $this->assertSame('Toyota Innova · F 1234 RTY', $carLog['subject']['label']);
        $this->assertSame('Mengubah', $carLog['event_label']);
        $this->assertContains(['field' => 'rate_self_drive', 'label' => 'Tarif lepas kunci', 'old' => 'Rp 700.000', 'new' => 'Rp 750.000'], $carLog['changes']);
        $this->assertSame('2026-10-21T15:00:00+07:00', $carLog['at']);

        $settingLog = $rows->first(fn ($r) => $r['module'] === 'setting');
        $this->assertSame('Denda keterlambatan per jam (Rp)', $settingLog['subject']['label']);
        $this->assertSame(['Rp 50.000', 'Rp 60.000'], [$settingLog['changes'][0]['old'], $settingLog['changes'][0]['new']]);

        $riskLog = $rows->first(fn ($r) => $r['module'] === 'customer');
        $this->assertContains(['field' => 'risk_status', 'label' => 'Status risiko', 'old' => 'lancar', 'new' => 'perhatian'], $riskLog['changes']);

        // Filter
        $this->actingAs($owner)->getJson('/api/v1/activity-log?module=car')->assertJsonCount($rows->where('module', 'car')->count(), 'data');
        $this->actingAs($owner)->getJson("/api/v1/activity-log?causer_id={$owner->id}")->assertJsonCount(1, 'data');
        $this->actingAs($owner)->getJson('/api/v1/activity-log?from=2026-10-22&to=2026-10-22')
            ->assertJsonCount($rows->filter(fn ($r) => str_starts_with($r['at'], '2026-10-22'))->count(), 'data');
        $this->actingAs($owner)->getJson('/api/v1/activity-log?search=Telat')->assertJsonCount(1, 'data');
        $this->actingAs($owner)->getJson('/api/v1/activity-log')->assertJsonFragment(['label' => 'Pengaturan', 'value' => 'setting']);
        $this->assertContains('Pak Admin', array_column($res->json('meta.causers'), 'name'));
    }

    public function test_nik_never_appears_in_activity_log(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->postJson('/api/v1/customers', ['name' => 'Rina', 'phone' => '081234567890', 'nik' => '3201010101950123'])->assertCreated();

        $json = $this->actingAs($admin)->getJson('/api/v1/activity-log')->getContent();
        $this->assertStringNotContainsString('3201010101950123', $json);
        $this->assertStringContainsString('Rina', $json);
    }

    public function test_payment_and_fee_entries_are_labelled_without_lazy_loading(): void
    {
        $admin = $this->userWithRole('admin');
        $rental = Rental::factory()->create(['status' => 'aktif', 'code' => 'TRX-2026-0777', 'total_amount' => 550000]);
        foreach (range(1, 3) as $i) {
            $this->actingAs($admin)->postJson("/api/v1/rentals/{$rental->id}/payments", ['type' => 'dp', 'method' => 'tunai', 'amount' => 1000])->assertCreated();
        }
        $rental->fees()->create(['type' => 'bbm', 'amount' => 50000, 'reason' => 'BBM kurang']);

        DB::enableQueryLog();
        $res = $this->actingAs($admin)->getJson('/api/v1/activity-log?module=payment')->assertOk();
        $queries = count(DB::getQueryLog());

        $this->assertSame('DP · TRX-2026-0777', $res->json('data.0.subject.label'));
        $this->assertSame('Rp 1.000', collect($res->json('data.0.changes'))->firstWhere('field', 'amount')['new']);
        $this->assertLessThan(15, $queries, 'Label subjek dimuat sekaligus, bukan per baris');
        $this->actingAs($admin)->getJson('/api/v1/activity-log?module=rental_fee')->assertOk()->assertJsonPath('data.0.subject.label', 'BBM kurang · TRX-2026-0777');
    }
}
