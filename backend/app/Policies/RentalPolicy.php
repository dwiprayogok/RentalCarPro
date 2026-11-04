<?php

namespace App\Policies;

use App\Models\Rental;
use App\Models\User;
use App\Support\Permissions;

class RentalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::RENTAL_VIEW);
    }

    public function view(User $user, Rental $rental): bool
    {
        return $user->can(Permissions::RENTAL_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::RENTAL_CREATE);
    }

    public function handover(User $user, Rental $rental): bool
    {
        return $user->can(Permissions::RENTAL_HANDOVER);
    }

    public function return(User $user, Rental $rental): bool
    {
        return $user->can(Permissions::RENTAL_RETURN);
    }

    public function cancel(User $user, Rental $rental): bool
    {
        return $user->can(Permissions::RENTAL_CANCEL);
    }

    public function addPayment(User $user, Rental $rental): bool
    {
        return $user->can(Permissions::PAYMENT_CREATE);
    }

    public function deletePayment(User $user, Rental $rental): bool
    {
        return $user->can(Permissions::PAYMENT_DELETE);
    }

    public function export(User $user): bool
    {
        return $user->can(Permissions::RENTAL_VIEW);
    }
}
