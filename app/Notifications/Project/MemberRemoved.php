<?php

namespace App\Notifications\Project;

use App\Models\Project;
use App\Models\User;
use App\Notifications\AppNotification;

class MemberRemoved extends AppNotification
{
    public function __construct(Project $project, User $actor)
    {
        parent::__construct(
            'proyecto',
            "Ya no participas en \"{$project->title}\"",
            "{$actor->name} te retiró del proyecto. Tus tareas pendientes quedaron sin responsable.",
            route('dashboard'), // ya no tiene acceso al proyecto
        );
    }

    protected function shouldMail(object $notifiable): bool
    {
        return true;
    }
}
