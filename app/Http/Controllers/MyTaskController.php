<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Mis tareas": tareas asignadas al usuario autenticado en todos sus proyectos.
 */
class MyTaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Task::class);

        return view('tasks.mine', ['tasks' => $this->tasks->assignedTo($request->user())]);
    }
}
