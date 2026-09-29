<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;

interface TaskRepositoryInterface
{
    /**
     * Deja sin responsable las tareas no completadas del usuario en el proyecto.
     *
     * @return int Cantidad de tareas afectadas.
     */
    public function unassignOpenTasks(Project $project, User $user): int;
}
