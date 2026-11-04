<?php

namespace App\Policies;

use App\Models\BookingRequest;
use App\Models\User;
use App\Support\Permissions;

class BookingRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::BOOKING_REQUEST_VIEW);
    }

    public function view(User $user, BookingRequest $request): bool
    {
        return $user->can(Permissions::BOOKING_REQUEST_VIEW);
    }

    public function update(User $user, BookingRequest $request): bool
    {
        return $user->can(Permissions::BOOKING_REQUEST_UPDATE);
    }

    /** "Jadikan Sewa": butuh izin konversi DAN izin membuat sewa. */
    public function convert(User $user, BookingRequest $request): bool
    {
        return $user->can(Permissions::BOOKING_REQUEST_CONVERT) && $user->can(Permissions::RENTAL_CREATE);
    }
}
