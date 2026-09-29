<?php

namespace App\Repositories\Eloquent;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

    public function findInProject(Project $project, int $taskId): ?Task
    {
        return $project->tasks()->find($taskId);
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
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', $until->toDateString())
            ->orderBy('due_date')
            ->get();
    }

    public function pastDueOpen(CarbonInterface $today): Collection
    {
        return Task::query()
            ->with(['assignee', 'project.leader'])
            ->whereHas('project')
            ->whereIn('status', [TaskStatus::Pendiente, TaskStatus::EnProgreso])
            ->whereDate('due_date', '<', $today->toDateString())
            ->get();
    }

    public function deadlineStats(?User $assignee, ?array $projectIds, CarbonInterface $until): array
    {
        $open = [TaskStatus::Pendiente->value, TaskStatus::EnProgreso->value];

        // Una sola consulta con agregados condicionales (DATE() es portable entre MySQL y SQLite).
        $row = $this->scoped($assignee, $projectIds)
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending', $open)
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) AND DATE(due_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_soon',
                [...$open, now()->toDateString(), $until->toDateString()])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as overdue', [TaskStatus::Vencida->value])
            ->toBase()
            ->first();

        return [
            'pending' => (int) $row->pending,
            'due_soon' => (int) $row->due_soon,
            'overdue' => (int) $row->overdue,
        ];
    }

    public function nextOpen(?User $assignee, ?array $projectIds, int $limit = 6): Collection
    {
        return $this->scoped($assignee, $projectIds)
            ->with(['project:id,title', 'assignee:id,name'])
            ->whereIn('status', TaskStatus::open())
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Tareas de un responsable y/o de un conjunto de proyectos, excluyendo proyectos eliminados.
     *
     * @param  list<int>|null  $projectIds
     * @return Builder<Task>
     */
    private function scoped(?User $assignee, ?array $projectIds): Builder
    {
        return Task::query()
            ->whereHas('project')
            ->when($assignee, fn (Builder $query) => $query->where('assigned_to', $assignee->id))
            ->when($projectIds !== null, fn (Builder $query) => $query->whereIn('project_id', $projectIds));
    }

    public function dueSoonWithoutReminder(CarbonInterface $from, CarbonInterface $until): Collection
    {
        return Task::query()
            ->with('assignee')
            ->whereHas('project')
            ->whereNotNull('assigned_to')
            ->whereNull('due_reminder_sent_at')
            ->whereIn('status', [TaskStatus::Pendiente, TaskStatus::EnProgreso])
            ->whereDate('due_date', '>=', $from->toDateString())
            ->whereDate('due_date', '<=', $until->toDateString())
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
