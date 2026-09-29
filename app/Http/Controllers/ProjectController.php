<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\CommentService;
use App\Services\ProgressService;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly UserService $users,
        private readonly TaskService $tasks,
        private readonly ProgressService $progress,
        private readonly CommentService $comments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        return view('projects.index', [
            'projects' => $this->projects->listFor($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create', [
            'project' => new Project,
            'teachers' => $this->users->teachers(),
            'students' => $this->users->availableStudents()->reject(fn ($user) => $user->is($request->user())),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projects->create($request->user(), $request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Proyecto creado correctamente.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $user = $request->user();

        return view('projects.show', [
            'project' => $this->projects->details($project),
            'canManageMembers' => $user->can('manageMembers', $project),
            'availableStudents' => $user->can('manageMembers', $project) ? $this->users->availableStudents($project) : collect(),
            'summary' => $this->progress->summary($project),
            'comments' => $this->comments->forProject($project),
            'tasks' => $this->tasks->forProject($project),
            'canCreateTasks' => $user->can('create', [Task::class, $project]),
            'trashedTasks' => $user->can('manageTrash', [Task::class, $project]) ? $this->tasks->trashedForProject($project) : collect(),
            'nextStatuses' => collect($project->status->allowedTransitions())
                ->filter(fn ($status) => $user->can('changeStatus', [$project, $status])),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', [
            'project' => $project,
            'teachers' => $this->users->teachers(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->projects->update($project, $request->validated(), $request->user());

        return redirect()->route('projects.show', $project)->with('success', 'Proyecto actualizado.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->projects->delete($project, $request->user());

        return redirect()->route('projects.index')->with('success', 'Proyecto eliminado.');
    }
}
