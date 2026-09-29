<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;

/**
 * Consulta de roles y permisos del usuario (ADR-004).
 *
 * Los permisos se cargan una sola vez por instancia (una consulta por request)
 * para que las múltiples verificaciones de Gate/Policies no repitan consultas.
 */
trait HasRoles
{
    /** @var Collection<int, string>|null */
    private ?Collection $cachedPermissionNames = null;

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissionNames()->contains($permission);
    }

    /**
     * Acceso global de administración: las Policies lo aceptan en lugar de la relación
     * con el registro (ser líder, integrante o docente). No omite reglas de negocio (ADR-018).
     */
    public function hasGlobalAccess(): bool
    {
        return $this->hasPermission('sistema.administrar');
    }

    /**
     * Unión de los permisos de todos los roles del usuario.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        return $this->cachedPermissionNames ??= $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap->permissions
            ->pluck('name')
            ->unique()
            ->values();
    }

    /**
     * Debe llamarse tras asignar o retirar roles.
     */
    public function flushRolesCache(): void
    {
        $this->cachedPermissionNames = null;
        $this->unsetRelation('roles');
    }
}
