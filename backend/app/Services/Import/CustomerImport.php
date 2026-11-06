<?php

namespace App\Services\Import;

use App\Http\Requests\Customer\SaveCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use App\Support\Permissions;
use App\Support\Phone;

final class CustomerImport implements ImportDefinition
{
    public function key(): string
    {
        return 'pelanggan';
    }

    public function label(): string
    {
        return 'pelanggan';
    }

    public function columns(): array
    {
        $sim = ['A', 'A Umum', 'B1', 'B1 Umum', 'B2', 'B2 Umum', 'C', 'D', 'Internasional'];

        return [
            new Column('name', 'Nama lengkap', required: true, example: 'Rudi Hartono'),
            new Column('phone', 'No. HP / WhatsApp', 'phone', true, hint: 'Format sel Teks agar angka 0 di depan tidak hilang', example: '081234567890'),
            new Column('nik', 'NIK (KTP)', 'digits', true, hint: '16 digit. Format sel Teks', example: '3201010101900001'),
            new Column('for_self_drive', 'Lepas kunci', 'bool', false, ['Ya' => '1', 'Tidak' => '0'], 'Ya = wajib isi SIM, alamat & kontak darurat', 'Ya', '0'),
            new Column('sim_number', 'No. SIM', hint: '10–20 karakter', example: '123456789012'),
            new Column('sim_type', 'Jenis SIM', 'enum', false, array_combine($sim, $sim), example: 'A'),
            new Column('sim_expires_at', 'Masa berlaku SIM', 'date', hint: 'Tanggal (dd/mm/yyyy)', example: '31/12/2028'),
            new Column('address', 'Alamat domisili', example: 'Jl. Contoh No. 5, Bogor'),
            new Column('emergency_contact_name', 'Kontak darurat (nama)', example: 'Sari'),
            new Column('emergency_contact_phone', 'Kontak darurat (HP)', 'phone', example: '081398765432'),
            new Column('emergency_contact_relation', 'Hubungan kontak darurat', example: 'Istri'),
            new Column('notes', 'Catatan', example: ''),
        ];
    }

    public function requestClass(): string
    {
        return SaveCustomerRequest::class;
    }

    public function createPermission(): string
    {
        return Permissions::CUSTOMER_CREATE;
    }

    private static function nik(?string $v): string
    {
        return preg_replace('/[\s.\-]/', '', (string) $v);
    }

    public function fileKey(array $input): ?string
    {
        return filled($input['nik'] ?? null) ? 'NIK '.self::nik($input['nik']) : (filled($input['phone'] ?? null) ? 'HP '.Phone::normalize($input['phone']) : null);
    }

    public function existing(array $input): ?string
    {
        $nik = self::nik($input['nik'] ?? '');
        if (strlen($nik) === 16 && ($c = Customer::withTrashed()->where('nik_hash', Customer::hashNik($nik))->first())) {
            return "NIK sudah terdaftar atas nama {$c->name}.";
        }
        $phone = Phone::normalize($input['phone'] ?? null);
        if ($phone && ($c = Customer::query()->where('phone', $phone)->first())) {
            return "Nomor HP sudah dipakai pelanggan {$c->name}.";
        }

        return null;
    }

    /** NIK tidak pernah ditampilkan utuh di pratinjau. */
    public function summary(array $input): string
    {
        $nik = self::nik($input['nik'] ?? '');

        return trim((string) ($input['name'] ?? '')).($nik !== '' ? ' · NIK ••••'.substr($nik, -4) : '');
    }

    public function create(array $validated, User $by): void
    {
        app(CustomerService::class)->create($validated, $by);
    }
}
