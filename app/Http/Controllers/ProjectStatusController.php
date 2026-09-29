<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\ChangeProjectStatusRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;

class ProjectStatusController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function update(ChangeProjectStatusRequest $request, Project $project): RedirectResponse
    {
        $status = $request->status();
        $this->authorize('changeStatus', [$project, $status]);

        $this->projects->changeStatus($project, $status, $request->user());

        return back()->with('success', "Estado actualizado a \"{$status->label()}\".");
    }
}
