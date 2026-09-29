<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;

class RoleRepository implements RoleRepositoryInterface
{
    public function findByName(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    public function attachToUser(User $user, Role $role): void
    {
        $user->roles()->syncWithoutDetaching([$role->id => ['created_at' => now()]]);
    }

    public function detachFromUser(User $user, Role $role): void
    {
        $user->roles()->detach($role->id);
    }
}
