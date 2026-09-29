<?php

namespace App\Http\Controllers;

use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Project;
use App\Services\CommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Comentarios de un proyecto o de una de sus tareas (task_id opcional).
 */
class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments) {}

    public function store(StoreCommentRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Comment::class, $project]);
        if ($request->boolean('is_observation')) {
            $this->authorize('markObservation', [Comment::class, $project]);
        }

        $comment = $this->comments->create($project, $request->validated(), $request->user());

        return back()->withFragment('comentario-'.$comment->id)
            ->with('success', $comment->is_observation ? 'Observación registrada.' : 'Comentario publicado.');
    }

    public function update(UpdateCommentRequest $request, Project $project, Comment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);

        $this->comments->update($comment, $request->validated('body'), $request->user());

        return back()->withFragment('comentario-'.$comment->id)->with('success', 'Comentario actualizado.');
    }

    public function destroy(Request $request, Project $project, Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $this->comments->delete($comment, $request->user());

        return back()->with('success', 'Comentario eliminado.');
    }
}
