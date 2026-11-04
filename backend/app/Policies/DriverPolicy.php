<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;
use App\Support\Permissions;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::DRIVER_VIEW);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->can(Permissions::DRIVER_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::DRIVER_CREATE);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->can(Permissions::DRIVER_UPDATE);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->can(Permissions::DRIVER_DELETE);
    }
}
