<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Task\TaskAssigned;
use App\Notifications\Task\TaskCompleted;
use App\Notifications\Task\TaskUpdated;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Tasks\TaskStateResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso del módulo de tareas (docs/03, §4).
 */
class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly TaskStateResolver $stateResolver,
        private readonly AuditService $audit,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * @return Collection<int, Task>
     */
    public function forProject(Project $project): Collection
    {
        return $this->tasks->forProject($project);
    }

    /**
     * @return Collection<int, Task>
     */
    public function trashedForProject(Project $project): Collection
    {
        return $this->tasks->trashedForProject($project);
    }

    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function assignedTo(User $user): LengthAwarePaginator
    {
        return $this->tasks->paginateAssignedTo($user);
    }

    /**
     * @param  array<string, mixed>  $data  Datos validados.
     */
    public function create(Project $project, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $task = $this->tasks->create([
                ...$data,
                ...$this->resolveState(TaskStatus::Pendiente, 0, $data['due_date']),
                'project_id' => $project->id,
                'created_by' => $actor->id,
            ]);

            $this->audit->record('task.created', 'tareas', $task, [], Arr::except($task->getAttributes(), ['id', 'created_at', 'updated_at']), $actor);

            if ($task->assigned_to) {
                $this->audit->record('task.assigned', 'tareas', $task, [], ['assigned_to' => $task->assigned_to], $actor);
                $this->notifier->send($task->assignee, new TaskAssigned($task, $actor), $actor);
            }

            return $task;
        });
    }

    /**
     * Edición completa (líder): datos generales, responsable, estado y avance.
     *
     * @param  array<string, mixed>  $data  Datos validados.
     */
    public function update(Task $task, array $data, User $actor): Task
    {
        $state = $this->resolveState(
            TaskStatus::from($data['status']),
            (int) $data['progress'],
            $data['due_date'],
        );

        // Si cambia la fecha límite, el recordatorio de "próxima a vencer" debe poder enviarse de nuevo.
        if (! $task->due_date->isSameDay(Carbon::parse($data['due_date']))) {
            $data['due_reminder_sent_at'] = null;
        }

        return $this->persistChanges($task, [...$data, ...$state], $actor);
    }

    /**
     * Actualización de avance (responsable de la tarea o líder).
     */
    public function updateProgress(Task $task, TaskStatus $status, int $progress, User $actor): Task
    {
        return $this->persistChanges($task, $this->resolveState($status, $progress, $task->due_date), $actor);
    }

    /**
     * Eliminación lógica: la tarea deja de contar en el seguimiento, pero conserva su historial.
     */
    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor) {
            $this->tasks->delete($task);
            $this->audit->record('task.deleted', 'tareas', $task, ['title' => $task->title, 'status' => $task->status->value], [], $actor);
        });
    }

    /**
     * Restaura la tarea y recalcula su estado (p. ej. pudo vencer mientras estaba eliminada).
     */
    public function restore(Task $task, User $actor): Task
    {
        return DB::transaction(function () use ($task, $actor) {
            $this->tasks->restore($task);
            $this->audit->record('task.restored', 'tareas', $task, [], ['title' => $task->title], $actor);

            $requested = $task->status === TaskStatus::Vencida
                ? ($task->progress > 0 ? TaskStatus::EnProgreso : TaskStatus::Pendiente)
                : $task->status;

            return $this->persistChanges($task, $this->resolveState($requested, $task->progress, $task->due_date), $actor);
        });
    }

    /**
     * @return array{status: TaskStatus, progress: int, completed_at: ?Carbon}
     */
    private function resolveState(TaskStatus $status, int $progress, mixed $dueDate): array
    {
        $state = $this->stateResolver->resolve($status, $progress, Carbon::parse($dueDate), Carbon::today());

        return [...$state, 'completed_at' => $state['status'] === TaskStatus::Completada ? Carbon::now() : null];
    }

    /**
     * Guarda y audita solo lo que cambió, con acciones específicas para asignación y estado.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persistChanges(Task $task, array $attributes, User $actor): Task
    {
        // Una tarea que ya estaba completada conserva su fecha de finalización original.
        if ($task->status === TaskStatus::Completada && $attributes['status'] === TaskStatus::Completada) {
            unset($attributes['completed_at']);
        }

        return DB::transaction(function () use ($task, $attributes, $actor) {
            $original = $task->getRawOriginal();
            $this->tasks->update($task, $attributes);

            $changes = Arr::except($task->getChanges(), ['updated_at', 'completed_at']);
            if ($changes === []) {
                return $task;
            }

            $old = Arr::only($original, array_keys($changes));
            $this->audit->record('task.updated', 'tareas', $task, $old, $changes, $actor);

            if (array_key_exists('assigned_to', $changes)) {
                $this->audit->record('task.assigned', 'tareas', $task, ['assigned_to' => $old['assigned_to']], ['assigned_to' => $changes['assigned_to']], $actor);
            }

            if (array_key_exists('status', $changes)) {
                $this->audit->record('task.status_changed', 'tareas', $task, ['status' => $old['status']], ['status' => $changes['status']], $actor);
            }

            $this->notifyChanges($task, $changes, $actor);

            return $task;
        });
    }

    /**
     * Asignación → nuevo responsable (con correo). Otros cambios → responsable (solo interna).
     * Completada → líder del proyecto.
     *
     * @param  array<string, mixed>  $changes
     */
    private function notifyChanges(Task $task, array $changes, User $actor): void
    {
        $task->load(['assignee', 'project.leader']);

        if (array_key_exists('assigned_to', $changes) && $task->assignee) {
            $this->notifier->send($task->assignee, new TaskAssigned($task, $actor), $actor);
        } elseif ($task->assignee) {
            $this->notifier->send($task->assignee, new TaskUpdated($task, $actor), $actor);
        }

        if (($changes['status'] ?? null) === TaskStatus::Completada->value) {
            $this->notifier->send($task->project->leader, new TaskCompleted($task, $actor), $actor);
        }
    }
}
