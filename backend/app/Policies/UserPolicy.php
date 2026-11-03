<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::USER_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::USER_MANAGE);
    }

    public function update(User $user, User $target): bool
    {
        return $user->can(Permissions::USER_MANAGE);
    }
}
