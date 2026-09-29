<?php

namespace App\Notifications\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Notifications\AppNotification;

class ProjectStatusChanged extends AppNotification
{
    private readonly bool $isClosing;

    public function __construct(Project $project, ProjectStatus $from, ProjectStatus $to, User $actor)
    {
        parent::__construct(
            'proyecto',
            "Proyecto \"{$project->title}\": {$to->label()}",
            "{$actor->name} cambió el estado del proyecto de \"{$from->label()}\" a \"{$to->label()}\".",
            route('projects.show', $project),
        );
        $this->isClosing = $to->isFinal();
    }

    /** Solo el cierre (finalizado / cancelado) es crítico. */
    protected function shouldMail(object $notifiable): bool
    {
        return $this->isClosing;
    }
}
