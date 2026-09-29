<?php

namespace App\Notifications\Task;

use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;

class TaskCompleted extends AppNotification
{
    public function __construct(Task $task, User $actor)
    {
        parent::__construct(
            'tarea',
            "Tarea completada: {$task->title}",
            "{$actor->name} marcó como completada la tarea \"{$task->title}\".",
            route('projects.tasks.show', [$task->project_id, $task]),
        );
    }
}
