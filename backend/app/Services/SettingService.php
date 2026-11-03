<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * @param  array<string, mixed>  $values
     */
    public function update(array $values, ?User $by = null): void
    {
        DB::transaction(function () use ($values, $by) {
            $settings = Setting::query()->whereIn('key', array_keys($values))->get()->keyBy('key');

            $unknown = array_diff(array_keys($values), $settings->keys()->all());
            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'settings' => 'Pengaturan tidak dikenal: '.implode(', ', $unknown),
                ]);
            }

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
            'string' => is_scalar($value) && mb_strlen((string) $value) <= 2000
                ? (string) $value
                : throw $error('Harus berupa teks maksimal 2000 karakter.'),
            default => is_array($value) ? $value : throw $error('Harus berupa daftar.'),
        };
    }
}
