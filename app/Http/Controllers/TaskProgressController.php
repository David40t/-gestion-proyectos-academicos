<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\UpdateTaskProgressRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;

class TaskProgressController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function update(UpdateTaskProgressRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('updateProgress', $task);

        $this->tasks->updateProgress($task, $request->status(), (int) $request->validated('progress'), $request->user());

        return back()->with('success', 'Avance registrado.');
    }
}
