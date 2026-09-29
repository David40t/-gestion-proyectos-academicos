<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;

/**
 * Autorización por registro para tareas: permiso del rol + relación con el proyecto/tarea.
 * En proyectos finalizados o cancelados las tareas quedan en solo lectura.
 */
class TaskPolicy
{
    public function __construct(private readonly ProjectMemberRepositoryInterface $members) {}

    /**
     * Listado "Mis tareas".
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tarea.ver');
    }

    public function view(User $user, Task $task): bool
    {
        $project = $task->project;

        return $user->hasPermission('tarea.ver')
            && ($project->isSupervisedBy($user) || $this->members->isMember($project, $user));
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission('tarea.crear') && $this->isOpenAndLedBy($project, $user);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->hasPermission('tarea.editar') && $this->isOpenAndLedBy($task->project, $user);
    }

    public function assign(User $user, Project $project): bool
    {
        return $user->hasPermission('tarea.asignar') && $this->isOpenAndLedBy($project, $user);
    }

    /**
     * El responsable actualiza el avance de su tarea; el líder, el de cualquiera del proyecto.
     */
    public function updateProgress(User $user, Task $task): bool
    {
        return $user->hasPermission('tarea.cambiar_estado')
            && ! $task->project->status->isFinal()
            && ($task->assigned_to === $user->id || $task->project->isLedBy($user));
    }

    private function isOpenAndLedBy(Project $project, User $user): bool
    {
        return $project->isLedBy($user) && ! $project->status->isFinal();
    }
}
