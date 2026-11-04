<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;
use App\Support\Permissions;

class CarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CAR_VIEW);
    }

    public function view(User $user, Car $car): bool
    {
        return $user->can(Permissions::CAR_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CAR_CREATE);
    }

    public function update(User $user, Car $car): bool
    {
        return $user->can(Permissions::CAR_UPDATE);
    }

    public function delete(User $user, Car $car): bool
    {
        return $user->can(Permissions::CAR_DELETE);
    }

    /** Toggle "Tampilkan di landing". */
    public function publish(User $user, ?Car $car = null): bool
    {
        return $user->can(Permissions::CAR_PUBLISH);
    }

    /** Kelola galeri foto (upload, hapus, sampul). */
    public function managePhotos(User $user, Car $car): bool
    {
        return $user->can(Permissions::CAR_UPDATE);
    }
}
