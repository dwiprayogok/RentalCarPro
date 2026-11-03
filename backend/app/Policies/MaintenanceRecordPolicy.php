<?php

namespace App\Policies;

use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Support\Permissions;

class MaintenanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::MAINTENANCE_VIEW);
    }

    public function view(User $user, MaintenanceRecord $record): bool
    {
        return $user->can(Permissions::MAINTENANCE_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MAINTENANCE_CREATE);
    }

    public function update(User $user, MaintenanceRecord $record): bool
    {
        return $user->can(Permissions::MAINTENANCE_UPDATE);
    }

    public function delete(User $user, MaintenanceRecord $record): bool
    {
        return $user->can(Permissions::MAINTENANCE_DELETE);
    }
}
