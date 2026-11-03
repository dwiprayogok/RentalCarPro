<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * User demo untuk tiap role. Kata sandi: "password123". JANGAN dijalankan di produksi.
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public function run(): void
    {
        $users = [
            ['name' => 'Owner Demo', 'email' => 'owner@rentalpro.test', 'phone' => '+6281200000001', 'role' => UserRole::Owner],
            ['name' => 'Admin Demo', 'email' => 'admin@rentalpro.test', 'phone' => '+6281200000002', 'role' => UserRole::Admin],
            ['name' => 'Staf Demo', 'email' => 'staff@rentalpro.test', 'phone' => '+6281200000003', 'role' => UserRole::Staff],
        ];

        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'phone' => $data['phone'], 'password' => self::PASSWORD, 'is_active' => true],
            );
            $user->syncRoles([$data['role']->value]);
        }
    }
}
