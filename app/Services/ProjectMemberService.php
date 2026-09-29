<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Project\MemberAdded;
use App\Notifications\Project\MemberRemoved;
use App\Notifications\Project\ProjectLeaderChanged;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de integrantes y liderazgo de un proyecto (docs/03, §3 y ADR-006).
 */
class ProjectMemberService
{
    public function __construct(
        private readonly ProjectMemberRepositoryInterface $members,
        private readonly ProjectRepositoryInterface $projects,
        private readonly TaskRepositoryInterface $tasks,
        private readonly RoleService $roles,
        private readonly AuditService $audit,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function membersOf(Project $project): Collection
    {
        return $this->members->membersOf($project);
    }

    public function add(Project $project, User $user, User $actor): void
    {
        if (! $user->hasRole(Role::ESTUDIANTE)) {
            throw new BusinessRuleException('Solo se pueden agregar estudiantes como integrantes.');
        }

        if ($this->members->isMember($project, $user)) {
            throw new BusinessRuleException("{$user->name} ya es integrante del proyecto.");
        }

        DB::transaction(function () use ($project, $user, $actor) {
            $this->members->add($project, $user);
            $this->audit->record('member.added', 'integrantes', $project, [], ['user_id' => $user->id, 'name' => $user->name], $actor);
            $this->notifier->send($user, new MemberAdded($project, $actor), $actor);
        });
    }

    public function remove(Project $project, User $user, User $actor): void
    {
        if ($project->isLedBy($user)) {
            throw new BusinessRuleException('No se puede retirar al líder. Primero asigna otro líder al proyecto.');
        }

        if (! $this->members->isMember($project, $user)) {
            throw new BusinessRuleException("{$user->name} no es integrante del proyecto.");
        }

        DB::transaction(function () use ($project, $user, $actor) {
            $unassigned = $this->tasks->unassignOpenTasks($project, $user);
            $this->members->remove($project, $user);

            $this->audit->record('member.removed', 'integrantes', $project, ['user_id' => $user->id, 'name' => $user->name], [
                'unassigned_tasks' => $unassigned,
            ], $actor);
            $this->notifier->send($user, new MemberRemoved($project, $actor), $actor);
        });
    }

    public function changeLeader(Project $project, User $newLeader, User $actor): void
    {
        if ($project->isLedBy($newLeader)) {
            throw new BusinessRuleException("{$newLeader->name} ya es el líder del proyecto.");
        }

        if (! $this->members->isMember($project, $newLeader)) {
            throw new BusinessRuleException('El nuevo líder debe ser integrante del proyecto.');
        }

        DB::transaction(function () use ($project, $newLeader, $actor) {
            $previousLeader = $project->leader;

            $this->projects->update($project, ['leader_id' => $newLeader->id]);

            $this->audit->record('project.leader_changed', 'integrantes', $project,
                ['leader_id' => $previousLeader->id], ['leader_id' => $newLeader->id], $actor);

            $this->syncLeaderRole($newLeader, $actor);
            $this->syncLeaderRole($previousLeader, $actor);

            $this->notifier->toProject($project, new ProjectLeaderChanged($project, $newLeader, $actor), $actor);
        });
    }

    /**
     * El rol LIDER refleja si el usuario lidera al menos un proyecto (ADR-006).
     */
    public function syncLeaderRole(User $user, User $actor): void
    {
        if ($this->projects->countLedBy($user) > 0) {
            $this->roles->assign($user, Role::LIDER, $actor);
        } else {
            $this->roles->revoke($user, Role::LIDER, $actor);
        }
    }
}
