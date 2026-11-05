<?php

namespace App\Policies;

use App\Models\OwnerSettlement;
use App\Models\User;
use App\Support\Permissions;

class OwnerSettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SETTLEMENT_VIEW);
    }

    public function view(User $user, OwnerSettlement $settlement): bool
    {
        return $user->can(Permissions::SETTLEMENT_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::SETTLEMENT_CREATE);
    }

    /** Aturan "sudah dibayar" ditegakkan di SettlementService (owner lolos Gate::before). */
    public function pay(User $user, OwnerSettlement $settlement): bool
    {
        return $user->can(Permissions::SETTLEMENT_PAY);
    }

    public function delete(User $user, OwnerSettlement $settlement): bool
    {
        return $user->can(Permissions::SETTLEMENT_DELETE);
    }
}
