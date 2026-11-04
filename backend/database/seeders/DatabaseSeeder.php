<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Wajib di semua environment.
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
        ]);

        // Hanya untuk lokal/staging: user demo + data fiktif.
        if (! app()->isProduction()) {
            $this->call([
                DemoUserSeeder::class,
                DemoDataSeeder::class,
            ]);
        }
    }
}
