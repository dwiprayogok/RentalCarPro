<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use App\Support\SettingDefinitions;
use Illuminate\Database\Seeder;

/**
 * Membuat pengaturan default yang belum ada. Nilai yang sudah diubah owner TIDAK ditimpa;
 * hanya label/deskripsi/grup yang diperbarui.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingDefinitions::all() as $def) {
            $setting = Setting::query()->firstOrNew(['key' => $def['key']]);
            $isNew = ! $setting->exists;

            $setting->fill([
                'group' => $def['group'],
                'type' => $def['type'],
                'label' => $def['label'],
                'description' => $def['description'],
                'is_public' => $def['is_public'] ?? false,
            ]);

            if ($isNew) {
                $setting->value = $def['value'];
            }

            $setting->saveQuietly();
        }

        app(SettingService::class)->flush();
    }
}
