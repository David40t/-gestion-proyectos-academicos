<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Proyecto de prueba',
            'description' => 'Descripción del proyecto',
            'objectives' => 'Objetivos',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-15',
            'teacher_id' => $this->userWithRoles(Role::DOCENTE)->id,
        ], $overrides);
    }

    public function test_a_student_creates_a_project_and_becomes_its_leader(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);
        $classmate = $this->userWithRoles(Role::ESTUDIANTE);

        $response = $this->actingAs($student)->post('/projects', $this->validData(['member_ids' => [$classmate->id]]));

        $project = Project::firstOrFail();
        $response->assertRedirect(route('projects.show', $project));

        $this->assertSame(ProjectStatus::Planeacion, $project->status);
        $this->assertTrue($project->isLedBy($student));
        $this->assertEqualsCanonicalizing([$student->id, $classmate->id], $project->members->pluck('id')->all());
        $this->assertTrue($student->fresh()->hasRole(Role::LIDER));
        $this->assertDatabaseHas('audits', ['action' => 'project.created', 'auditable_id' => $project->id, 'user_id' => $student->id]);
        $this->assertDatabaseHas('audits', ['action' => 'member.added', 'auditable_id' => $project->id]);
    }

    public function test_project_creation_is_atomic(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);
        $teacherAsMember = $this->userWithRoles(Role::DOCENTE);

        // El Form Request lo rechaza; si llegara al Service, la transacción revertiría todo.
        $this->actingAs($student)
            ->post('/projects', $this->validData(['member_ids' => [$teacherAsMember->id]]))
            ->assertSessionHasErrors('member_ids.0');

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_members', 0);
        $this->assertDatabaseCount('audits', 0);
    }

    public function test_project_validation(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);
        $notATeacher = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($student)
            ->post('/projects', $this->validData([
                'title' => '',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-01',
                'teacher_id' => $notATeacher->id,
            ]))
            ->assertSessionHasErrors(['title', 'end_date', 'teacher_id']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_teachers_cannot_create_projects(): void
    {
        $teacher = $this->userWithRoles(Role::DOCENTE);

        $this->actingAs($teacher)->get('/projects/create')->assertForbidden();
        $this->actingAs($teacher)->post('/projects', $this->validData())->assertForbidden();
    }

    public function test_listing_only_shows_projects_the_user_participates_in(): void
    {
        $this->createProjectScenario();
        $other = Project::factory()->withLeaderAsMember()->create(['title' => 'Proyecto ajeno']);

        $this->actingAs($this->member)->get('/projects')
            ->assertOk()->assertSee($this->project->title)->assertDontSee('Proyecto ajeno');

        $this->actingAs($this->teacher)->get('/projects')
            ->assertOk()->assertSee($this->project->title)->assertDontSee($other->title);
    }

    public function test_only_participants_can_view_a_project(): void
    {
        $this->createProjectScenario();

        $this->actingAs($this->member)->get(route('projects.show', $this->project))->assertOk();
        $this->actingAs($this->teacher)->get(route('projects.show', $this->project))->assertOk();
        $this->actingAs($this->outsider)->get(route('projects.show', $this->project))->assertForbidden();
    }

    public function test_the_leader_updates_the_project_and_changes_are_audited(): void
    {
        $this->createProjectScenario();

        $this->actingAs($this->leader)
            ->put(route('projects.update', $this->project), $this->validData(['title' => 'Nuevo título']))
            ->assertRedirect(route('projects.show', $this->project));

        $this->assertSame('Nuevo título', $this->project->fresh()->title);

        $audit = \App\Models\Audit::where('action', 'project.updated')->firstOrFail();
        $this->assertSame('Nuevo título', $audit->new_values['title']);
        $this->assertArrayHasKey('title', $audit->old_values);
    }

    public function test_members_and_other_leaders_cannot_edit_the_project(): void
    {
        $this->createProjectScenario();

        $this->actingAs($this->member)->put(route('projects.update', $this->project), $this->validData())->assertForbidden();
        // Tiene el rol LIDER, pero no lidera ESTE proyecto: la Policy lo impide aunque conozca la URL.
        $this->actingAs($this->outsider)->put(route('projects.update', $this->project), $this->validData())->assertForbidden();
        $this->actingAs($this->teacher)->get(route('projects.edit', $this->project))->assertForbidden();
    }

    public function test_status_transitions_follow_the_rules(): void
    {
        $this->createProjectScenario();
        $url = route('projects.status.update', $this->project);

        // Transición inválida (planeación → finalizado) rechazada por la Policy (el líder no puede finalizar).
        $this->actingAs($this->leader)->patch($url, ['status' => 'finalizado'])->assertForbidden();

        // Transición inválida según el Enum (planeación → en revisión): regla de negocio.
        $this->actingAs($this->leader)->from(route('projects.show', $this->project))
            ->patch($url, ['status' => 'en_revision'])
            ->assertRedirect(route('projects.show', $this->project))
            ->assertSessionHas('error');

        $this->actingAs($this->leader)->patch($url, ['status' => 'en_progreso'])->assertSessionHas('success');
        $this->actingAs($this->leader)->patch($url, ['status' => 'en_revision'])->assertSessionHas('success');

        // En revisión: solo el docente finaliza.
        $this->actingAs($this->leader)->patch($url, ['status' => 'finalizado'])->assertForbidden();
        $this->actingAs($this->teacher)->patch($url, ['status' => 'finalizado'])->assertSessionHas('success');

        $this->assertSame(ProjectStatus::Finalizado, $this->project->fresh()->status);
        $this->assertDatabaseCount('audits', 3);
    }

    public function test_the_teacher_cannot_change_status_outside_review(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso);

        $this->actingAs($this->teacher)
            ->patch(route('projects.status.update', $this->project), ['status' => 'cancelado'])
            ->assertForbidden();
    }

    public function test_closed_projects_cannot_be_edited(): void
    {
        $this->createProjectScenario(ProjectStatus::Finalizado);

        $this->actingAs($this->leader)->get(route('projects.edit', $this->project))->assertForbidden();
    }

    public function test_deleting_a_project_is_restricted_and_revokes_leader_role(): void
    {
        $this->createProjectScenario();
        $this->actingAs($this->member)->delete(route('projects.destroy', $this->project))->assertForbidden();

        Task::factory()->create(['project_id' => $this->project->id]);
        $this->actingAs($this->leader)->delete(route('projects.destroy', $this->project))->assertSessionHas('error');
        $this->assertNotSoftDeleted($this->project);

        $this->project->tasks()->delete();
        $this->actingAs($this->leader)->delete(route('projects.destroy', $this->project))->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($this->project);
        $this->assertFalse($this->leader->fresh()->hasRole(Role::LIDER));
        $this->assertDatabaseHas('audits', ['action' => 'project.deleted']);
        $this->assertDatabaseHas('audits', ['action' => 'role.revoked']);
    }
}
