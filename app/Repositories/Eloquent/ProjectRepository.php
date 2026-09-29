<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function paginateVisibleTo(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Project::query()
            ->where(fn (Builder $query) => $query
                ->where('teacher_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id)))
            ->with(['leader:id,name', 'teacher:id,name'])
            ->withCount('members')
            ->withAvg('tasks', 'progress')
            ->latest()
            ->paginate($perPage);
    }

    public function loadDetails(Project $project): Project
    {
        return $project
            ->load(['leader:id,name,email', 'teacher:id,name,email', 'members' => fn ($query) => $query->orderBy('name')])
            ->loadAvg('tasks', 'progress');
    }

    public function create(array $attributes): Project
    {
        return Project::create($attributes);
    }

    public function update(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $project;
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function countLedBy(User $user): int
    {
        return Project::where('leader_id', $user->id)->count();
    }

    public function idsSupervisedBy(User $user): array
    {
        return Project::withTrashed()->where('teacher_id', $user->id)->pluck('id')->all();
    }

    public function options(?array $ids): Collection
    {
        return Project::withTrashed()
            ->when($ids !== null, fn (Builder $query) => $query->whereKey($ids))
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    public function hasTasks(Project $project): bool
    {
        return $project->tasks()->exists();
    }
}
