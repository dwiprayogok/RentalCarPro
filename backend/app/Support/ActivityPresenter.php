<?php

namespace App\Support;

use App\Models\BookingRequest;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Holiday;
use App\Models\Rental;
use App\Models\RentalFee;
use App\Models\RentalPayment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Mengubah baris activity_log menjadi kalimat yang mudah dibaca:
 * modul, aksi, objek (mis. "TRX-2026-0001", "Dwi Prayogo"), dan daftar perubahan lama → baru.
 */
final class ActivityPresenter
{
    public const MODULES = [
        'rental' => 'Transaksi', 'payment' => 'Pembayaran', 'rental_fee' => 'Biaya tambahan', 'customer' => 'Pelanggan',
        'car' => 'Armada', 'booking_request' => 'Permintaan booking', 'setting' => 'Pengaturan', 'user' => 'Pengguna',
    ];

    public const EVENTS = [
        'created' => 'Membuat', 'updated' => 'Mengubah', 'deleted' => 'Menghapus', 'restored' => 'Memulihkan',
        'returned' => 'Menyelesaikan (Kembali)', 'blacklist_override' => 'Pengecualian blacklist',
        'document_uploaded' => 'Unggah dokumen', 'document_deleted' => 'Hapus dokumen', 'role_changed' => 'Ubah role',
        'password_reset_forced' => 'Paksa reset kata sandi', 'status_changed' => 'Ubah status',
    ];

    private const FIELDS = [
        'status' => 'Status', 'start_at' => 'Mulai', 'planned_end_at' => 'Kembali rencana', 'returned_at' => 'Kembali aktual',
        'car_id' => 'Mobil', 'driver_id' => 'Driver', 'daily_rate' => 'Tarif harian', 'rental_amount' => 'Biaya sewa',
        'late_fee' => 'Denda', 'extra_fees_total' => 'Biaya tambahan', 'discount' => 'Diskon', 'discount_reason' => 'Alasan diskon',
        'total_amount' => 'Total tagihan', 'blacklist_override_by' => 'Pengecualian oleh', 'blacklist_override_reason' => 'Alasan pengecualian',
        'cancel_reason' => 'Alasan batal', 'name' => 'Nama', 'phone' => 'Nomor HP', 'email' => 'Email', 'is_active' => 'Aktif',
        'risk_status' => 'Status risiko', 'risk_note' => 'Catatan risiko', 'rate_self_drive' => 'Tarif lepas kunci',
        'rate_with_driver' => 'Tarif dengan driver', 'weekend_rate_self_drive' => 'Tarif akhir pekan (lepas kunci)',
        'weekend_rate_with_driver' => 'Tarif akhir pekan (driver)', 'odometer' => 'Odometer', 'is_published' => 'Tampil di landing',
        'plate_number' => 'Plat nomor', 'type' => 'Jenis', 'method' => 'Metode', 'amount' => 'Jumlah', 'paid_at' => 'Tanggal bayar',
        'reference_number' => 'Referensi', 'reason' => 'Alasan', 'rental_id' => 'Sewa', 'role' => 'Role', 'value' => 'Nilai',
        'date' => 'Tanggal', 'deposit_deduction' => 'Potong deposit', 'deposit_refund' => 'Deposit kembali',
        'payment_amount' => 'Pembayaran akhir', 'remaining' => 'Sisa tagihan', 'car_status' => 'Status mobil',
        'customer_flagged' => 'Pelanggan ditandai Perhatian', 'customer_id' => 'Pelanggan',
    ];

    private const MONEY = [
        'daily_rate', 'rental_amount', 'late_fee', 'extra_fees_total', 'discount', 'total_amount', 'rate_self_drive', 'rate_with_driver',
        'weekend_rate_self_drive', 'weekend_rate_with_driver', 'amount', 'deposit_deduction', 'deposit_refund', 'payment_amount', 'remaining',
    ];

    /** @return array<string, mixed> */
    public static function present(Activity $a): array
    {
        $tz = config('app.display_timezone');

        return [
            'id' => $a->id,
            'at' => $a->created_at?->copy()->setTimezone($tz)->toIso8601String(),
            'module' => $a->log_name,
            'module_label' => self::MODULES[$a->log_name] ?? ucfirst((string) $a->log_name),
            'event' => $a->event,
            'event_label' => self::EVENTS[$a->event] ?? ($a->event ? ucfirst($a->event) : 'Aktivitas'),
            'description' => $a->description,
            'causer' => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
            'subject' => self::subject($a),
            'changes' => self::changes($a),
        ];
    }

    /** @return array{type: string, id: ?int, label: string, link: ?string}|null */
    private static function subject(Activity $a): ?array
    {
        $s = $a->subject;
        $id = $a->subject_id;

        return match (true) {
            $s instanceof Rental => ['type' => 'rental', 'id' => $id, 'label' => $s->code, 'link' => "/transaksi?q={$s->code}"],
            $s instanceof RentalPayment => ['type' => 'payment', 'id' => $id, 'label' => "{$s->type->label()} · ".($s->rental?->code ?? "sewa #{$s->rental_id}"), 'link' => $s->rental ? "/transaksi?q={$s->rental->code}" : null],
            $s instanceof RentalFee => ['type' => 'fee', 'id' => $id, 'label' => "{$s->type->label()} · ".($s->rental?->code ?? ''), 'link' => null],
            $s instanceof Customer => ['type' => 'customer', 'id' => $id, 'label' => $s->name, 'link' => "/pelanggan?q={$s->phone}"],
            $s instanceof Car => ['type' => 'car', 'id' => $id, 'label' => "{$s->display_name} · {$s->plate_number}", 'link' => '/armada?q='.urlencode($s->plate_number)],
            $s instanceof Setting => ['type' => 'setting', 'id' => $id, 'label' => $s->label, 'link' => '/pengaturan'],
            $s instanceof Holiday => ['type' => 'holiday', 'id' => $id, 'label' => "Hari libur {$s->date->toDateString()}", 'link' => '/pengaturan'],
            $s instanceof User => ['type' => 'user', 'id' => $id, 'label' => "{$s->name} ({$s->email})", 'link' => '/pengguna'],
            $s instanceof BookingRequest => ['type' => 'booking_request', 'id' => $id, 'label' => "Permintaan {$s->name}", 'link' => '/permintaan-booking'],
            $a->subject_type !== null => ['type' => class_basename($a->subject_type), 'id' => $id, 'label' => class_basename($a->subject_type)." #{$id} (terhapus)", 'link' => null],
            default => null,
        };
    }

    /** @return list<array{field: string, label: string, old: ?string, new: ?string}> */
    private static function changes(Activity $a): array
    {
        $props = $a->properties?->toArray() ?? [];
        $new = $props['attributes'] ?? null;
        $old = $props['old'] ?? [];

        if ($new === null) {
            // Event khusus (mis. returned): tampilkan properti datar sebagai nilai baru.
            $new = collect($props)->except(['old'])->map(fn ($v) => is_array($v) ? ($v['total'] ?? null) : $v)->filter(fn ($v) => $v !== null)->all();
            if (isset($props['late']['total'])) {
                $new = ['late_fee' => $props['late']['total']] + $new;
                unset($new['late']);
            }
        }

        $out = [];
        foreach (array_unique(array_merge(array_keys($new), array_keys($old))) as $field) {
            $isSetting = $field === 'value' && $a->subject instanceof Setting;
            $label = $isSetting ? $a->subject->label : (self::FIELDS[$field] ?? $field);
            $o = $isSetting ? self::formatSetting($a->subject, $old[$field] ?? null) : self::format($field, $old[$field] ?? null);
            $n = $isSetting ? self::formatSetting($a->subject, $new[$field] ?? null) : self::format($field, $new[$field] ?? null);
            if ($o === $n) {
                continue;
            }
            $out[] = ['field' => $field, 'label' => $label, 'old' => $o, 'new' => $n];
        }

        return $out;
    }

    /** Nilai pengaturan sesuai jenisnya: rupiah, menit, ya/tidak, atau nama hari. */
    private static function formatSetting(Setting $setting, mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return match (true) {
            in_array($setting->key, ['late_fee_per_hour', 'default_deposit'], true) => 'Rp '.number_format((int) $v, 0, ',', '.'),
            str_ends_with($setting->key, '_minutes') => number_format((int) $v, 0, ',', '.').' menit',
            $setting->key === 'late_fee_cap_days' => $v.'× tarif harian',
            $setting->type === 'bool' => $v ? 'Ya' : 'Tidak',
            $setting->key === 'weekend_days' && is_array($v) => implode(', ', array_map(fn ($d) => ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'][(int) $d] ?? $d, $v)),
            default => self::format('value', $v),
        };
    }

    private static function format(string $field, mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_bool($v)) {
            return $v ? 'Ya' : 'Tidak';
        }
        if (in_array($field, self::MONEY, true) && is_numeric($v)) {
            return 'Rp '.number_format((int) $v, 0, ',', '.');
        }
        if (in_array($field, ['start_at', 'planned_end_at', 'returned_at', 'paid_at'], true) && is_string($v)) {
            try {
                return Carbon::parse($v)->setTimezone(config('app.display_timezone'))->locale('id')->translatedFormat('j M Y H.i').' WIB';
            } catch (\Throwable) {
                return $v;
            }
        }
        if (in_array($field, ['is_active', 'is_published', 'customer_flagged'], true)) {
            return $v ? 'Ya' : 'Tidak';
        }
        if (is_array($v)) {
            return implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v));
        }

        return mb_strimwidth((string) $v, 0, 200, '…');
    }
}
