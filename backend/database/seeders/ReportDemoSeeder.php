<?php

namespace Database\Seeders;

use App\Enums\CarStatus;
use App\Enums\InspectionType;
use App\Enums\RentalStatus;
use App\Enums\ServiceType;
use App\Models\BookingRequest;
use App\Models\Car;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\MaintenanceRecord;
use App\Models\Rental;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\Rental\PaymentService;
use App\Services\Rental\PricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Riwayat transaksi FIKTIF & DETERMINISTIK (seed RNG tetap) selama N hari terakhir untuk
 * demo laporan/dashboard dan untuk test laporan. Hanya berjalan bila belum ada sewa.
 *
 * Isi: sewa selesai (sebagian terlambat, sebagian belum lunas), beberapa batal, DP/pelunasan
 * campuran metode, deposit masuk/kembali/dipotong, sesekali refund, catatan perawatan, dan
 * permintaan booking dari landing.
 */
class ReportDemoSeeder extends Seeder
{
    public int $days = 60;

    public function run(): void
    {
        if (Rental::query()->exists()) {
            return;
        }

        mt_srand(2026);
        $pricing = app(PricingService::class);
        $numbers = app(DocumentNumberService::class);
        $payments = app(PaymentService::class);
        $user = User::query()->first() ?? User::factory()->create(['name' => 'Seeder']);

        $cars = Car::query()->get();
        if ($cars->count() < 3) {
            $cars = $cars->merge(Car::factory()->count(3 - $cars->count())->create());
        }
        $customers = Customer::query()->where('risk_status', '!=', 'blacklist')->get();
        if ($customers->count() < 6) {
            $customers = $customers->merge(Customer::factory()->selfDriveReady()->count(6 - $customers->count())->create());
        }

        $methods = ['tunai', 'transfer', 'qris'];
        $start0 = now()->subDays($this->days)->setTime(2, 0); // 09.00 WIB

        foreach ($cars as $ci => $car) {
            $cursor = $start0->copy()->addHours(mt_rand(0, 72));
            while ($cursor->lt(now()->subDays(3))) {
                $start = $cursor->copy();
                $days = mt_rand(1, 4);
                $end = $start->copy()->addDays($days);
                $customer = $customers[mt_rand(0, $customers->count() - 1)];
                $service = mt_rand(1, 4) === 1 ? ServiceType::DenganDriver : ServiceType::LepasKunci;
                $quote = $pricing->quote($car, $service, $start, $end);
                $cancelled = mt_rand(1, 10) === 1;

                $late = [0, 0, 0, 0, 60, 300, 1560][mt_rand(0, 6)]; // menit
                $returned = $end->copy()->addMinutes($late);
                // Riwayat "selesai" harus sudah kembali di masa lalu (tidak ada pembayaran di masa depan).
                if ($returned->gt(now()->subHours(2))) {
                    break;
                }
                $lateFee = $cancelled ? null : $pricing->lateFee($end, $returned, $quote->baseRate);
                $extra = (! $cancelled && mt_rand(1, 6) === 1) ? 100000 : 0;
                $total = $quote->rentalAmount + ($lateFee?->total() ?? 0) + $extra;
                $deposit = (int) $car->default_deposit;

                $rental = Rental::create([
                    'code' => $numbers->next('TRX', $start),
                    'customer_id' => $customer->id,
                    'car_id' => $car->id,
                    'service_type' => $service,
                    'status' => $cancelled ? RentalStatus::Batal : RentalStatus::Selesai,
                    'start_at' => $start,
                    'planned_end_at' => $end,
                    'picked_up_at' => $cancelled ? null : $start,
                    'returned_at' => $cancelled ? null : $returned,
                    'daily_rate' => $quote->baseRate,
                    'billed_days' => $quote->billedDays,
                    'rental_amount' => $quote->rentalAmount,
                    'price_breakdown' => $quote->toArray(),
                    'late_fee' => $lateFee?->total() ?? 0,
                    'late_extra_days' => $lateFee?->extraDays ?? 0,
                    'extra_fees_total' => $extra,
                    'total_amount' => $cancelled ? $quote->rentalAmount : $total,
                    'deposit_required' => $cancelled ? 0 : $deposit,
                    'cancel_reason' => $cancelled ? 'Data contoh: pelanggan batal' : null,
                    'cancelled_at' => $cancelled ? $start->copy()->subDay() : null,
                    'created_by' => $user->id,
                    'created_at' => $start->copy()->subDay(),
                    'updated_at' => $returned,
                ]);

                if (! $cancelled) {
                    $rental->inspections()->create(['type' => InspectionType::SerahTerima, 'odometer' => $car->odometer, 'fuel_level' => 100, 'checklist' => [], 'inspected_at' => $start, 'inspected_by' => $user->id]);
                    $car->odometer += $days * mt_rand(80, 250);
                    $rental->inspections()->create(['type' => InspectionType::Kembali, 'odometer' => $car->odometer, 'fuel_level' => 75, 'checklist' => [], 'inspected_at' => $returned, 'inspected_by' => $user->id]);
                    if ($extra) {
                        $rental->fees()->create(['type' => 'kebersihan', 'amount' => $extra, 'reason' => 'Data contoh: interior kotor', 'created_by' => $user->id]);
                    }

                    $pay = fn (string $type, ?string $method, int $amount, Carbon $at) => $rental->payments()->create([
                        'type' => $type, 'method' => $method, 'amount' => $amount, 'paid_at' => $at, 'received_by' => $user->id,
                        'reference_number' => $method && $method !== 'tunai' ? 'DEMO-'.$rental->id.'-'.$type : null,
                    ]);

                    $dp = (int) (round($quote->rentalAmount * mt_rand(3, 5) / 10 / 1000) * 1000);
                    $pay('dp', $methods[mt_rand(0, 2)], $dp, $start->copy()->subDay());
                    if ($deposit > 0) {
                        $pay('deposit', 'tunai', $deposit, $start);
                    }
                    $applied = min($deposit, $total - $dp);
                    $applied = $late > 120 || $extra ? $applied : 0; // deposit dipotong bila ada denda/biaya
                    if ($applied > 0) {
                        $pay('deposit_potong', null, $applied, $returned);
                    }
                    $rest = $total - $dp - $applied;
                    $unpaid = mt_rand(1, 8) === 1; // sebagian jadi piutang
                    if ($rest > 0) {
                        $pay('pelunasan', $methods[mt_rand(0, 2)], $unpaid ? (int) floor($rest / 2 / 1000) * 1000 : $rest, $returned);
                    }
                    if ($deposit - $applied > 0) {
                        $pay('deposit_kembali', 'tunai', $deposit - $applied, $returned);
                    }
                    if (mt_rand(1, 15) === 1) {
                        $pay('refund', 'transfer', 50000, $returned->copy()->addHour()); // kompensasi contoh
                    }
                    $payments->recalculate($rental);
                } elseif (mt_rand(0, 1)) {
                    // Batal setelah DP lalu DP dikembalikan penuh.
                    $rental->payments()->create(['type' => 'dp', 'method' => 'transfer', 'amount' => 200000, 'paid_at' => $start->copy()->subDays(2), 'received_by' => $user->id, 'reference_number' => 'DEMO-BATAL']);
                    $rental->payments()->create(['type' => 'refund', 'method' => 'transfer', 'amount' => 200000, 'paid_at' => $start->copy()->subDay(), 'received_by' => $user->id, 'reference_number' => 'DEMO-REFUND']);
                    $payments->recalculate($rental);
                }

                $cursor = ($cancelled ? $start : $returned)->copy()->addHours(mt_rand(6, 96));
            }

            // Perawatan contoh
            MaintenanceRecord::create([
                'car_id' => $car->id, 'type' => 'servis_rutin', 'title' => 'Servis berkala (contoh)',
                'performed_at' => now()->subDays(20 + $ci * 7)->toDateString(), 'odometer' => $car->odometer - 500,
                'cost' => 650000 + $ci * 50000, 'vendor' => 'Bengkel Resmi', 'created_by' => $user->id,
            ]);
            if ($ci % 2 === 0) {
                MaintenanceRecord::create([
                    'car_id' => $car->id, 'type' => 'ban', 'title' => 'Ganti 2 ban (contoh)',
                    'performed_at' => now()->subDays(40 + $ci)->toDateString(), 'cost' => 1400000, 'vendor' => 'Toko Ban', 'created_by' => $user->id,
                ]);
            }
            $car->saveQuietly();
        }

        $this->renumberChronologically();

        // Pengeluaran contoh (deterministik, tanpa mt_rand agar urutan acak di atas tidak berubah).
        foreach ($cars->values() as $ci => $car) {
            for ($d = 3 + $ci; $d <= 58; $d += 9) {
                Expense::create([
                    'car_id' => $car->id, 'category' => 'bbm', 'title' => 'Isi BBM antar-jemput (contoh)', 'spent_at' => now()->subDays($d)->toDateString(),
                    'amount' => 150000 + (($d * 37 + $ci * 11) % 6) * 25000, 'method' => 'tunai', 'vendor' => 'SPBU Pajajaran', 'created_by' => $user->id,
                ]);
            }
            for ($d = 5 + $ci * 2; $d <= 58; $d += 14) {
                Expense::create([
                    'car_id' => $car->id, 'category' => 'cuci', 'title' => 'Cuci & vakum interior (contoh)', 'spent_at' => now()->subDays($d)->toDateString(),
                    'amount' => 75000, 'method' => 'tunai', 'vendor' => 'Cuci Mobil Kilat', 'created_by' => $user->id,
                ]);
            }
            Expense::create([
                'car_id' => $car->id, 'category' => 'asuransi', 'title' => 'Premi asuransi bulanan (contoh)', 'spent_at' => now()->subDays(15 + $ci)->toDateString(),
                'amount' => 350000 + $ci * 50000, 'method' => 'transfer', 'vendor' => 'Asuransi Contoh', 'created_by' => $user->id,
            ]);
        }
        foreach ([[1, 'gaji', 'Gaji staf admin (contoh)', 3500000], [2, 'sewa_tempat', 'Sewa garasi (contoh)', 1500000], [4, 'utilitas', 'Listrik & internet kantor (contoh)', 650000], [10, 'pemasaran', 'Iklan media sosial (contoh)', 400000]] as [$day, $cat, $title, $amount]) {
            foreach ([0, 30] as $shift) {
                Expense::create([
                    'car_id' => null, 'category' => $cat, 'title' => $title, 'spent_at' => now()->subDays($day + $shift)->toDateString(),
                    'amount' => $amount, 'method' => 'transfer', 'created_by' => $user->id,
                ]);
            }
        }

        // Pengingat jatuh tempo untuk dashboard.
        $first = $cars->first();
        $first->forceFill(['tax_due_date' => now()->addDays(12)->toDateString(), 'next_service_odometer' => $first->odometer + 600])->saveQuietly();
        if ($second = $cars->get(1)) {
            $second->forceFill(['stnk_due_date' => now()->subDays(3)->toDateString()])->saveQuietly();
        }

        // Permintaan booking dari landing.
        $published = $cars->firstWhere('status', CarStatus::Tersedia) ?? $cars->first();
        foreach ([
            ['Rina Kartika', '+6281234567890', 'baru', 3, 'Untuk ke Bandung, 2 hari.'],
            ['Andi Saputra', '+6281398765432', 'dihubungi', 5, 'Butuh kursi bayi.'],
            ['Yuni Wahyuni', '+6281354785421', 'baru', 8, 'Dengan driver ya.'],
            ['Budi Hartono', '+6285611112222', 'ditolak', 2, null],
        ] as [$name, $phone, $status, $inDays, $notes]) {
            BookingRequest::create([
                'name' => $name, 'phone' => $phone, 'car_id' => $published->id,
                'start_at' => now()->addDays($inDays)->setTime(2, 0), 'end_at' => now()->addDays($inDays + 2)->setTime(2, 0),
                'service_type' => str_contains((string) $notes, 'driver') ? 'dengan_driver' : 'lepas_kunci',
                'notes' => $notes, 'status' => $status,
                'rejection_reason' => $status === 'ditolak' ? 'Mobil sudah terpesan di tanggal tersebut.' : null,
                'handled_by' => $status === 'baru' ? null : $user->id, 'handled_at' => $status === 'baru' ? null : now()->subDay(),
                'ip_address' => '127.0.0.1',
            ]);
        }
    }

    /**
     * Sewa dibuat per mobil, jadi nomor nota belum urut tanggal. Urutkan ulang berdasarkan
     * waktu mulai (WIB, reset per tahun) dan sesuaikan counter document_sequences.
     */
    private function renumberChronologically(): void
    {
        $tz = config('app.display_timezone');
        $rentals = Rental::query()->orderBy('start_at')->orderBy('id')->get(['id', 'start_at']);
        // Langkah 1: kode sementara agar tidak bentrok dengan indeks unik saat menukar nomor.
        foreach ($rentals as $r) {
            Rental::query()->whereKey($r->id)->update(['code' => 'TMP-'.$r->id]);
        }

        $counters = [];
        foreach ($rentals as $r) {
            $year = (int) $r->start_at->copy()->setTimezone($tz)->format('Y');
            $counters[$year] = ($counters[$year] ?? 0) + 1;
            Rental::query()->whereKey($r->id)->update(['code' => sprintf('TRX-%d-%04d', $year, $counters[$year])]);
        }
        foreach ($counters as $year => $n) {
            DocumentSequence::query()->updateOrCreate(['prefix' => 'TRX', 'year' => $year], ['last_number' => $n]);
        }
    }
}
