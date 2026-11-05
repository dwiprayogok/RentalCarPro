<?php

namespace Tests\Feature\Expenses;

use App\Models\Car;
use App\Models\Expense;
use App\Models\MaintenanceRecord;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    private User $owner;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', 'UTC'));
        Storage::fake('private');
        $this->owner = $this->userWithRole('owner');
        $this->car = Car::factory()->create(['brand' => 'Toyota', 'model' => 'Avanza', 'plate_number' => 'F 1111 AA']);
    }

    public function test_create_with_private_proof_update_and_delete_are_logged(): void
    {
        $res = $this->actingAs($this->owner)->post('/api/v1/expenses', [
            'car_id' => $this->car->id, 'category' => 'bbm', 'title' => 'Isi BBM ke Bandung', 'spent_at' => '2026-10-20',
            'amount' => 350000, 'method' => 'tunai', 'vendor' => 'SPBU', 'proof' => UploadedFile::fake()->image('struk.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.category_label', 'BBM')
            ->assertJsonPath('data.car.plate_number', 'F 1111 AA')
            ->assertJsonPath('data.has_proof', true)
            ->assertJsonPath('message', 'Pengeluaran berhasil dicatat.');

        $expense = Expense::findOrFail($res->json('data.id'));
        Storage::disk('private')->assertExists($expense->proof_path);
        $this->assertStringStartsWith('expenses/2026/10/', $expense->proof_path);

        // Bukti hanya lewat signed URL.
        $url = $res->json('data.proof_url');
        $this->get($url)->assertOk();
        $this->get(route('v1.expenses.proof', ['expense' => $expense->id]))->assertForbidden();

        // Ganti bukti → file lama terhapus. Ubah jadi pengeluaran umum (car_id kosong).
        $old = $expense->proof_path;
        $this->actingAs($this->owner)->post("/api/v1/expenses/{$expense->id}", [
            '_method' => 'PUT', 'car_id' => '', 'category' => 'gaji', 'title' => 'Bonus driver', 'spent_at' => '2026-10-20', 'amount' => 400000,
            'proof' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.car', null)->assertJsonPath('data.amount', 400000);
        Storage::disk('private')->assertMissing($old);
        $newPath = $expense->fresh()->proof_path;

        // Hapus bukti saja.
        $this->actingAs($this->owner)->putJson("/api/v1/expenses/{$expense->id}", [
            'category' => 'gaji', 'title' => 'Bonus driver', 'spent_at' => '2026-10-20', 'amount' => 400000, 'remove_proof' => true,
        ])->assertOk()->assertJsonPath('data.has_proof', false)->assertJsonPath('data.proof_url', null);
        Storage::disk('private')->assertMissing($newPath);

        $this->actingAs($this->owner)->deleteJson("/api/v1/expenses/{$expense->id}")->assertOk();
        $this->assertSoftDeleted($expense);

        foreach (['created', 'updated', 'deleted'] as $event) {
            $this->assertDatabaseHas('activity_log', ['log_name' => 'expense', 'event' => $event, 'subject_id' => $expense->id, 'causer_id' => $this->owner->id]);
        }
        $log = $this->actingAs($this->owner)->getJson('/api/v1/activity-log?module=expense')->assertOk();
        $this->assertSame('Pengeluaran', $log->json('data.0.module_label'));
        $this->assertContains('Kategori', array_column($log->json('data.1.changes') ?? [], 'label'));
    }

    public function test_validation_messages(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/expenses', [
            'car_id' => 999999, 'category' => 'pesta', 'title' => 'x', 'spent_at' => '2026-10-25', 'amount' => 0, 'method' => 'cek',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['car_id', 'category', 'title', 'spent_at', 'amount', 'method'])
            ->assertJsonPath('errors.spent_at.0', 'Tanggal pengeluaran tidak boleh di masa depan.');

        $this->actingAs($this->owner)->post('/api/v1/expenses', [
            'category' => 'bbm', 'title' => 'Isi BBM', 'spent_at' => '2026-10-20', 'amount' => 100000,
            'proof' => UploadedFile::fake()->create('virus.exe', 10),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('errors.proof.0', 'Bukti harus berupa foto (JPG, PNG, WEBP) atau PDF.');
    }

    public function test_filters_and_summary(): void
    {
        $other = Car::factory()->create();
        Expense::factory()->for($this->car)->create(['category' => 'bbm', 'amount' => 100000, 'spent_at' => '2026-10-01', 'title' => 'BBM satu']);
        Expense::factory()->for($this->car)->create(['category' => 'cuci', 'amount' => 50000, 'spent_at' => '2026-10-10', 'vendor' => 'Cuci Kilat']);
        Expense::factory()->for($other)->create(['category' => 'bbm', 'amount' => 70000, 'spent_at' => '2026-09-15']);
        Expense::factory()->general()->create(['amount' => 3000000, 'spent_at' => '2026-10-05']);
        Expense::factory()->general()->create(['amount' => 999, 'spent_at' => '2026-10-06'])->delete();

        $all = $this->actingAs($this->owner)->getJson('/api/v1/expenses')->assertOk();
        $all->assertJsonCount(4, 'data')->assertJsonPath('meta.summary.total', 3220000)
            ->assertJsonPath('meta.summary.by_category.0.category', 'gaji')
            ->assertJsonPath('data.0.spent_at', '2026-10-10');
        $this->assertCount(10, $all->json('meta.categories'));

        $this->actingAs($this->owner)->getJson('/api/v1/expenses?car_id=umum')->assertJsonCount(1, 'data')->assertJsonPath('meta.summary.total', 3000000);
        $this->actingAs($this->owner)->getJson("/api/v1/expenses?car_id={$this->car->id}")->assertJsonPath('meta.summary.total', 150000);
        $this->actingAs($this->owner)->getJson('/api/v1/expenses?category=bbm&from=2026-10-01&to=2026-10-31')->assertJsonCount(1, 'data')->assertJsonPath('meta.summary.total', 100000);
        $this->actingAs($this->owner)->getJson('/api/v1/expenses?search=kilat')->assertJsonCount(1, 'data');
        $this->actingAs($this->owner)->getJson('/api/v1/expenses?car_id=abc')->assertStatus(422);
    }

    public function test_staff_can_record_but_not_change_and_cannot_see_profit(): void
    {
        $staff = $this->userWithRole('staff');
        $id = $this->actingAs($staff)->postJson('/api/v1/expenses', [
            'car_id' => $this->car->id, 'category' => 'parkir_tol', 'title' => 'Tol antar pelanggan', 'spent_at' => '2026-10-21', 'amount' => 45000,
        ])->assertCreated()->assertJsonPath('data.can.update', false)->assertJsonPath('data.can.delete', false)->json('data.id');

        $this->actingAs($staff)->putJson("/api/v1/expenses/{$id}", ['category' => 'bbm', 'title' => 'Ubah', 'spent_at' => '2026-10-21', 'amount' => 1])->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/v1/expenses/{$id}")->assertForbidden();
        $this->actingAs($staff)->getJson('/api/v1/reports/profit')->assertForbidden();
    }

    public function test_profit_report_with_controlled_data(): void
    {
        $car2 = Car::factory()->create();
        $rental = Rental::factory()->for($this->car)->create();
        $rental->payments()->create(['type' => 'pelunasan', 'method' => 'tunai', 'amount' => 2000000, 'paid_at' => '2026-10-10 03:00:00']);
        $rental->payments()->create(['type' => 'deposit', 'method' => 'tunai', 'amount' => 1000000, 'paid_at' => '2026-10-10 03:00:00']);
        MaintenanceRecord::factory()->for($this->car)->create(['cost' => 500000, 'performed_at' => '2026-10-12']);
        Expense::factory()->for($this->car)->create(['amount' => 300000, 'spent_at' => '2026-10-13']);
        Expense::factory()->for($car2)->create(['amount' => 100000, 'spent_at' => '2026-10-13']);
        Expense::factory()->general()->create(['amount' => 600000, 'spent_at' => '2026-10-02']);
        Expense::factory()->general()->create(['amount' => 600000, 'spent_at' => '2026-09-02']); // di luar rentang

        $res = $this->actingAs($this->owner)->getJson('/api/v1/reports/profit?from=2026-10-01&to=2026-10-21')->assertOk();
        $res->assertJsonPath('data.0.car_id', $this->car->id)
            ->assertJsonPath('data.0.income', 2000000) // deposit titipan bukan pendapatan
            ->assertJsonPath('data.0.costs', 800000)
            ->assertJsonPath('data.0.profit', 1200000)
            ->assertJsonPath('data.0.margin_pct', 60)
            ->assertJsonPath('data.0.expenses_by_category.bbm', 300000)
            ->assertJsonPath('data.1.profit', -100000)
            ->assertJsonPath('data.1.margin_pct', null)
            ->assertJsonPath('summary.cars_profit', 1100000)
            ->assertJsonPath('summary.general_expenses', 600000)
            ->assertJsonPath('summary.net_profit', 500000)
            ->assertJsonPath('summary.general_by_category.0.label', 'Gaji & komisi');
    }
}
