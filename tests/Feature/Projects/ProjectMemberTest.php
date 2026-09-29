<?php

namespace Tests\Feature\Projects;

use App\Enums\TaskStatus;
use App\Models\Role;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario();
    }

    public function test_the_leader_adds_a_member(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($this->leader)
            ->post(route('projects.members.store', $this->project), ['user_id' => $student->id])
            ->assertSessionHas('success');

        $this->assertTrue($this->project->members()->whereKey($student->id)->exists());
        $this->assertDatabaseHas('audits', ['action' => 'member.added', 'auditable_id' => $this->project->id]);
    }

    public function test_a_member_cannot_be_added_twice(): void
    {
        $this->actingAs($this->leader)
            ->post(route('projects.members.store', $this->project), ['user_id' => $this->member->id])
            ->assertSessionHas('error');

        $this->assertSame(1, $this->project->members()->whereKey($this->member->id)->count());
    }

    public function test_only_students_can_be_members(): void
    {
        $this->actingAs($this->leader)
            ->post(route('projects.members.store', $this->project), ['user_id' => $this->teacher->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_only_the_project_leader_manages_members(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);
        $url = route('projects.members.store', $this->project);

        $this->actingAs($this->member)->post($url, ['user_id' => $student->id])->assertForbidden();
        $this->actingAs($this->outsider)->post($url, ['user_id' => $student->id])->assertForbidden();
        $this->actingAs($this->teacher)->post($url, ['user_id' => $student->id])->assertForbidden();
        $this->actingAs($this->member)->delete(route('projects.members.destroy', [$this->project, $this->leader]))->assertForbidden();
    }

    public function test_the_leader_cannot_be_removed(): void
    {
        $this->actingAs($this->leader)
            ->delete(route('projects.members.destroy', [$this->project, $this->leader]))
            ->assertSessionHas('error');

        $this->assertTrue($this->project->members()->whereKey($this->leader->id)->exists());
    }

    public function test_removing_a_member_unassigns_their_open_tasks(): void
    {
        $open = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->member->id]);
        $done = Task::factory()->completed()->create(['project_id' => $this->project->id, 'assigned_to' => $this->member->id]);

        $this->actingAs($this->leader)
            ->delete(route('projects.members.destroy', [$this->project, $this->member]))
            ->assertSessionHas('success');

        $this->assertFalse($this->project->members()->whereKey($this->member->id)->exists());
        $this->assertNull($open->fresh()->assigned_to);
        $this->assertSame($this->member->id, $done->fresh()->assigned_to, 'Las tareas completadas conservan su responsable.');
        $this->assertSame(TaskStatus::Pendiente, $open->fresh()->status);
        $this->assertDatabaseHas('audits', ['action' => 'member.removed']);
    }

    public function test_leadership_can_be_transferred_to_a_member(): void
    {
        $this->actingAs($this->leader)
            ->patch(route('projects.leader.update', $this->project), ['leader_id' => $this->member->id])
            ->assertSessionHas('success');

        $this->assertTrue($this->project->fresh()->isLedBy($this->member));
        $this->assertTrue($this->member->fresh()->hasRole(Role::LIDER));
        $this->assertFalse($this->leader->fresh()->hasRole(Role::LIDER), 'El líder anterior ya no lidera ningún proyecto.');
        $this->assertTrue($this->project->members()->whereKey($this->leader->id)->exists(), 'El líder anterior sigue como integrante.');
        $this->assertDatabaseHas('audits', ['action' => 'project.leader_changed']);

        // El antiguo líder ya no puede gestionar el proyecto.
        $this->actingAs($this->leader->fresh())
            ->post(route('projects.members.store', $this->project), ['user_id' => $this->outsider->id])
            ->assertForbidden();
    }

    public function test_leadership_cannot_be_transferred_to_a_non_member(): void
    {
        $this->actingAs($this->leader)
            ->patch(route('projects.leader.update', $this->project), ['leader_id' => $this->outsider->id])
            ->assertSessionHas('error');

        $this->assertTrue($this->project->fresh()->isLedBy($this->leader));
    }

    public function test_project_page_shows_management_controls_only_to_the_leader(): void
    {
        $this->actingAs($this->leader)->get(route('projects.show', $this->project))
            ->assertSee('Agregar integrante')->assertSee('Editar');

        $this->actingAs($this->member)->get(route('projects.show', $this->project))
            ->assertOk()->assertDontSee('Agregar integrante')->assertDontSee('Transferir liderazgo');
    }
}
