<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TaskRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Task;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Task $task, array $attributes): Task;

    /**
     * @return Collection<int, Task>
     */
    public function forProject(Project $project): Collection;

    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginateAssignedTo(User $user, int $perPage = 15): LengthAwarePaginator;

    /**
     * Cantidad de tareas del proyecto por estado: ['pendiente' => 2, ...].
     *
     * @return array<string, int>
     */
    public function countByStatus(Project $project): array;

    public function averageProgress(Project $project): float;

    /**
     * Tareas no completadas del proyecto con fecha límite entre hoy y $until.
     *
     * @return Collection<int, Task>
     */
    public function upcoming(Project $project, CarbonInterface $until): Collection;

    /**
     * Tareas abiertas (pendientes o en progreso) cuya fecha límite ya pasó.
     *
     * @return Collection<int, Task>
     */
    public function pastDueOpen(CarbonInterface $today): Collection;

    /**
     * Deja sin responsable las tareas no completadas del usuario en el proyecto.
     *
     * @return int Cantidad de tareas afectadas.
     */
    public function unassignOpenTasks(Project $project, User $user): int;
}
