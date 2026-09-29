<?php

namespace App\Http\Controllers;

use App\Http\Requests\Member\ChangeLeaderRequest;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMemberService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function __construct(
        private readonly ProjectMemberService $members,
        private readonly UserService $users,
    ) {}

    public function store(StoreMemberRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $member = $this->users->find($request->validated('user_id'));
        $this->members->add($project, $member, $request->user());

        return back()->with('success', "{$member->name} fue agregado al proyecto.");
    }

    public function destroy(Request $request, Project $project, User $member): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $this->members->remove($project, $member, $request->user());

        return back()->with('success', "{$member->name} fue retirado del proyecto.");
    }

    public function updateLeader(ChangeLeaderRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $leader = $this->users->find($request->validated('leader_id'));
        $this->members->changeLeader($project, $leader, $request->user());

        return redirect()->route('projects.show', $project)->with('success', "{$leader->name} es el nuevo líder.");
    }
}
