<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use Illuminate\Support\Collection;

class ProjectMemberRepository implements ProjectMemberRepositoryInterface
{
    public function add(Project $project, User $user): void
    {
        $project->members()->attach($user->id);
    }

    public function remove(Project $project, User $user): void
    {
        $project->members()->detach($user->id);
    }

    public function isMember(Project $project, User $user): bool
    {
        return $project->members()->whereKey($user->id)->exists();
    }

    public function countProjectsOf(User $user): int
    {
        return $user->projects()->count();
    }

    public function membersOf(Project $project): Collection
    {
        return $project->members()->orderBy('name')->get(['users.id', 'users.name', 'users.email']);
    }
}
