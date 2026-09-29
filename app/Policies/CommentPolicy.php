<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;

class CommentPolicy
{
    public function __construct(private readonly ProjectMemberRepositoryInterface $members) {}

    public function viewAny(User $user, Project $project): bool
    {
        return $user->hasPermission('comentario.ver') && $this->participates($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission('comentario.crear') && $this->participates($user, $project);
    }

    /**
     * Observación formal: solo el docente responsable de ESTE proyecto.
     */
    public function markObservation(User $user, Project $project): bool
    {
        return $user->hasPermission('comentario.crear') && $project->isSupervisedBy($user);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->hasPermission('comentario.editar') && $comment->isAuthoredBy($user);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->hasPermission('comentario.eliminar') && $comment->isAuthoredBy($user);
    }

    private function participates(User $user, Project $project): bool
    {
        return $project->isSupervisedBy($user) || $this->members->isMember($project, $user);
    }
}
