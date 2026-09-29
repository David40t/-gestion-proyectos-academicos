<?php

namespace App\Notifications\Project;

use App\Models\Project;
use App\Models\User;
use App\Notifications\AppNotification;

class MemberAdded extends AppNotification
{
    public function __construct(Project $project, User $actor)
    {
        parent::__construct(
            'proyecto',
            "Te agregaron al proyecto \"{$project->title}\"",
            "{$actor->name} te agregó como integrante del proyecto.",
            route('projects.show', $project),
        );
    }

    protected function shouldMail(object $notifiable): bool
    {
        return true;
    }
}
