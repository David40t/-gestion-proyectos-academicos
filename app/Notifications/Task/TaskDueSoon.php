<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Notifications\AppNotification;

class TaskDueSoon extends AppNotification
{
    public function __construct(Task $task)
    {
        parent::__construct(
            'recordatorio',
            "Fecha límite próxima: {$task->title}",
            "La tarea \"{$task->title}\" vence el {$task->due_date->format('d/m/Y')} y lleva {$task->progress}% de avance.",
            route('projects.tasks.show', [$task->project_id, $task]),
        );
    }

    protected function shouldMail(object $notifiable): bool
    {
        return true;
    }
}
