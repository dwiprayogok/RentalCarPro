<?php

namespace App\Services\Import;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Import Excel/CSV untuk armada, pelanggan, dan driver.
 *
 * 1. preview(): baca file → ubah nilai sel (tanggal Excel, "Rp 450.000", Ya/Tidak, label pilihan)
 *    → validasi tiap baris memakai FormRequest yang SAMA dengan form biasa (normalisasi, unik,
 *    pesan Bahasa Indonesia) → simpan baris valid di cache 30 menit dengan token.
 * 2. commit(): validasi ulang (data bisa berubah sejak pratinjau) lalu simpan lewat service
 *    yang sama (log aktivitas, enkripsi NIK, slug) dalam satu transaksi.
 * Data yang sudah ada (plat / NIK / HP sama) dilewati, tidak ditimpa.
 */
class ImportService
{
    public const MAX_ROWS = 1000;

    public const TTL_MINUTES = 30;

    /** @var array<string, class-string<ImportDefinition>> */
    private const TYPES = ['armada' => CarImport::class, 'pelanggan' => CustomerImport::class, 'driver' => DriverImport::class];

    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    public function definition(string $type): ImportDefinition
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return app(self::TYPES[$type]);
    }

    public function authorize(User $user, ImportDefinition $def): void
    {
        if (! $user->can(Permissions::DATA_IMPORT) || ! $user->can($def->createPermission())) {
            throw new AuthorizationException('Anda tidak berwenang mengimpor data '.$def->label().'.');
        }
    }

    // ---------------------------------------------------------------- template

    public function template(ImportDefinition $def): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('RentalPro')->setTitle('Template import '.$def->label());
        $sheet = $book->getActiveSheet()->setTitle('Data');
        $cols = $def->columns();
        $last = self::MAX_ROWS + 1;

        foreach ($cols as $i => $c) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}1", $c->header());
            $style = $sheet->getStyle("{$col}1");
            $style->getFont()->setBold(true)->getColor()->setRGB($c->required ? 'FFFFFF' : '14262D');
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($c->required ? '0F7B63' : 'E7F4EF');
            $sheet->getColumnDimension($col)->setWidth(max(14, mb_strlen($c->header()) + 4));
            if ($c->hint !== '') {
                $sheet->getComment("{$col}1")->getText()->createTextRun($c->hint);
            }

            $range = "{$col}2:{$col}{$last}";
            match ($c->type) {
                // Teks: angka 0 di depan (HP) dan 16 digit NIK tidak berubah.
                'phone', 'digits', 'text' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('@'),
                'date' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('dd/mm/yyyy'),
                'money', 'int' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0'),
                default => null,
            };
            if ($c->options) {
                $v = $sheet->getCell("{$col}2")->getDataValidation();
                $v->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setAllowBlank(! $c->required)->setShowDropDown(true)->setShowErrorMessage(true)
                    ->setErrorTitle('Pilihan tidak valid')->setError('Pilih salah satu: '.implode(', ', array_keys($c->options)))
                    ->setFormula1('"'.implode(',', array_keys($c->options)).'"');
                $sheet->setDataValidation($range, $v);
            }
        }
        $sheet->freezePane('A2');

        // Sheet petunjuk
        $help = $book->createSheet()->setTitle('Petunjuk');
        $help->setCellValue('A1', 'Petunjuk import '.$def->label())->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $lines = [
            '1. Isi data mulai baris 2 di sheet "Data". Satu baris = satu '.$def->label().'. Jangan ubah judul kolom.',
            '2. Kolom bertanda * (hijau tua) wajib diisi. Maksimal '.self::MAX_ROWS.' baris per file.',
            '3. Tanggal ditulis dd/mm/yyyy. Nominal rupiah boleh "450000" atau "Rp 450.000".',
            '4. Data yang sudah ada (plat / NIK / nomor HP sama) akan dilewati, tidak ditimpa.',
            '5. Unggah file di menu Import Data, periksa pratinjau, lalu klik Import.',
        ];
        foreach ($lines as $i => $l) {
            $help->setCellValue('A'.($i + 3), $l);
        }
        $r = count($lines) + 4;
        foreach (['Kolom', 'Wajib', 'Keterangan', 'Contoh'] as $i => $h) {
            $cell = Coordinate::stringFromColumnIndex($i + 1).$r;
            $help->setCellValue($cell, $h)->getStyle($cell)->getFont()->setBold(true);
        }
        foreach ($cols as $i => $c) {
            $row = $r + 1 + $i;
            $help->setCellValue("A{$row}", $c->label);
            $help->setCellValue("B{$row}", $c->required ? 'Ya' : '');
            $help->setCellValue("C{$row}", trim($c->hint.($c->options ? ' Pilihan: '.implode(' / ', array_keys($c->options)).'.' : '')));
            $help->setCellValueExplicit("D{$row}", $c->example, DataType::TYPE_STRING);
        }
        foreach (['A' => 30, 'B' => 8, 'C' => 60, 'D' => 26] as $col => $w) {
            $help->getColumnDimension($col)->setWidth($w);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function writeTemplate(Spreadsheet $book): void
    {
        (new Xlsx($book))->save('php://output');
    }

    // ---------------------------------------------------------------- preview

    /** @return array<string, mixed> */
    public function preview(ImportDefinition $def, UploadedFile $file, User $user): array
    {
        $this->authorize($user, $def);
        [$rows, $headerErrors] = $this->read($def, $file);
        if ($headerErrors) {
            throw ValidationException::withMessages(['file' => $headerErrors]);
        }

        $seen = [];
        $results = [];
        $valid = [];
        foreach ($rows as $line => $input) {
            [$input, $preErrors] = $this->convert($def, $input);
            $res = ['row' => $line, 'summary' => $def->summary($input), 'status' => 'baru', 'message' => null, 'errors' => []];

            $key = $def->fileKey($input);
            if ($key && isset($seen[$key])) {
                $res['status'] = 'error';
                $res['errors'][] = ['field' => null, 'message' => "Duplikat dengan baris {$seen[$key]} ({$key})."];
            } elseif ($msg = $def->existing($input)) {
                $res['status'] = 'lewati';
                $res['message'] = $msg.' Dilewati.';
            } else {
                $errors = $preErrors + $this->validate($def, $input, $user)[1];
                if ($errors) {
                    $res['status'] = 'error';
                    $res['errors'] = $this->errorList($def, $errors);
                } else {
                    $valid[] = ['row' => $line, 'input' => $input];
                }
            }
            if ($key) {
                $seen[$key] ??= $line;
            }
            $results[] = $res;
        }

        $token = null;
        if ($valid) {
            $token = (string) Str::uuid();
            Cache::put("import:{$token}", [
                'user_id' => $user->id, 'type' => $def->key(), 'file' => mb_substr($file->getClientOriginalName(), 0, 120), 'rows' => $valid,
            ], now()->addMinutes(self::TTL_MINUTES));
        }

        $count = fn ($s) => count(array_filter($results, fn ($r) => $r['status'] === $s));

        return [
            'token' => $token,
            'expires_at' => $token ? now()->addMinutes(self::TTL_MINUTES)->setTimezone(config('app.display_timezone'))->toIso8601String() : null,
            'file' => $file->getClientOriginalName(),
            'summary' => ['total' => count($results), 'baru' => $count('baru'), 'lewati' => $count('lewati'), 'error' => $count('error')],
            'rows' => $results,
        ];
    }

    // ---------------------------------------------------------------- commit

    /** @return array{created: int, skipped: list<array{row: int, message: string}>} */
    public function commit(ImportDefinition $def, string $token, User $user): array
    {
        $this->authorize($user, $def);
        $data = Cache::get("import:{$token}");
        if (! $data || $data['user_id'] !== $user->id || $data['type'] !== $def->key()) {
            throw ValidationException::withMessages(['token' => 'Pratinjau sudah kedaluwarsa atau tidak ditemukan. Unggah ulang file Anda.']);
        }

        $result = DB::transaction(function () use ($def, $data, $user) {
            $created = 0;
            $skipped = [];
            foreach ($data['rows'] as $r) {
                // Validasi ulang: bisa saja data yang sama baru ditambahkan orang lain sejak pratinjau.
                if ($msg = $def->existing($r['input'])) {
                    $skipped[] = ['row' => $r['row'], 'message' => $msg];

                    continue;
                }
                [$validated, $errors] = $this->validate($def, $r['input'], $user);
                if ($errors) {
                    $skipped[] = ['row' => $r['row'], 'message' => collect($this->errorList($def, $errors))->pluck('message')->implode(' ')];

                    continue;
                }
                $def->create($validated, $user);
                $created++;
            }

            activity('import')->causedBy($user)->event('imported')
                ->withProperties(['type' => $def->key(), 'file' => $data['file'], 'created' => $created, 'skipped' => count($skipped)])
                ->log("Import {$created} {$def->label()} dari {$data['file']}");

            return ['created' => $created, 'skipped' => $skipped];
        });

        Cache::forget("import:{$token}");

        return $result;
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Baca sheet "Data" (atau sheet pertama). Kunci hasil = nomor baris Excel.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: list<string>}
     */
    private function read(ImportDefinition $def, UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $reader = IOFactory::createReader(match ($ext) {
            'csv', 'txt' => 'Csv', 'xls' => 'Xls', default => 'Xlsx'
        });
        if ($reader instanceof Csv) {
            $reader->setInputEncoding('UTF-8');
            $first = (string) (new \SplFileObject($file->getRealPath()))->fgets();
            $reader->setDelimiter(substr_count($first, ';') > substr_count($first, ',') ? ';' : ',');
        } else {
            $reader->setReadDataOnly(true);
        }

        try {
            $book = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            return [[], ['File tidak bisa dibaca. Pastikan formatnya .xlsx, .xls, atau .csv dan tidak rusak/terkunci kata sandi.']];
        }
        $sheet = $book->getSheetByName('Data') ?? $book->getSheet(0);
        $raw = $sheet->toArray(null, true, false, false);
        if (count($raw) < 2) {
            return [[], ['File kosong. Isi data mulai baris 2 di bawah judul kolom.']];
        }

        $norm = fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower(preg_replace('/\s*\*\s*$/', '', trim((string) $s))));
        $map = [];
        foreach ($raw[0] as $i => $h) {
            foreach ($def->columns() as $c) {
                if ($norm($h) !== '' && in_array($norm($h), [$norm($c->label), $norm($c->key)], true)) {
                    $map[$i] = $c->key;
                }
            }
        }
        $missing = collect($def->columns())->filter(fn ($c) => $c->required && ! in_array($c->key, $map, true))->map->label->values();
        if ($missing->isNotEmpty()) {
            return [[], ['Kolom wajib tidak ditemukan: '.$missing->implode(', ').'. Gunakan template dari tombol Unduh template.']];
        }

        $rows = [];
        foreach (array_slice($raw, 1, null, true) as $i => $cells) {
            $input = [];
            foreach ($map as $idx => $key) {
                $v = $cells[$idx] ?? null;
                $input[$key] = is_string($v) ? trim($v) : $v;
            }
            if (collect($input)->every(fn ($v) => $v === null || $v === '')) {
                continue;
            }
            $rows[$i + 1] = $input;
        }
        if (! $rows) {
            return [[], ['File kosong. Isi data mulai baris 2 di bawah judul kolom.']];
        }
        if (count($rows) > self::MAX_ROWS) {
            return [[], ['Maksimal '.self::MAX_ROWS.' baris per file. File ini berisi '.count($rows).' baris; pecah menjadi beberapa file.']];
        }

        return [$rows, []];
    }

    /**
     * Ubah nilai sel ke format input API.
     *
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function convert(ImportDefinition $def, array $input): array
    {
        $out = [];
        $errors = [];
        foreach ($def->columns() as $c) {
            $v = $input[$c->key] ?? null;
            $blank = $v === null || $v === '';
            if ($blank) {
                if ($c->default !== null) {
                    $out[$c->key] = $c->default;
                }

                continue;
            }
            $out[$c->key] = match ($c->type) {
                'int', 'money' => self::number($v),
                'date' => $this->date($v),
                'enum', 'bool' => $this->option($c, $v),
                'phone' => is_float($v) || is_int($v) ? sprintf('%.0f', $v) : (string) $v,
                'digits' => is_float($v) || is_int($v) ? sprintf('%.0f', $v) : (string) $v,
                default => is_float($v) && floor($v) === $v ? sprintf('%.0f', $v) : (string) $v,
            };
            // 16 digit NIK yang terbaca sebagai angka sudah kehilangan digit terakhir (presisi Excel 15 digit).
            if ($c->type === 'digits' && (is_float($v) || is_int($v)) && strlen(sprintf('%.0f', $v)) > 15) {
                $errors[$c->key] = [$c->label.' terbaca sebagai angka sehingga digit terakhir bisa berubah. Ubah format sel menjadi Teks lalu ketik ulang.'];
            }
        }

        return [$out, $errors];
    }

    /**
     * Angka dari sel. Teks memakai notasi Indonesia: "500.000" = lima ratus ribu, "Rp 1.250.000,00".
     * Notasi Inggris "1,250,000" dan desimal "450000.00" juga diterima. Selain itu dikembalikan
     * apa adanya agar validasi memberi pesan "harus berupa angka".
     */
    private static function number(mixed $v): mixed
    {
        if (is_int($v) || is_float($v)) {
            return (int) round($v);
        }
        $s = preg_replace('/^rp\.?\s*/i', '', str_replace(' ', '', trim((string) $v)));

        return match (true) {
            (bool) preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s) => (int) str_replace('.', '', explode(',', $s)[0]),
            (bool) preg_match('/^\d+(,\d+)?$/', $s) => (int) explode(',', $s)[0],
            (bool) preg_match('/^\d{1,3}(,\d{3})+$/', $s) => (int) str_replace(',', '', $s),
            (bool) preg_match('/^\d+\.\d{1,2}$/', $s) => (int) round((float) $s),
            default => $v,
        };
    }

    private function date(mixed $v): mixed
    {
        if (is_int($v) || is_float($v)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($v))->toDateString();
        }
        $s = trim((string) $v);
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $s, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : $s;
        }

        return $s;
    }

    private function option(Column $c, mixed $v): mixed
    {
        $s = mb_strtolower(trim((string) $v));
        foreach ($c->options as $label => $value) {
            if ($s === mb_strtolower($label) || $s === mb_strtolower((string) $value)) {
                return $value;
            }
        }
        if ($c->type === 'bool') {
            return match ($s) {
                'y', 'iya', 'true', 'aktif' => '1', 't', 'no', 'false', 'nonaktif' => '0', default => $v
            };
        }

        return $v;
    }

    /**
     * Validasi satu baris lewat FormRequest asli (prepareForValidation, rules, after, pesan).
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, list<string>>}
     */
    private function validate(ImportDefinition $def, array $input, User $user): array
    {
        $class = $def->requestClass();
        $req = $class::create('/import', 'POST', $input);
        $req->setContainer(app())->setRedirector(app(Redirector::class));
        $req->setUserResolver(fn () => $user);

        try {
            $req->validateResolved();

            return [$req->validated(), []];
        } catch (ValidationException $e) {
            return [null, $e->errors()];
        }
    }

    /** @return list<array{field: string|null, message: string}> */
    private function errorList(ImportDefinition $def, array $errors): array
    {
        $labels = collect($def->columns())->mapWithKeys(fn ($c) => [$c->key => $c->label]);
        $out = [];
        foreach ($errors as $field => $messages) {
            $out[] = ['field' => $labels[$field] ?? $field, 'message' => $messages[0]];
        }

        return $out;
    }
}
