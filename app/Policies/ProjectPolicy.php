<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;

/**
 * Autorización por registro (docs/03, §1): cada regla combina
 * el permiso del rol con la relación del usuario con ESTE proyecto.
 * El acceso global (administrador) reemplaza solo la relación, nunca el permiso ni el estado (ADR-018).
 */
class ProjectPolicy
{
    public function __construct(private readonly ProjectMemberRepositoryInterface $members) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('proyecto.ver');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('proyecto.ver')
            && ($user->hasGlobalAccess() || $project->isSupervisedBy($user) || $this->members->isMember($project, $user));
    }

    /**
     * Quien crea un proyecto queda como su líder, y el líder es un estudiante (ADR-006).
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('proyecto.crear') && $user->hasRole(Role::ESTUDIANTE);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('proyecto.editar')
            && $this->manages($user, $project)
            && ! $project->status->isFinal();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('proyecto.eliminar') && $this->manages($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasPermission('proyecto.gestionar_integrantes')
            && $this->manages($user, $project)
            && ! $project->status->isFinal();
    }

    /**
     * El docente decide sobre proyectos en revisión (finalizar o devolver);
     * el líder gestiona el resto del ciclo, pero no puede finalizar; el administrador, cualquier transición.
     * La validez de la transición en sí la verifica ProjectService.
     */
    public function changeStatus(User $user, Project $project, ProjectStatus $to): bool
    {
        if (! $user->hasPermission('proyecto.cambiar_estado')) {
            return false;
        }

        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($project->isSupervisedBy($user)) {
            return $project->status === ProjectStatus::EnRevision;
        }

        return $project->isLedBy($user) && $to !== ProjectStatus::Finalizado;
    }

    /**
     * Líder de este proyecto, o administrador con acceso global.
     */
    private function manages(User $user, Project $project): bool
    {
        return $user->hasGlobalAccess() || $project->isLedBy($user);
    }
}
