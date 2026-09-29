<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;

class TaskUpdated extends AppNotification
{
    public function __construct(Task $task, User $actor)
    {
        parent::__construct(
            'tarea',
            "Tarea actualizada: {$task->title}",
            "{$actor->name} actualizó la tarea \"{$task->title}\" (estado: {$task->status->label()}, avance: {$task->progress}%).",
            route('projects.tasks.show', [$task->project_id, $task]),
        );
    }
}
