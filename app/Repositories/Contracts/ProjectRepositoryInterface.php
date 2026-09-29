<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    public function hasTasks(Project $project): bool;
}
