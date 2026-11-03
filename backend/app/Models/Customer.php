<?php

namespace App\Models;

use App\Enums\RiskStatus;
use App\Models\Concerns\SerializesDatesInDisplayTimezone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * NIK disimpan terenkripsi (nik_encrypted), dengan nik_hash (HMAC-SHA256 berbasis APP_KEY)
 * untuk pencarian/keunikan dan nik_last4 untuk tampilan ter-mask.
 * Set lewat atribut virtual `nik`: $customer->nik = '3201...'.
 */
class Customer extends Model
{
    use HasFactory, LogsActivity, SerializesDatesInDisplayTimezone, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'nik',
        'sim_number', 'sim_type', 'sim_expires_at',
        'address', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'ktp_photo_path', 'sim_photo_path',
        'risk_status', 'risk_note', 'risk_updated_at', 'risk_updated_by',
        'notes', 'created_by',
    ];

    protected $hidden = ['nik_encrypted', 'nik_hash', 'ktp_photo_path', 'sim_photo_path'];

    protected function casts(): array
    {
        return [
            'nik_encrypted' => 'encrypted',
            'sim_expires_at' => 'date',
            'risk_status' => RiskStatus::class,
            'risk_updated_at' => 'datetime',
        ];
    }

    public static function hashNik(string $nik): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $nik), (string) config('app.key'));
    }

    /** Atribut virtual: menulis NIK mengisi ciphertext, hash, dan 4 digit terakhir. */
    protected function nik(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nik_encrypted,
            set: function (?string $value) {
                $digits = preg_replace('/\D/', '', (string) $value);

                return [
                    'nik_encrypted' => $this->castAttributeAsEncryptedString('nik_encrypted', $digits),
                    'nik_hash' => static::hashNik($digits),
                    'nik_last4' => substr($digits, -4),
                ];
            },
        );
    }

    protected function nikMasked(): Attribute
    {
        return Attribute::get(fn () => $this->nik_last4 ? '•••• •••• •••• '.$this->nik_last4 : null);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function isBlacklisted(): bool
    {
        return $this->risk_status === RiskStatus::Blacklist;
    }

    public function getActivitylogOptions(): LogOptions
    {
        // NIK & dokumen tidak pernah masuk log.
        return LogOptions::defaults()
            ->logOnly(['name', 'phone', 'risk_status', 'risk_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customer');
    }
}
