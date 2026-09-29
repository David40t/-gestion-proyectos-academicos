<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;

/**
 * Asignación y retiro de roles, siempre auditados.
 */
class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditService $audit,
    ) {}

    public function assign(User $user, string $roleName, ?User $actor = null): void
    {
        if ($user->hasRole($roleName)) {
            return;
        }

        $this->roles->attachToUser($user, $this->roles->findByName($roleName));
        $user->flushRolesCache();

        $this->audit->record('role.assigned', 'roles', $user, [], ['role' => $roleName], $actor);
    }

    public function revoke(User $user, string $roleName, ?User $actor = null): void
    {
        if (! $user->hasRole($roleName)) {
            return;
        }

        $this->roles->detachFromUser($user, $this->roles->findByName($roleName));
        $user->flushRolesCache();

        $this->audit->record('role.revoked', 'roles', $user, ['role' => $roleName], [], $actor);
    }
}
