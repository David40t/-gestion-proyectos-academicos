<?php

namespace Tests\Feature\Audit;

use App\Enums\ProjectStatus;
use App\Models\Audit;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
    }

    public function test_audits_record_their_project_context_automatically(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->leader)->patch(route('projects.status.update', $this->project), ['status' => 'en_revision']);
        $this->actingAs($this->leader)->patch(route('projects.tasks.progress.update', [$this->project, $task]), ['status' => 'en_progreso', 'progress' => 10]);
        $this->actingAs($this->member)->post(route('projects.comments.store', $this->project), ['body' => 'Hola']);

        $this->assertSame(
            ['comment.created', 'project.status_changed', 'task.status_changed', 'task.updated'],
            Audit::where('project_id', $this->project->id)->orderBy('action')->distinct()->pluck('action')->all(),
        );
    }

    public function test_the_teacher_sees_only_audits_of_supervised_projects(): void
    {
        $this->actingAs($this->leader)->patch(route('projects.status.update', $this->project), ['status' => 'en_revision']);

        $foreign = Project::factory()->withLeaderAsMember()->create(['title' => 'Proyecto ajeno']);
        app(\App\Services\AuditService::class)->record('project.created', 'proyectos', $foreign);

        $this->actingAs($this->teacher)->get(route('audits.index'))
            ->assertOk()
            ->assertSee('<td>Cambio de estado del proyecto</td>', false)
            ->assertDontSee('Proyecto ajeno');

        $foreignAudit = Audit::where('project_id', $foreign->id)->firstOrFail();
        $this->actingAs($this->teacher)->get(route('audits.show', $foreignAudit))->assertForbidden();
    }

    public function test_auth_audits_are_not_visible_to_teachers(): void
    {
        $this->post('/login', ['email' => $this->member->email, 'password' => 'password']);
        $loginAudit = Audit::where('action', 'auth.login')->firstOrFail();
        $this->post('/logout');

        $this->actingAs($this->teacher)->get(route('audits.show', $loginAudit))->assertForbidden();
    }

    public function test_students_cannot_access_the_audit(): void
    {
        $this->actingAs($this->member)->get(route('audits.index'))->assertForbidden();
        $this->actingAs($this->leader)->get(route('audits.index'))->assertForbidden();
        $this->actingAs($this->leader)->get(route('projects.show', $this->project))->assertDontSee('Historial');
    }

    public function test_filters_by_action_and_user(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $this->actingAs($this->leader)->patch(route('projects.status.update', $this->project), ['status' => 'en_revision']);
        $this->actingAs($this->leader)->patch(route('projects.tasks.progress.update', [$this->project, $task]), ['status' => 'en_progreso', 'progress' => 10]);

        $this->actingAs($this->teacher)->get(route('audits.index', ['action' => 'project.status_changed']))
            ->assertOk()
            ->assertSee('<td>Cambio de estado del proyecto</td>', false)
            ->assertDontSee('<td>Tarea modificada</td>', false);

        $this->actingAs($this->teacher)->get(route('audits.index', ['user' => 'no-existe-nadie']))
            ->assertOk()->assertSee('No hay registros');

        $this->actingAs($this->teacher)->get(route('audits.index', ['module' => 'inventado']))
            ->assertSessionHasErrors('module');
    }

    public function test_detail_shows_old_and_new_values_without_sensitive_data(): void
    {
        $comment = Comment::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'body' => 'Texto original']);
        $this->actingAs($this->member)->put(route('projects.comments.update', [$this->project, $comment]), ['body' => 'Texto corregido']);

        $audit = Audit::where('action', 'comment.updated')->firstOrFail();

        $this->actingAs($this->teacher)->get(route('audits.show', $audit))
            ->assertOk()
            ->assertSee('Comentario modificado')
            ->assertSee('Texto original')
            ->assertSee('Texto corregido')
            ->assertSee($this->member->email)
            ->assertSee('127.0.0.1');
    }

    public function test_audits_are_read_only(): void
    {
        $audit = app(\App\Services\AuditService::class)->record('project.created', 'proyectos', $this->project);
        $url = '/audits/'.$audit->id;

        $this->actingAs($this->teacher)->put($url, ['action' => 'x'])->assertStatus(405);
        $this->actingAs($this->teacher)->patch($url, ['action' => 'x'])->assertStatus(405);
        $this->actingAs($this->teacher)->delete($url)->assertStatus(405);

        $this->assertFalse($this->teacher->can('update', $audit));
        $this->assertFalse($this->teacher->can('delete', $audit));
        $this->assertDatabaseHas('audits', ['id' => $audit->id, 'action' => 'project.created']);
    }

    public function test_the_administrator_sees_the_whole_audit_including_authentication(): void
    {
        $this->post('/login', ['email' => $this->member->email, 'password' => 'password']);
        $this->post('/logout');

        $admin = $this->userWithRoles(Role::ADMINISTRADOR);

        $this->actingAs($admin)->get(route('audits.index', ['module' => 'auth']))
            ->assertOk()->assertSee('<td>Inicio de sesión</td>', false)->assertSee($this->member->name);
    }

    public function test_the_teacher_sees_the_history_button_on_supervised_projects(): void
    {
        $this->actingAs($this->teacher)->get(route('projects.show', $this->project))
            ->assertSee(route('audits.index', ['project_id' => $this->project->id]), false);
    }
}
