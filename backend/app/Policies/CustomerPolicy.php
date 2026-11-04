<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\Permissions;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::CUSTOMER_VIEW);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMER_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::CUSTOMER_CREATE);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMER_UPDATE);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMER_DELETE);
    }

    /** Ubah status risiko (lancar/perhatian/blacklist). */
    public function changeRisk(User $user, ?Customer $customer = null): bool
    {
        return $user->can(Permissions::CUSTOMER_CHANGE_RISK);
    }

    /** Lihat & unggah foto KTP/SIM. */
    public function viewDocuments(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMER_VIEW_DOCUMENTS);
    }

    public function manageDocuments(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMER_UPDATE) && $user->can(Permissions::CUSTOMER_VIEW_DOCUMENTS);
    }

    public function viewRentals(User $user, Customer $customer): bool
    {
        return $user->can(Permissions::CUSTOMER_VIEW) && $user->can(Permissions::RENTAL_VIEW);
    }
}
