<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use App\Models\User;

interface RoleRepositoryInterface
{
    public function findByName(string $name): Role;

    public function attachToUser(User $user, Role $role): void;

    public function detachFromUser(User $user, Role $role): void;
}
