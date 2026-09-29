<?php

namespace App\Repositories\Contracts;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

interface CommentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Comment;

    public function update(Comment $comment, string $body): Comment;

    public function delete(Comment $comment): void;

    /**
     * Comentarios generales del proyecto (no asociados a una tarea).
     *
     * @return Collection<int, Comment>
     */
    public function forProject(Project $project): Collection;

    /**
     * @return Collection<int, Comment>
     */
    public function forTask(Task $task): Collection;
}
