<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\Task\TaskDueSoon;
use App\Notifications\Task\TaskOverdue;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Procesos diarios sobre fechas límite (los ejecuta el Scheduler, sin usuario autenticado):
 *  - marcar tareas vencidas y avisar al responsable y al líder;
 *  - recordar las tareas próximas a vencer (una sola vez por fecha límite).
 */
class TaskDeadlineService
{
    public const REMINDER_DAYS = 2;

    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly AuditService $audit,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * @return Collection<int, Task> Tareas marcadas como vencidas en esta ejecución.
     */
    public function markOverdueTasks(): Collection
    {
        $tasks = $this->tasks->pastDueOpen(Carbon::today());

        foreach ($tasks as $task) {
            DB::transaction(function () use ($task) {
                $previous = $task->status;
                $this->tasks->update($task, ['status' => TaskStatus::Vencida]);
                $this->audit->record('task.marked_overdue', 'tareas', $task, ['status' => $previous->value], ['status' => TaskStatus::Vencida->value]);

                $this->notifier->send([$task->assignee, $task->project->leader], new TaskOverdue($task));
            });
        }

        return $tasks;
    }

    /**
     * @return Collection<int, Task> Tareas recordadas en esta ejecución.
     */
    public function sendDueSoonReminders(): Collection
    {
        $tasks = $this->tasks->dueSoonWithoutReminder(Carbon::today(), Carbon::today()->addDays(self::REMINDER_DAYS));

        foreach ($tasks as $task) {
            DB::transaction(function () use ($task) {
                $this->tasks->update($task, ['due_reminder_sent_at' => Carbon::now()]);
                $this->notifier->send($task->assignee, new TaskDueSoon($task));
            });
        }

        return $tasks;
    }
}
