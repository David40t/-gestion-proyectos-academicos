<?php

namespace Tests\Feature\Administration;

use App\Enums\ProjectStatus;
use App\Models\Audit;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

/**
 * El administrador tiene acceso global, pero NO omite las reglas que aplican a todos (ADR-018).
 */
class AdministratorAccessTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $this->admin = $this->userWithRoles(Role::ADMINISTRADOR);
    }

    public function test_sees_and_manages_any_project(): void
    {
        $other = Project::factory()->withLeaderAsMember()->create(['title' => 'Proyecto sin relación con el admin']);

        $this->actingAs($this->admin)->get(route('projects.index'))
            ->assertSee($this->project->title)->assertSee('Proyecto sin relación con el admin');
        $this->actingAs($this->admin)->get(route('projects.show', $other))
            ->assertOk()->assertSee('Agregar integrante')->assertSee('Editar');

        $student = $this->userWithRoles(Role::ESTUDIANTE);
        $this->actingAs($this->admin)->post(route('projects.members.store', $this->project), ['user_id' => $student->id])
            ->assertSessionHas('success');
    }

    public function test_can_make_any_valid_status_transition_but_not_an_invalid_one(): void
    {
        $url = route('projects.status.update', $this->project);

        $this->actingAs($this->admin)->patch($url, ['status' => 'finalizado'])->assertSessionHas('error'); // en progreso → finalizado no es válida
        $this->actingAs($this->admin)->patch($url, ['status' => 'en_revision'])->assertSessionHas('success');
        $this->actingAs($this->admin)->patch($url, ['status' => 'finalizado'])->assertSessionHas('success');

        $this->assertSame(ProjectStatus::Finalizado, $this->project->fresh()->status);
    }

    public function test_manages_tasks_of_any_open_project(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->member->id]);

        $this->actingAs($this->admin)
            ->patch(route('projects.tasks.progress.update', [$this->project, $task]), ['status' => 'en_progreso', 'progress' => 40])
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->delete(route('projects.tasks.destroy', [$this->project, $task]))->assertRedirect();

        $this->assertSoftDeleted($task);
    }

    public function test_business_rules_still_apply_to_the_administrator(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('projects.members.destroy', [$this->project, $this->leader]))
            ->assertSessionHas('error'); // no se puede retirar al líder

        $this->project->update(['status' => ProjectStatus::Finalizado]);
        $this->actingAs($this->admin)->get(route('projects.edit', $this->project))->assertForbidden(); // proyecto cerrado
    }

    public function test_cannot_create_projects_because_leaders_are_students(): void
    {
        $this->actingAs($this->admin)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertDontSee('Nuevo proyecto');
    }

    public function test_moderates_but_cannot_edit_comments_of_others(): void
    {
        $comment = Comment::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'body' => 'Texto original']);

        $this->actingAs($this->admin)->put(route('projects.comments.update', [$this->project, $comment]), ['body' => 'Alterado'])->assertForbidden();
        $this->assertSame('Texto original', $comment->fresh()->body);

        $this->actingAs($this->admin)->delete(route('projects.comments.destroy', [$this->project, $comment]))->assertSessionHas('success');
        $this->assertSoftDeleted($comment);
        $this->assertDatabaseHas('audits', ['action' => 'comment.deleted', 'user_id' => $this->admin->id]);
    }

    public function test_cannot_register_teacher_observations(): void
    {
        $this->actingAs($this->admin)
            ->post(route('projects.comments.store', $this->project), ['body' => 'Obs', 'is_observation' => '1'])
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('projects.comments.store', $this->project), ['body' => 'Comentario del administrador'])
            ->assertSessionHas('success');
    }

    public function test_sees_the_whole_audit_but_cannot_modify_it(): void
    {
        $this->post('/login', ['email' => $this->member->email, 'password' => 'password']);
        $this->post('/logout');
        $audit = Audit::where('action', 'auth.login')->firstOrFail();

        $this->actingAs($this->admin)->get(route('audits.show', $audit))->assertOk();
        $this->actingAs($this->admin)->delete('/audits/'.$audit->id)->assertStatus(405);
        $this->assertFalse($this->admin->can('update', $audit));
        $this->assertFalse($this->admin->can('delete', $audit));
    }

    public function test_administrator_dashboard_shows_the_whole_system(): void
    {
        Project::factory()->withLeaderAsMember()->create(['title' => 'Otro proyecto del sistema']);

        $data = app(DashboardService::class)->for($this->admin);
        $this->assertSame('admin', $data['perspective']);
        $this->assertSame(2, collect($data['stats'])->firstWhere('label', 'Proyectos')['value']);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Proyectos recientes del sistema')
            ->assertSee('Otro proyecto del sistema')
            ->assertSee('Usuarios por rol');
    }
}
