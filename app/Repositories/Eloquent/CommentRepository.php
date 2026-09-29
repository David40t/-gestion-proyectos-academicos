<?php

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Support\Collection;

class CommentRepository implements CommentRepositoryInterface
{
    public function create(array $attributes): Comment
    {
        return Comment::create($attributes);
    }

    public function update(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    public function forProject(Project $project): Collection
    {
        return $project->comments()->whereNull('task_id')->with('author:id,name')->latest()->get();
    }

    public function forTask(Task $task): Collection
    {
        return $task->comments()->with('author:id,name')->latest()->get();
    }
}
