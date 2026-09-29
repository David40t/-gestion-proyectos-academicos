<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comentarios de proyectos y tareas; las observaciones son comentarios formales del docente (docs/03, §6).
 */
class CommentService
{
    public function __construct(
        private readonly CommentRepositoryInterface $comments,
        private readonly TaskRepositoryInterface $tasks,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return Collection<int, Comment>
     */
    public function forProject(Project $project): Collection
    {
        return $this->comments->forProject($project);
    }

    /**
     * @return Collection<int, Comment>
     */
    public function forTask(Task $task): Collection
    {
        return $this->comments->forTask($task);
    }

    /**
     * @param  array{body: string, task_id?: int|string|null, is_observation?: bool}  $data  Datos validados.
     */
    public function create(Project $project, array $data, User $author): Comment
    {
        $task = $this->resolveTask($project, $data['task_id'] ?? null);
        $isObservation = (bool) ($data['is_observation'] ?? false);

        if ($isObservation && ! $project->isSupervisedBy($author)) {
            throw new BusinessRuleException('Solo el docente responsable del proyecto puede registrar observaciones.');
        }

        return DB::transaction(function () use ($project, $task, $data, $isObservation, $author) {
            $comment = $this->comments->create([
                'project_id' => $project->id,
                'task_id' => $task?->id,
                'user_id' => $author->id,
                'body' => $data['body'],
                'is_observation' => $isObservation,
            ]);

            $this->audit->record('comment.created', 'comentarios', $comment, [], [
                'project_id' => $project->id,
                'task_id' => $task?->id,
                'is_observation' => $isObservation,
                'body' => $comment->body,
            ], $author);

            return $comment;
        });
    }

    public function update(Comment $comment, string $body, User $actor): Comment
    {
        if ($comment->body === $body) {
            return $comment;
        }

        return DB::transaction(function () use ($comment, $body, $actor) {
            $previous = $comment->body;
            $this->comments->update($comment, $body);
            $this->audit->record('comment.updated', 'comentarios', $comment, ['body' => $previous], ['body' => $body], $actor);

            return $comment;
        });
    }

    public function delete(Comment $comment, User $actor): void
    {
        DB::transaction(function () use ($comment, $actor) {
            $this->comments->delete($comment);
            $this->audit->record('comment.deleted', 'comentarios', $comment, ['body' => $comment->body], [], $actor);
        });
    }

    /**
     * Un comentario de tarea debe pertenecer al mismo proyecto (ADR-007).
     */
    private function resolveTask(Project $project, int|string|null $taskId): ?Task
    {
        if ($taskId === null || $taskId === '') {
            return null;
        }

        return $this->tasks->findInProject($project, (int) $taskId)
            ?? throw new BusinessRuleException('La tarea indicada no pertenece a este proyecto.');
    }
}
