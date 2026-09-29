<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectRepositoryInterface
{
    /**
     * Proyectos donde el usuario es integrante o docente responsable.
     *
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateVisibleTo(User $user, int $perPage = 10): LengthAwarePaginator;

    public function loadDetails(Project $project): Project;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Project;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Project $project, array $attributes): Project;

    public function delete(Project $project): void;

    public function countLedBy(User $user): int;

    /**
     * Proyectos donde el usuario es integrante, con estadísticas para el dashboard
     * (tasks_avg_progress, members_count, open_tasks_count, overdue_tasks_count).
     *
     * @return Collection<int, Project>
     */
    public function withStatsWhereMember(User $user): Collection;

    /**
     * @return Collection<int, Project>
     */
    public function withStatsLedBy(User $user): Collection;

    /**
     * @return Collection<int, Project>
     */
    public function withStatsSupervisedBy(User $user): Collection;

    /**
     * Ids de los proyectos supervisados por el docente (incluye eliminados, para consultar su historial).
     *
     * @return list<int>
     */
    public function idsSupervisedBy(User $user): array;

    /**
     * Proyectos (id, título) para listas de selección; null = todos. Incluye eliminados.
     *
     * @param  list<int>|null  $ids
     * @return Collection<int, Project>
     */
    public function options(?array $ids): Collection;

    public function hasTasks(Project $project): bool;
}
