<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

interface ProjectMemberRepositoryInterface
{
    public function add(Project $project, User $user): void;

    public function remove(Project $project, User $user): void;

    public function isMember(Project $project, User $user): bool;

    /**
     * @return Collection<int, User>
     */
    public function membersOf(Project $project): Collection;
}
