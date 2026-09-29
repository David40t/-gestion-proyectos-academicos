<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;

/**
 * Autorización por registro (docs/03, §1): cada regla combina
 * el permiso del rol con la relación del usuario con ESTE proyecto.
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
            && ($project->isSupervisedBy($user) || $this->members->isMember($project, $user));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('proyecto.crear');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('proyecto.editar')
            && $project->isLedBy($user)
            && ! $project->status->isFinal();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('proyecto.eliminar') && $project->isLedBy($user);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasPermission('proyecto.gestionar_integrantes')
            && $project->isLedBy($user)
            && ! $project->status->isFinal();
    }

    /**
     * El docente decide sobre proyectos en revisión (finalizar o devolver);
     * el líder gestiona el resto del ciclo, pero no puede finalizar.
     * La validez de la transición en sí la verifica ProjectService.
     */
    public function changeStatus(User $user, Project $project, ProjectStatus $to): bool
    {
        if (! $user->hasPermission('proyecto.cambiar_estado')) {
            return false;
        }

        if ($project->isSupervisedBy($user)) {
            return $project->status === ProjectStatus::EnRevision;
        }

        return $project->isLedBy($user) && $to !== ProjectStatus::Finalizado;
    }
}
