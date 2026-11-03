<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SETTING_VIEW);
    }

    /** Ubah tarif/denda/buffer: hanya owner. */
    public function update(User $user, ?Setting $setting = null): bool
    {
        return $user->can(Permissions::SETTING_UPDATE);
    }
}
