<?php

namespace App\Policies;

use App\Models\CarOwner;
use App\Models\User;
use App\Support\Permissions;

class CarOwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CAR_OWNER_VIEW);
    }

    public function view(User $user, CarOwner $owner): bool
    {
        return $user->can(Permissions::CAR_OWNER_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CAR_OWNER_MANAGE);
    }

    public function update(User $user, CarOwner $owner): bool
    {
        return $user->can(Permissions::CAR_OWNER_MANAGE);
    }

    public function delete(User $user, CarOwner $owner): bool
    {
        return $user->can(Permissions::CAR_OWNER_MANAGE);
    }
}
