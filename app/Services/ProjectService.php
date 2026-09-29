<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso del módulo de proyectos (docs/03, §2).
 */
class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ProjectMemberRepositoryInterface $members,
        private readonly UserRepositoryInterface $users,
        private readonly ProjectMemberService $memberService,
        private readonly AuditService $audit,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function listFor(User $user): LengthAwarePaginator
    {
        return $this->projects->paginateVisibleTo($user);
    }

    public function details(Project $project): Project
    {
        return $this->projects->loadDetails($project);
    }

    /**
     * Crea el proyecto, registra al creador como líder e integrante y agrega
     * los integrantes iniciales. Todo o nada (una sola transacción).
     *
     * @param  array<string, mixed>  $data  Datos validados (incluye member_ids opcional).
     */
    public function create(User $creator, array $data): Project
    {
        return DB::transaction(function () use ($creator, $data) {
            $project = $this->projects->create([
                ...Arr::except($data, 'member_ids'),
                'status' => ProjectStatus::Planeacion,
                'leader_id' => $creator->id,
                'created_by' => $creator->id,
            ]);

            $this->members->add($project, $creator);
            $this->audit->record('project.created', 'proyectos', $project, [], Arr::except($data, 'member_ids'), $creator);

            $initialMembers = $this->users->findMany($data['member_ids'] ?? [])->reject(fn (User $user) => $user->is($creator));

            foreach ($initialMembers as $member) {
                $this->memberService->add($project, $member, $creator);
            }

            $this->memberService->syncLeaderRole($creator, $creator);

            return $project;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data, User $actor): Project
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $original = $project->getRawOriginal();
            $this->projects->update($project, $data);

            $changes = Arr::except($project->getChanges(), 'updated_at');
            if ($changes !== []) {
                $this->audit->record('project.updated', 'proyectos', $project,
                    Arr::only($original, array_keys($changes)), $changes, $actor);
            }

            return $project;
        });
    }

    public function changeStatus(Project $project, ProjectStatus $status, User $actor): void
    {
        if (! $project->status->canTransitionTo($status)) {
            throw new BusinessRuleException(
                "No se puede pasar de \"{$project->status->label()}\" a \"{$status->label()}\"."
            );
        }

        DB::transaction(function () use ($project, $status, $actor) {
            $previous = $project->status;
            $this->projects->update($project, ['status' => $status]);

            $this->audit->record('project.status_changed', 'proyectos', $project,
                ['status' => $previous->value], ['status' => $status->value], $actor);
        });
    }

    public function delete(Project $project, User $actor): void
    {
        if ($project->status !== ProjectStatus::Planeacion || $this->projects->hasTasks($project)) {
            throw new BusinessRuleException('Solo se pueden eliminar proyectos en planeación y sin tareas. Considera cancelarlo.');
        }

        DB::transaction(function () use ($project, $actor) {
            $leader = $project->leader;
            $this->projects->delete($project);

            $this->audit->record('project.deleted', 'proyectos', $project, ['title' => $project->title], [], $actor);
            $this->memberService->syncLeaderRole($leader, $actor);
        });
    }

}
