<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kelola pengguna (owner). Pengaman:
 * - owner tidak bisa menurunkan role / menonaktifkan dirinya sendiri;
 * - selalu tersisa minimal satu owner aktif.
 * Pengguna tidak dihapus (cukup dinonaktifkan) agar jejak audit tetap utuh.
 */
class UserService
{
    /**
     * @param  array{name: string, email: string, phone?: ?string, password: string, role: string}  $data
     */
    public function create(array $data, User $by): User
    {
        return DB::transaction(function () use ($data, $by) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->syncRoles([$data['role']]);
            $this->logRole($user, null, $data['role'], $by);

            return $user->load('roles');
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, role: string}  $data
     */
    public function update(User $user, array $data, User $by): User
    {
        return DB::transaction(function () use ($user, $data, $by) {
            $oldRole = $user->getRoleNames()->first();
            $newRole = $data['role'];

            if ($oldRole !== $newRole) {
                if ($user->is($by)) {
                    throw ValidationException::withMessages(['role' => 'Anda tidak bisa mengubah role akun Anda sendiri.']);
                }
                if ($oldRole === UserRole::Owner->value) {
                    $this->assertAnotherActiveOwner($user);
                }
            }

            $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null])->save();

            if ($oldRole !== $newRole) {
                $user->syncRoles([$newRole]);
                $this->logRole($user, $oldRole, $newRole, $by);
            }

            return $user->load('roles');
        });
    }

    public function setActive(User $user, bool $active, User $by): User
    {
        return DB::transaction(function () use ($user, $active, $by) {
            if (! $active) {
                if ($user->is($by)) {
                    throw ValidationException::withMessages(['is_active' => 'Anda tidak bisa menonaktifkan akun Anda sendiri.']);
                }
                if ($user->hasRole(UserRole::Owner->value)) {
                    $this->assertAnotherActiveOwner($user);
                }
            }

            $user->forceFill(['is_active' => $active])->save(); // tercatat oleh LogsActivity

            if (! $active) {
                $this->revokeAccess($user);
            }

            return $user->load('roles');
        });
    }

    /**
     * Paksa reset: kata sandi lama langsung tidak berlaku (diganti acak), semua sesi & token dicabut,
     * lalu email tautan atur ulang dikirim ke pengguna.
     */
    public function forcePasswordReset(User $user, User $by): void
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages(['user' => 'Aktifkan pengguna terlebih dahulu sebelum mengirim reset kata sandi.']);
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['password' => Hash::make(Str::random(48)), 'remember_token' => Str::random(60)])->saveQuietly();
            $this->revokeAccess($user);
        });

        Password::broker()->sendResetLink(['email' => $user->email]);

        activity('user')->performedOn($user)->causedBy($by)->event('password_reset_forced')
            ->log("Paksa reset kata sandi {$user->email}");
    }

    /** Cabut semua token API (mobile) & sesi web milik pengguna. */
    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function assertAnotherActiveOwner(User $exclude): void
    {
        $others = User::role(UserRole::Owner->value)->where('is_active', true)->whereKeyNot($exclude->id)->count();
        if ($others === 0) {
            throw ValidationException::withMessages(['role' => 'Harus ada minimal satu pemilik (owner) yang aktif.']);
        }
    }

    private function logRole(User $user, ?string $old, string $new, User $by): void
    {
        activity('user')->performedOn($user)->causedBy($by)->event('role_changed')
            ->withProperties(['old' => ['role' => $old], 'attributes' => ['role' => $new]])
            ->log($old ? "Ubah role {$user->email}: {$old} → {$new}" : "Buat pengguna {$user->email} sebagai {$new}");
    }
}
