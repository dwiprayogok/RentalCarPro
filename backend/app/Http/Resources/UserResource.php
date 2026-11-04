<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames()->values(),
            'role' => $this->getRoleNames()->first(),
            'role_label' => UserRole::tryFrom((string) $this->getRoleNames()->first())?->label(),
            'is_self' => $request->user()?->is($this->resource) ?? false,
            'permissions' => $this->when(
                $this->resource->relationLoaded('permissions') || $request->routeIs('v1.auth.*'),
                fn () => $this->getAllPermissions()->pluck('name')->sort()->values(),
            ),
            'last_login_at' => $this->last_login_at?->setTimezone(config('app.display_timezone'))->toIso8601String(),
            'created_at' => $this->created_at?->setTimezone(config('app.display_timezone'))->toIso8601String(),
        ];
    }
}
