<?php

namespace App\Notifications\Project;

use App\Models\Project;
use App\Models\User;
use App\Notifications\AppNotification;

class ProjectLeaderChanged extends AppNotification
{
    private readonly int $newLeaderId;

    public function __construct(Project $project, User $newLeader, User $actor)
    {
        parent::__construct(
            'proyecto',
            "Nuevo líder en \"{$project->title}\"",
            "{$actor->name} designó a {$newLeader->name} como líder del proyecto.",
            route('projects.show', $project),
        );
        $this->newLeaderId = $newLeader->id;
    }

    /** El nuevo líder recibe además un correo: asume nuevas responsabilidades. */
    protected function shouldMail(object $notifiable): bool
    {
        return $notifiable->getKey() === $this->newLeaderId;
    }
}
