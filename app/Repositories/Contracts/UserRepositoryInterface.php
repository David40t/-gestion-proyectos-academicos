<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function create(array $attributes): User;

    public function findOrFail(int $id): User;

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, User>
     */
    public function findMany(array $ids): Collection;

    /**
     * @return Collection<int, User>
     */
    public function withRole(string $role): Collection;

    /**
     * Usuarios con el rol indicado que aún no son integrantes del proyecto.
     *
     * @return Collection<int, User>
     */
    public function withRoleNotInProject(string $role, Project $project): Collection;
}
