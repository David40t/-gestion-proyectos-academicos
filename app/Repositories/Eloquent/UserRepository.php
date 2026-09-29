<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function findMany(array $ids): Collection
    {
        return User::findMany($ids);
    }

    public function withRole(string $role): Collection
    {
        return $this->queryWithRole($role)->get();
    }

    public function withRoleNotInProject(string $role, Project $project): Collection
    {
        return $this->queryWithRole($role)
            ->whereDoesntHave('projects', fn (Builder $query) => $query->whereKey($project->id))
            ->get();
    }

    /**
     * @return Builder<User>
     */
    private function queryWithRole(string $role): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', $role))
            ->orderBy('name')
            ->select(['id', 'name', 'email']);
    }
}
