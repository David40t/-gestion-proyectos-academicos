<?php

namespace App\Notifications\Project;

use App\Models\Project;
use App\Models\User;
use App\Notifications\AppNotification;

class ProjectUpdated extends AppNotification
{
    /**
     * @param  list<string>  $changedLabels  Nombres legibles de los campos modificados.
     */
    public function __construct(Project $project, array $changedLabels, User $actor)
    {
        parent::__construct(
            'proyecto',
            "Cambios en \"{$project->title}\"",
            "{$actor->name} modificó: ".implode(', ', $changedLabels).'.',
            route('projects.show', $project),
        );
    }
}
