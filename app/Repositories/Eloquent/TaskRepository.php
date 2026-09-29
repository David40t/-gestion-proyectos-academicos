<?php

namespace App\Repositories\Eloquent;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\TaskRepositoryInterface;

class TaskRepository implements TaskRepositoryInterface
{
    public function unassignOpenTasks(Project $project, User $user): int
    {
        return $project->tasks()
            ->where('assigned_to', $user->id)
            ->whereIn('status', TaskStatus::open())
            ->update(['assigned_to' => null]);
    }
}
