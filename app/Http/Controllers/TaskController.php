<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\ProjectMemberService;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tareas anidadas en su proyecto: /projects/{project}/tasks/{task} (scoped binding).
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ProjectMemberService $members,
    ) {}

    public function index(Project $project): RedirectResponse
    {
        // El listado de tareas vive en el detalle del proyecto.
        return redirect()->to(route('projects.show', $project).'#tareas');
    }

    public function create(Project $project): View
    {
        $this->authorize('create', [Task::class, $project]);

        return view('tasks.create', $this->formData($project, new Task(['priority' => TaskPriority::Media])));
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Task::class, $project]);
        if ($request->filled('assigned_to')) {
            $this->authorize('assign', [Task::class, $project]);
        }

        $task = $this->tasks->create($project, $request->validated(), $request->user());

        return redirect()->route('projects.tasks.show', [$project, $task])->with('success', 'Tarea creada.');
    }

    public function show(Request $request, Project $project, Task $task): View
    {
        $this->authorize('view', $task);

        return view('tasks.show', [
            'project' => $project,
            'task' => $task->load(['assignee:id,name', 'creator:id,name']),
            'selectableStatuses' => TaskStatus::selectable(),
        ]);
    }

    public function edit(Project $project, Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.edit', $this->formData($project, $task));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        if ((int) $request->validated('assigned_to') !== (int) $task->assigned_to) {
            $this->authorize('assign', [Task::class, $project]);
        }

        $this->tasks->update($task, $request->validated(), $request->user());

        return redirect()->route('projects.tasks.show', [$project, $task])->with('success', 'Tarea actualizada.');
    }

    public function destroy(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->tasks->delete($task, $request->user());

        return redirect()->to(route('projects.show', $project).'#tareas')->with('success', "Tarea \"{$task->title}\" eliminada. Puedes restaurarla desde la papelera.");
    }

    public function restore(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('restore', $task);

        $this->tasks->restore($task, $request->user());

        return redirect()->route('projects.tasks.show', [$project, $task])->with('success', 'Tarea restaurada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Project $project, Task $task): array
    {
        return [
            'project' => $project,
            'task' => $task,
            'members' => $this->members->membersOf($project)->pluck('name', 'id'),
            'priorities' => TaskPriority::cases(),
            'selectableStatuses' => TaskStatus::selectable(),
        ];
    }
}
