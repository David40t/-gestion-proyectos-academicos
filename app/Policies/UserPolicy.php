<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestión de usuarios y roles (módulo de administración).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('rol.gestionar');
    }

    public function updateRoles(User $user, User $target): bool
    {
        return $user->hasPermission('rol.gestionar');
    }
}
