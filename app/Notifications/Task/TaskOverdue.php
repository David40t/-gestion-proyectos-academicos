<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Notifications\AppNotification;

class TaskOverdue extends AppNotification
{
    public function __construct(Task $task)
    {
        parent::__construct(
            'recordatorio',
            "Tarea vencida: {$task->title}",
            "La tarea \"{$task->title}\" venció el {$task->due_date->format('d/m/Y')} con {$task->progress}% de avance.",
            route('projects.tasks.show', [$task->project_id, $task]),
        );
    }

    protected function shouldMail(object $notifiable): bool
    {
        return true;
    }
}
