<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\Phone;
use App\Support\SettingDefinitions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Akses pengaturan bertipe dengan cache. Semua logika bisnis membaca angka
 * (toleransi, denda, buffer) dari sini, bukan dari konstanta.
 */
class SettingService
{
    private const CACHE_KEY = 'settings.all';

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
            ->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * Simpan beberapa pengaturan sekaligus. Validasi memakai SettingDefinitions::rules()
     * (pesan Bahasa Indonesia dengan label pengaturan), lalu nilai dipaksa ke tipenya.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values, ?User $by = null): void
    {
        $known = Setting::query()->pluck('label', 'key');
        $unknown = array_diff(array_keys($values), $known->keys()->all());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['settings' => 'Pengaturan tidak dikenal: '.implode(', ', $unknown)]);
        }

        if (array_key_exists('business_whatsapp', $values) && filled($values['business_whatsapp'])) {
            $values['business_whatsapp'] = Phone::normalize((string) $values['business_whatsapp']);
        }

        $rules = [];
        $attributes = [];
        foreach (SettingDefinitions::rules() as $key => $r) {
            $base = str_contains($key, '.') ? strstr($key, '.', true) : $key;
            if (array_key_exists($base, $values)) {
                $rules["settings.{$key}"] = array_merge(str_contains($key, '.') ? [] : ['present'], $r);
                $attributes["settings.{$key}"] = mb_strtolower($known[$base] ?? $base);
            }
        }
        Validator::make(['settings' => $values], $rules, [], $attributes)->validate();

        DB::transaction(function () use ($values, $by) {
            $settings = Setting::query()->whereIn('key', array_keys($values))->get()->keyBy('key');
            foreach ($values as $key => $value) {
                $setting = $settings[$key];
                $setting->value = $this->coerce($setting->type, $value, $key);
                $setting->updated_by = $by?->id;
                $setting->save();
            }
        });

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function coerce(string $type, mixed $value, string $key): mixed
    {
        $error = fn (string $msg) => ValidationException::withMessages(["settings.$key" => $msg]);

        return match ($type) {
            'int' => ! is_bool($value) && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false
                ? (int) $value
                : throw $error('Harus berupa bilangan bulat tidak negatif.'),
            'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true)
                ? (bool) $value
                : throw $error('Harus bernilai ya atau tidak.'),
            'string' => $value === null || is_scalar($value)
                ? trim((string) $value)
                : throw $error('Harus berupa teks.'),
            default => is_array($value)
                ? ($key === 'weekend_days' ? array_values(array_map('intval', array_unique($value))) : array_values($value))
                : throw $error('Harus berupa daftar.'),
        };
    }
}
