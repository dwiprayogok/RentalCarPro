<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Role, permission, dan pengaturan default selalu tersedia di setiap test. */
    protected bool $seed = true;

    protected string $seeder = BaseDataSeeder::class;

    protected const SPA_ORIGIN = 'http://localhost:5173';

    /** Header yang membuat Sanctum memperlakukan request sebagai Admin Web (SPA stateful). */
    protected function fromSpa(): static
    {
        return $this->withHeaders([
            'Origin' => self::SPA_ORIGIN,
            'Referer' => self::SPA_ORIGIN.'/',
            'Accept' => 'application/json',
        ]);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }
}

/** @internal */
class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
    }
}
