<?php

namespace Tests\Feature\Authorization;

use App\Enums\RentalStatus;
use App\Models\BookingRequest;
use App\Models\Car;
use App\Models\CarOwner;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\MaintenanceRecord;
use App\Models\OwnerSettlement;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Matriks role × aksi untuk SELURUH endpoint API yang butuh login.
 *
 * Untuk tiap endpoint: role yang TIDAK berhak harus mendapat 403 (dicek lebih dulu, sebelum
 * role yang berhak mengubah/menghapus data), dan role yang berhak TIDAK BOLEH mendapat 403
 * (boleh 200/201/422/404 tergantung data). "none" = pengguna login tanpa role.
 */
class PermissionMatrixTest extends TestCase
{
    private const ALL = ['owner', 'admin', 'staff'];

    private const MANAGERS = ['owner', 'admin'];

    private const OWNER = ['owner'];

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:00:00', 'UTC'));

        $car = Car::factory()->create();
        $photo = $car->photos()->create(['path' => 'cars/x.jpg']);
        $customer = Customer::factory()->selfDriveReady()->create();
        $active = Rental::factory()->for($car)->for($customer)->status(RentalStatus::Aktif)->create([
            'start_at' => now()->subDay(), 'planned_end_at' => now()->addDay(), 'picked_up_at' => now()->subDay(),
        ]);
        $active->inspections()->create(['type' => 'serah_terima', 'odometer' => 1, 'fuel_level' => 100, 'photos' => ['rentals/x.jpg'], 'inspected_at' => now()]);
        $booking = Rental::factory()->status(RentalStatus::Booking)->create(['start_at' => now()->addDays(5), 'planned_end_at' => now()->addDays(6)]);
        $payment = $active->payments()->create(['type' => 'dp', 'method' => 'tunai', 'amount' => 1000, 'paid_at' => now()]);

        $this->ids = [
            'car' => $car->id, 'photo' => $photo->id, 'customer' => $customer->id, 'active' => $active->id, 'booking' => $booking->id,
            'payment' => $payment->id, 'driver' => Driver::factory()->create()->id, 'maintenance' => MaintenanceRecord::factory()->create()->id,
            'request' => BookingRequest::create(['name' => 'Uji', 'phone' => '+6281200000099', 'start_at' => now()->addDays(3), 'end_at' => now()->addDays(4), 'service_type' => 'lepas_kunci'])->id,
            'holiday' => Holiday::create(['date' => '2026-12-25', 'name' => 'Natal'])->id,
            'expense' => Expense::factory()->create()->id,
            'carowner' => ($co = CarOwner::factory()->create())->id,
            'settlement' => OwnerSettlement::create([
                'code' => 'SET-2026-9999', 'owner_id' => $co->id, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30', 'share_basis' => 'kotor',
                'share_percent' => 70, 'income' => 0, 'maintenance' => 0, 'expenses' => 0, 'base_amount' => 0, 'owner_amount' => 0, 'business_amount' => 0, 'lines' => [],
            ])->id,
            'user' => User::factory()->withRole('staff')->create()->id,
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: list<string>}> */
    public static function endpoints(): array
    {
        return [
            // [method, uri dengan {placeholder}, role yang berhak]
            'auth me' => ['GET', '/api/v1/auth/me', [...self::ALL, 'none']],
            'dashboard' => ['GET', '/api/v1/dashboard', self::ALL],

            'settings lihat' => ['GET', '/api/v1/settings', self::ALL],
            'settings ubah' => ['PUT', '/api/v1/settings', self::OWNER],
            'libur lihat' => ['GET', '/api/v1/holidays', self::ALL],
            'libur tambah' => ['POST', '/api/v1/holidays', self::OWNER],
            'libur hapus' => ['DELETE', '/api/v1/holidays/{holiday}', self::OWNER],

            'roles' => ['GET', '/api/v1/roles', self::OWNER],
            'users daftar' => ['GET', '/api/v1/users', self::OWNER],
            'users tambah' => ['POST', '/api/v1/users', self::OWNER],
            'users ubah' => ['PUT', '/api/v1/users/{user}', self::OWNER],
            'users status' => ['PATCH', '/api/v1/users/{user}/status', self::OWNER],
            'users paksa reset' => ['POST', '/api/v1/users/{user}/force-password-reset', self::OWNER],
            'log aktivitas' => ['GET', '/api/v1/activity-log', self::MANAGERS],

            'armada daftar' => ['GET', '/api/v1/cars', self::ALL],
            'armada detail' => ['GET', '/api/v1/cars/{car}', self::ALL],
            'armada kalender' => ['GET', '/api/v1/cars/{car}/availability?from=2026-10-01&to=2026-10-31', self::ALL],
            'armada tambah' => ['POST', '/api/v1/cars', self::MANAGERS],
            'armada ubah' => ['PUT', '/api/v1/cars/{car}', self::MANAGERS],
            'armada foto unggah' => ['POST', '/api/v1/cars/{car}/photos', self::MANAGERS],
            'armada foto sampul' => ['PATCH', '/api/v1/cars/{car}/photos/{photo}', self::MANAGERS],
            'armada foto hapus' => ['DELETE', '/api/v1/cars/{car}/photos/{photo}', self::MANAGERS],
            'armada hapus' => ['DELETE', '/api/v1/cars/{car}', self::MANAGERS],

            'pelanggan daftar' => ['GET', '/api/v1/customers', self::ALL],
            'pelanggan detail' => ['GET', '/api/v1/customers/{customer}', self::ALL],
            'pelanggan riwayat' => ['GET', '/api/v1/customers/{customer}/rentals', self::ALL],
            'pelanggan tambah' => ['POST', '/api/v1/customers', self::ALL],
            'pelanggan ubah' => ['PUT', '/api/v1/customers/{customer}', self::ALL],
            'pelanggan dokumen unggah' => ['POST', '/api/v1/customers/{customer}/documents', self::ALL],
            'pelanggan dokumen hapus' => ['DELETE', '/api/v1/customers/{customer}/documents/ktp', self::ALL],
            'pelanggan hapus' => ['DELETE', '/api/v1/customers/{customer}', self::MANAGERS],

            'sewa daftar' => ['GET', '/api/v1/rentals', self::ALL],
            'sewa csv' => ['GET', '/api/v1/rentals/export', self::ALL],
            'sewa detail' => ['GET', '/api/v1/rentals/{active}', self::ALL],
            'sewa quote' => ['POST', '/api/v1/rentals/quote', self::ALL],
            'sewa baru' => ['POST', '/api/v1/rentals', self::ALL],
            'sewa serah terima' => ['POST', '/api/v1/rentals/{booking}/handover', self::ALL],
            'sewa batal' => ['POST', '/api/v1/rentals/{booking}/cancel', self::MANAGERS],
            'sewa kembali preview' => ['POST', '/api/v1/rentals/{active}/return/preview', self::ALL],
            'sewa kembali' => ['POST', '/api/v1/rentals/{active}/return', self::ALL],
            'nota' => ['GET', '/api/v1/rentals/{active}/invoice', self::ALL],
            'nota pdf' => ['GET', '/api/v1/rentals/{active}/invoice/pdf', self::ALL],
            'nota print url' => ['POST', '/api/v1/rentals/{active}/invoice/print-url', self::ALL],
            'pembayaran catat' => ['POST', '/api/v1/rentals/{active}/payments', self::ALL],
            'pembayaran hapus' => ['DELETE', '/api/v1/rentals/{active}/payments/{payment}', self::MANAGERS],
            'foto inspeksi unggah' => ['POST', '/api/v1/rentals/{active}/inspections/serah_terima/photos', self::ALL],
            'foto inspeksi hapus' => ['DELETE', '/api/v1/rentals/{active}/inspections/serah_terima/photos/0', self::ALL],

            'permintaan daftar' => ['GET', '/api/v1/booking-requests', self::ALL],
            'permintaan detail' => ['GET', '/api/v1/booking-requests/{request}', self::ALL],
            'permintaan ubah status' => ['PATCH', '/api/v1/booking-requests/{request}', self::ALL],

            'laporan pembayaran' => ['GET', '/api/v1/reports/payments', self::MANAGERS],
            'laporan piutang' => ['GET', '/api/v1/reports/receivables', self::MANAGERS],
            'laporan pendapatan' => ['GET', '/api/v1/reports/revenue-by-car', self::MANAGERS],
            'laporan utilisasi' => ['GET', '/api/v1/reports/utilization', self::MANAGERS],
            'laporan servis' => ['GET', '/api/v1/reports/maintenance', self::MANAGERS],
            'laporan laba' => ['GET', '/api/v1/reports/profit', self::MANAGERS],
            'laporan csv' => ['GET', '/api/v1/reports/payments/export', self::MANAGERS],

            'driver daftar' => ['GET', '/api/v1/drivers', self::ALL],
            'driver detail' => ['GET', '/api/v1/drivers/{driver}', self::ALL],
            'driver tambah' => ['POST', '/api/v1/drivers', self::MANAGERS],
            'driver ubah' => ['PUT', '/api/v1/drivers/{driver}', self::MANAGERS],
            'driver hapus' => ['DELETE', '/api/v1/drivers/{driver}', self::MANAGERS],

            'perawatan daftar' => ['GET', '/api/v1/maintenance', self::ALL],
            'perawatan detail' => ['GET', '/api/v1/maintenance/{maintenance}', self::ALL],
            'perawatan tambah' => ['POST', '/api/v1/maintenance', self::MANAGERS],
            'perawatan ubah' => ['PUT', '/api/v1/maintenance/{maintenance}', self::MANAGERS],
            'perawatan hapus' => ['DELETE', '/api/v1/maintenance/{maintenance}', self::MANAGERS],

            'pengeluaran daftar' => ['GET', '/api/v1/expenses', self::ALL],
            'pengeluaran detail' => ['GET', '/api/v1/expenses/{expense}', self::ALL],
            'pengeluaran tambah' => ['POST', '/api/v1/expenses', self::ALL],
            'pengeluaran ubah' => ['PUT', '/api/v1/expenses/{expense}', self::MANAGERS],
            'pengeluaran hapus' => ['DELETE', '/api/v1/expenses/{expense}', self::MANAGERS],

            'pemilik titipan daftar' => ['GET', '/api/v1/car-owners', self::MANAGERS],
            'pemilik titipan detail' => ['GET', '/api/v1/car-owners/{carowner}', self::MANAGERS],
            'pemilik titipan tambah' => ['POST', '/api/v1/car-owners', self::MANAGERS],
            'pemilik titipan ubah' => ['PUT', '/api/v1/car-owners/{carowner}', self::MANAGERS],
            'pemilik titipan pratinjau' => ['GET', '/api/v1/car-owners/{carowner}/settlement-preview?from=2026-10-01&to=2026-10-31', self::MANAGERS],
            'setoran daftar' => ['GET', '/api/v1/settlements', self::MANAGERS],
            'setoran detail' => ['GET', '/api/v1/settlements/{settlement}', self::MANAGERS],
            'setoran buat' => ['POST', '/api/v1/settlements', self::MANAGERS],
            'setoran pdf' => ['GET', '/api/v1/settlements/{settlement}/pdf', self::MANAGERS],
            'setoran print url' => ['POST', '/api/v1/settlements/{settlement}/print-url', self::MANAGERS],
            'setoran bayar' => ['POST', '/api/v1/settlements/{settlement}/pay', self::MANAGERS],
            'setoran batal' => ['DELETE', '/api/v1/settlements/{settlement}', self::MANAGERS],
            'pemilik titipan hapus' => ['DELETE', '/api/v1/car-owners/{carowner}', self::MANAGERS],

            'auth logout' => ['POST', '/api/v1/auth/logout', [...self::ALL, 'none']],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_role_matrix(string $method, string $uri, array $allowed): void
    {
        $url = preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) $this->ids[$m[1]], $uri);
        $denied = array_values(array_diff(['none', 'staff', 'admin', 'owner'], $allowed));

        // Tolak dulu (sebelum role berhak mungkin mengubah/menghapus data).
        foreach ($denied as $role) {
            $this->app['auth']->forgetGuards();
            $res = $this->actingAs($this->userFor($role))->json($method, $url);
            $this->assertSame(403, $res->getStatusCode(), "{$method} {$uri} harus DITOLAK untuk {$role}, dapat {$res->getStatusCode()}");
        }
        foreach ($allowed as $role) {
            $this->app['auth']->forgetGuards();
            $res = $this->actingAs($this->userFor($role))->json($method, $url);
            $this->assertNotSame(403, $res->getStatusCode(), "{$method} {$uri} harus DIIZINKAN untuk {$role}: ".$res->getContent());
            $this->assertLessThan(500, $res->getStatusCode(), "{$method} {$uri} error server untuk {$role}: ".mb_substr($res->getContent(), 0, 300));
        }
    }

    public function test_guest_is_rejected_everywhere(): void
    {
        foreach (self::endpoints() as $name => [$method, $uri]) {
            $url = preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) $this->ids[$m[1]], $uri);
            $this->app['auth']->forgetGuards();
            $this->json($method, $url)->assertStatus(401);
        }
    }

    public function test_every_authenticated_api_route_is_covered_by_matrix(): void
    {
        $covered = collect(self::endpoints())->map(fn ($e) => $e[0].' '.preg_replace(['/\?.*$/', '/\{\w+\}/', '/\/(ktp|serah_terima|0)(?=\/|$)/', '/\/payments\/export$/'], ['', '{}', '/{}', '/{}/export'], $e[1]))->unique();

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1') && in_array('auth:sanctum', $r->gatherMiddleware(), true))
            ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => $m === 'HEAD' || ($m === 'PATCH' && in_array('PUT', $r->methods(), true)))
                ->map(fn ($m) => $m.' /'.preg_replace('/\{\w+\}/', '{}', $r->uri())));

        $missing = $routes->diff($covered)->values()->all();
        $this->assertSame([], $missing, 'Endpoint tanpa uji matriks izin');
    }

    private function userFor(string $role): User
    {
        static $cache = [];
        $key = $role.spl_object_id($this->app);

        return $cache[$key] ??= $role === 'none'
            ? User::factory()->create()
            : User::factory()->withRole($role)->create();
    }
}
