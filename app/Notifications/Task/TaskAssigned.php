<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;

class TaskAssigned extends AppNotification
{
    public function __construct(Task $task, User $actor)
    {
        parent::__construct(
            'tarea',
            "Nueva tarea asignada: {$task->title}",
            "{$actor->name} te asignó la tarea \"{$task->title}\" con fecha límite {$task->due_date->format('d/m/Y')}.",
            route('projects.tasks.show', [$task->project_id, $task]),
        );
    }

    protected function shouldMail(object $notifiable): bool
    {
        return true;
    }
}
