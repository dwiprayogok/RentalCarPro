<?php

namespace Tests\Feature\Imports;

use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Services\Import\CarImport;
use App\Services\Import\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-06 03:00:00', 'UTC'));
        $this->owner = $this->userWithRole('owner');
    }

    /**
     * Buat .xlsx dari template asli lalu isi baris data.
     * Nilai string dengan awalan "=" ditulis apa adanya; nilai numerik ditulis sebagai angka.
     *
     * @param  list<list<mixed>>  $rows
     */
    private function xlsx(string $type, array $rows, string $name = 'data.xlsx'): UploadedFile
    {
        $book = app(ImportService::class)->template(app(ImportService::class)->definition($type));
        $sheet = $book->getSheetByName('Data');
        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $v) {
                $coord = [$c + 1, $r + 2];
                if (is_string($v)) {
                    $sheet->setCellValueExplicit($coord, $v, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coord, $v);
                }
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_template_has_headers_dropdowns_and_guide(): void
    {
        $res = $this->actingAs($this->owner)->get('/api/v1/imports/armada/template')->assertOk();
        $this->assertStringContainsString('template-import-armada.xlsx', $res->headers->get('Content-Disposition'));
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $res->streamedContent());

        $book = IOFactory::load($path);
        $data = $book->getSheetByName('Data');
        $headers = array_map(fn ($c) => $c->header(), (new CarImport)->columns());
        $this->assertSame($headers, $data->rangeToArray('A1:'.$data->getHighestColumn().'1')[0]);
        $this->assertSame('Plat nomor *', $data->getCell('C1')->getValue());
        $this->assertSame('"Matic,Manual"', $data->getCell('D2')->getDataValidation()->getFormula1());
        $this->assertNotNull($book->getSheetByName('Petunjuk'));

        $this->actingAs($this->userWithRole('staff'))->get('/api/v1/imports/pelanggan/template')->assertForbidden();
        $this->actingAs($this->owner)->get('/api/v1/imports/supir/template')->assertNotFound();
    }

    public function test_car_import_preview_and_commit(): void
    {
        Car::factory()->create(['plate_number' => 'F 1000 AA']);
        $file = $this->xlsx('armada', [
            // merk, tipe, plat, transmisi, tahun, warna, kursi, bbm, tarif LK, tarif driver, akhir pekan LK, akhir pekan driver, deposit, odometer, status, pajak, stnk, catatan
            ['Toyota', 'Avanza', 'f 2222  bb', 'matic', 2022, 'Putih', 7, 'Bensin', 'Rp 450.000', 650000, '', '', '500.000', '35.000', '', ExcelDate::PHPToExcel(new \DateTime('2027-08-31')), '31/12/2027', 'Unit baru'],
            ['Honda', 'Brio', 'B 3333 CC', 'Manual', 2021, '', '', '', 350000, 300000],              // tarif driver < LK
            ['Honda', 'Jazz', 'XYZ', 'Otomatis', 2020, '', 5, '', 400000, 600000],                   // plat & transmisi salah
            ['Daihatsu', 'Xenia', 'F 2222 BB', 'Manual', 2019, '', 7, '', 300000, 500000],           // duplikat baris 2
            ['Suzuki', 'Ertiga', 'F 1000 AA', 'Manual', 2019, '', 7, '', 300000, 500000],            // sudah ada di DB
            [],                                                                                        // baris kosong diabaikan
            ['Mitsubishi', 'Xpander', 'F 4444 DD', 'Matic', 2023, 'Hitam', 7, 'Bensin', 550000, 750000, 600000, 800000],
        ]);

        $p = $this->actingAs($this->owner)->post('/api/v1/imports/armada/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();
        $p->assertJsonPath('data.summary', ['total' => 6, 'baru' => 2, 'lewati' => 1, 'error' => 3]);
        $rows = collect($p->json('data.rows'))->keyBy('row');
        $this->assertSame('baru', $rows[2]['status']);
        $this->assertSame('Toyota Avanza · F 2222 BB', $rows[2]['summary']);
        $this->assertStringContainsString('tidak boleh lebih kecil', $rows[3]['errors'][0]['message']);
        $this->assertEqualsCanonicalizing(['Plat nomor', 'Transmisi'], array_column($rows[4]['errors'], 'field'));
        $this->assertSame('Duplikat dengan baris 2 (plat F 2222 BB).', $rows[5]['errors'][0]['message']);
        $this->assertSame('lewati', $rows[6]['status']);
        $this->assertStringContainsString('Plat F 1000 AA sudah terdaftar', $rows[6]['message']);
        $this->assertSame('baru', $rows[8]['status'], 'baris kosong (7) dilewati tanpa pesan');

        $c = $this->actingAs($this->owner)->postJson('/api/v1/imports/armada/commit', ['token' => $p->json('data.token')])->assertOk()
            ->assertJsonPath('data.created', 2)->assertJsonPath('message', '2 armada berhasil diimpor.');

        $car = Car::where('plate_number', 'F 2222 BB')->firstOrFail();
        $this->assertSame([450000, 650000, 500000, 35000, 'tersedia', 'matic', '2027-08-31', '2027-12-31'], [
            $car->rate_self_drive, $car->rate_with_driver, $car->default_deposit, $car->odometer, $car->status->value, $car->transmission->value,
            $car->tax_due_date->toDateString(), $car->stnk_due_date->toDateString(),
        ]);
        $this->assertSame($this->owner->id, $car->created_by);
        $this->assertNotEmpty($car->slug);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'import', 'event' => 'imported', 'causer_id' => $this->owner->id, 'description' => 'Import 2 armada dari data.xlsx']);
        $log = $this->actingAs($this->owner)->getJson('/api/v1/activity-log?module=import')->assertOk();
        $log->assertJsonPath('data.0.module_label', 'Import data')->assertJsonPath('data.0.event_label', 'Import')
            ->assertJsonPath('data.0.subject.label', 'Import 2 armada dari data.xlsx');
        $this->assertContains('Berhasil diimpor', array_column($log->json('data.0.changes'), 'label'));

        // Token sekali pakai.
        $this->actingAs($this->owner)->postJson('/api/v1/imports/armada/commit', ['token' => $p->json('data.token')])
            ->assertStatus(422)->assertJsonPath('errors.token.0', 'Pratinjau sudah kedaluwarsa atau tidak ditemukan. Unggah ulang file Anda.');
    }

    public function test_commit_revalidates_and_token_is_bound_to_user_and_type(): void
    {
        $file = $this->xlsx('armada', [
            ['Toyota', 'Rush', 'F 5555 EE', 'Matic', 2022, '', 7, '', 450000, 650000],
            ['Toyota', 'Raize', 'F 6666 FF', 'Matic', 2023, '', 5, '', 400000, 600000],
        ]);
        $token = $this->actingAs($this->owner)->post('/api/v1/imports/armada/preview', ['file' => $file], ['Accept' => 'application/json'])->json('data.token');

        $this->actingAs($this->userWithRole('admin'))->postJson('/api/v1/imports/armada/commit', ['token' => $token])->assertStatus(422);
        $this->actingAs($this->owner)->postJson('/api/v1/imports/pelanggan/commit', ['token' => $token])->assertStatus(422);

        // Sejak pratinjau, plat F 5555 EE ditambahkan orang lain → dilewati saat commit.
        Car::factory()->create(['plate_number' => 'F 5555 EE']);
        $this->actingAs($this->owner)->postJson('/api/v1/imports/armada/commit', ['token' => $token])->assertOk()
            ->assertJsonPath('data.created', 1)->assertJsonPath('data.skipped.0.row', 2)
            ->assertJsonPath('message', '1 armada berhasil diimpor, 1 dilewati.');
    }

    public function test_customer_import_protects_nik(): void
    {
        Customer::factory()->create(['name' => 'Lama', 'nik' => '3201010101900009', 'phone' => '+6281200000001']);
        $file = $this->xlsx('pelanggan', [
            ['Rudi Hartono', '081234567890', '3201010101900001', 'Ya', '123456789012', 'A', '31/12/2028', 'Jl. Contoh 5', 'Sari', '081398765432', 'Istri'],
            ['Tanpa SIM', '081234567891', '3201010101900002', 'Ya'],                                         // lepas kunci tanpa SIM
            ['Angka NIK', '081234567892', 3201010101900003.0],                                                  // NIK terbaca angka
            ['Sudah Ada', '081299999999', '3201010101900009'],                                                  // NIK terdaftar
            ['Harian', 81234567893, '3201010101900004', 'Tidak'],                                               // HP tanpa 0 (angka)
        ]);
        $p = $this->actingAs($this->owner)->post('/api/v1/imports/pelanggan/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk();
        $p->assertJsonPath('data.summary', ['total' => 5, 'baru' => 2, 'lewati' => 1, 'error' => 2]);
        $rows = collect($p->json('data.rows'))->keyBy('row');
        $this->assertSame('Rudi Hartono · NIK ••••0001', $rows[2]['summary']);
        $this->assertContains('No. SIM', array_column($rows[3]['errors'], 'field'));
        $this->assertStringContainsString('Ubah format sel menjadi Teks', $rows[4]['errors'][0]['message']);
        $this->assertSame('lewati', $rows[5]['status']);
        // NIK lengkap tidak pernah dikirim di pratinjau.
        $this->assertDoesNotMatchRegularExpression('/\d{16}/', $p->getContent());

        $this->actingAs($this->owner)->postJson('/api/v1/imports/pelanggan/commit', ['token' => $p->json('data.token')])->assertOk()->assertJsonPath('data.created', 2);
        $rudi = Customer::where('name', 'Rudi Hartono')->firstOrFail();
        $this->assertSame('+6281234567890', $rudi->phone);
        $this->assertSame(Customer::hashNik('3201010101900001'), $rudi->nik_hash);
        $this->assertDatabaseMissing('customers', ['nik_encrypted' => '3201010101900001']);
        $this->assertSame('+6281234567893', Customer::where('name', 'Harian')->value('phone'));
    }

    public function test_driver_csv_semicolon_and_file_errors(): void
    {
        Driver::factory()->create(['phone' => '+6281311110000']);
        $csv = "Nama driver *;No. HP *;No. SIM;Masa berlaku SIM;Honor harian;Aktif;Catatan\n"
            ."Budi;0813-1111-2222;987654321012;30/06/2028;Rp 200.000;Ya;\n"
            ."Andi;081311110000;;;150000;Tidak;\n"
            ."Cecep;0813;;;;;\n";
        $file = UploadedFile::fake()->createWithContent('driver.csv', $csv);
        $p = $this->actingAs($this->owner)->post('/api/v1/imports/driver/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.summary', ['total' => 3, 'baru' => 1, 'lewati' => 1, 'error' => 1]);
        $this->actingAs($this->owner)->postJson('/api/v1/imports/driver/commit', ['token' => $p->json('data.token')])->assertOk();
        $budi = Driver::where('name', 'Budi')->firstOrFail();
        $this->assertSame(['+6281311112222', 200000, '2028-06-30', true], [$budi->phone, $budi->daily_fee, $budi->license_expires_at->toDateString(), $budi->is_active]);

        // Kolom wajib hilang.
        $bad = UploadedFile::fake()->createWithContent('x.csv', "Nama;Telepon\nBudi;0812\n");
        $this->actingAs($this->owner)->post('/api/v1/imports/driver/preview', ['file' => $bad], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'Kolom wajib tidak ditemukan: Nama driver, No. HP. Gunakan template dari tombol Unduh template.');

        // Lebih dari 1000 baris.
        $big = "Nama driver;No. HP\n".str_repeat("Budi;081234567890\n", 1001);
        $this->actingAs($this->owner)->post('/api/v1/imports/driver/preview', ['file' => UploadedFile::fake()->createWithContent('big.csv', $big)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'Maksimal 1000 baris per file. File ini berisi 1001 baris; pecah menjadi beberapa file.');

        // Bukan Excel/CSV.
        $this->actingAs($this->owner)->post('/api/v1/imports/driver/preview', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.file.0', 'File harus berformat Excel (.xlsx/.xls) atau CSV.');
    }

    public function test_number_parsing_indonesian_and_english_notation(): void
    {
        $parse = fn ($v) => (new \ReflectionMethod(ImportService::class, 'number'))->invoke(null, $v);
        foreach ([
            ['500.000', 500000], ['Rp 1.250.000', 1250000], ['Rp1.250.000,00', 1250000], ['450000', 450000], ['450000,50', 450000],
            ['1,250,000', 1250000], ['450000.00', 450000], [450000.0, 450000], [35000, 35000], ['  Rp. 75.000 ', 75000],
            ['lima ratus', 'lima ratus'], ['12.5.6', '12.5.6'],
        ] as [$in, $out]) {
            $this->assertSame($out, $parse($in), 'input: '.var_export($in, true));
        }
    }
}
