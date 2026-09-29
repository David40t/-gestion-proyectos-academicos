<?php

namespace App\Notifications\Comment;

use App\Models\Comment;
use App\Models\Project;
use App\Notifications\AppNotification;

class CommentPosted extends AppNotification
{
    private readonly bool $isObservation;

    public function __construct(Comment $comment, Project $project)
    {
        $author = $comment->author;
        $where = $comment->task ? "la tarea \"{$comment->task->title}\"" : "el proyecto \"{$project->title}\"";

        parent::__construct(
            'comentario',
            $comment->is_observation ? "Nueva observación docente en \"{$project->title}\"" : "Nuevo comentario en \"{$project->title}\"",
            "{$author->name} comentó en {$where}: \"".str($comment->body)->limit(140).'"',
            ($comment->task ? route('projects.tasks.show', [$project, $comment->task]) : route('projects.show', $project))
                .'#comentario-'.$comment->id,
        );
        $this->isObservation = $comment->is_observation;
    }

    /** Las observaciones formales del docente son críticas: también por correo. */
    protected function shouldMail(object $notifiable): bool
    {
        return $this->isObservation;
    }
}
