<?php

namespace App\Services\Auth;

use App\Models\User;

class PermissionService
{
    public function permissionsFor(User $user): array
    {
        return config('permissions.roles.'.$user->role, []);
    }

    public function has(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user), true);
    }

    public function hasAny(User $user, array $permissions): bool
    {
        return collect($permissions)
            ->contains(fn (string $permission) => $this->has($user, $permission));
    }
}
