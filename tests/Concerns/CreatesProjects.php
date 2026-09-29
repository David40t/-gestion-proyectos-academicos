<?php

namespace Tests\Concerns;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * Escenario típico: un proyecto con líder, un integrante, docente responsable y un usuario ajeno.
 *
 * @mixin \Tests\TestCase
 */
trait CreatesProjects
{
    protected Project $project;

    protected User $leader;

    protected User $member;

    protected User $teacher;

    protected User $outsider;

    protected function createProjectScenario(ProjectStatus $status = ProjectStatus::Planeacion): void
    {
        $this->leader = $this->userWithRoles(Role::ESTUDIANTE, Role::LIDER);
        $this->member = $this->userWithRoles(Role::ESTUDIANTE);
        $this->teacher = $this->userWithRoles(Role::DOCENTE);
        $this->outsider = $this->userWithRoles(Role::ESTUDIANTE, Role::LIDER);

        $this->project = Project::factory()->withLeaderAsMember()->status($status)->create([
            'leader_id' => $this->leader->id,
            'teacher_id' => $this->teacher->id,
        ]);
        $this->project->members()->attach($this->member->id);
    }
}
