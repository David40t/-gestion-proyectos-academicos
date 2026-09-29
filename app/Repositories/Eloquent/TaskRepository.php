<?php

namespace App\Repositories\Eloquent;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TaskRepository implements TaskRepositoryInterface
{
    public function create(array $attributes): Task
    {
        return Task::create($attributes);
    }

    public function update(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function restore(Task $task): void
    {
        $task->restore();
    }

    public function trashedForProject(Project $project): Collection
    {
        return $project->tasks()->onlyTrashed()->latest('deleted_at')->get();
    }

    public function forProject(Project $project): Collection
    {
        return $project->tasks()
            ->with('assignee:id,name')
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [TaskStatus::Completada->value])
            ->orderBy('due_date')
            ->get();
    }

    public function paginateAssignedTo(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->assignedTasks()
            ->whereHas('project') // excluye proyectos eliminados (soft delete)
            ->with('project:id,title')
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [TaskStatus::Completada->value])
            ->orderBy('due_date')
            ->paginate($perPage);
    }

    public function countByStatus(Project $project): array
    {
        return $project->tasks()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function averageProgress(Project $project): float
    {
        return (float) $project->tasks()->avg('progress');
    }

    public function upcoming(Project $project, CarbonInterface $until): Collection
    {
        return $project->tasks()
            ->with('assignee:id,name')
            ->whereIn('status', [TaskStatus::Pendiente, TaskStatus::EnProgreso])
            ->whereBetween('due_date', [now()->toDateString(), $until->toDateString()])
            ->orderBy('due_date')
            ->get();
    }

    public function pastDueOpen(CarbonInterface $today): Collection
    {
        return Task::query()
            ->whereHas('project')
            ->whereIn('status', [TaskStatus::Pendiente, TaskStatus::EnProgreso])
            ->whereDate('due_date', '<', $today->toDateString())
            ->get();
    }

    public function unassignOpenTasks(Project $project, User $user): int
    {
        return $project->tasks()
            ->where('assigned_to', $user->id)
            ->whereIn('status', TaskStatus::open())
            ->update(['assigned_to' => null]);
    }
}
