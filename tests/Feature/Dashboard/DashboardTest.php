<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ProjectStatus;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);

        $factory = Task::factory()->state(['project_id' => $this->project->id]);
        $factory->create(['assigned_to' => $this->member->id, 'title' => 'Tarea pendiente del integrante', 'due_date' => now()->addDays(3)]);
        $factory->inProgress(50)->create(['assigned_to' => $this->member->id, 'due_date' => now()->addDays(20)]);
        $factory->overdue()->create(['assigned_to' => $this->member->id, 'title' => 'Tarea vencida del integrante']);
        $factory->completed()->create(['assigned_to' => $this->member->id]);
        $factory->create(['assigned_to' => $this->leader->id, 'title' => 'Tarea del líder', 'due_date' => now()->addDays(2)]);
    }

    private function statValue(array $data, string $label): int
    {
        return collect($data['stats'])->firstWhere('label', $label)['value'];
    }

    public function test_student_stats_are_based_on_their_own_tasks(): void
    {
        $data = app(DashboardService::class)->for($this->member);

        $this->assertSame('student', $data['perspective']);
        $this->assertSame(1, $this->statValue($data, 'Mis proyectos'));
        $this->assertSame(1, $this->statValue($data, 'Proyectos activos'));
        $this->assertSame(2, $this->statValue($data, 'Mis tareas pendientes'));
        $this->assertSame(1, $this->statValue($data, 'Vencen en 7 días'));
        $this->assertSame(1, $this->statValue($data, 'Tareas vencidas'));
        $this->assertTrue($data['ledProjects']->isEmpty());
    }

    public function test_student_dashboard_page(): void
    {
        $this->actingAs($this->member)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis proyectos')
            ->assertSee($this->project->title)
            ->assertSee('Tarea vencida del integrante')
            ->assertDontSee('Tarea del líder')
            ->assertDontSee('Proyectos que lideras');
    }

    public function test_leader_sees_the_projects_they_lead_with_stats(): void
    {
        $data = app(DashboardService::class)->for($this->leader);

        $led = $data['ledProjects']->sole();
        $this->assertTrue($led->is($this->project));
        $this->assertSame(2, $led->members_count);
        $this->assertSame(3, $led->open_tasks_count);
        $this->assertSame(1, $led->overdue_tasks_count);

        $this->actingAs($this->leader)->get(route('dashboard'))
            ->assertOk()->assertSee('Proyectos que lideras')->assertSee('Tarea del líder');
    }

    public function test_teacher_dashboard_covers_supervised_projects(): void
    {
        Project::factory()->withLeaderAsMember()->create(['title' => 'Proyecto de otro docente']);
        Comment::factory()->observation()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->teacher->id,
            'body' => 'Observación para el dashboard',
        ]);
        $this->actingAs($this->leader)->patch(route('projects.status.update', $this->project), ['status' => 'en_revision']);

        $data = app(DashboardService::class)->for($this->teacher);
        $this->assertSame('teacher', $data['perspective']);
        $this->assertSame(1, $this->statValue($data, 'Proyectos supervisados'));
        $this->assertSame(3, $this->statValue($data, 'Tareas pendientes'));
        $this->assertSame(2, $this->statValue($data, 'Vencen en 7 días'));

        $this->actingAs($this->teacher)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Proyectos supervisados')
            ->assertSee('Observación para el dashboard')
            ->assertSee('Cambio de estado del proyecto')
            ->assertDontSee('Proyecto de otro docente')
            ->assertDontSee('Nuevo proyecto');
    }

    public function test_dashboard_excludes_deleted_projects(): void
    {
        $this->project->tasks()->delete();
        $this->project->update(['status' => ProjectStatus::Planeacion]);
        $this->project->delete();

        $data = app(DashboardService::class)->for($this->member);
        $this->assertSame(0, $this->statValue($data, 'Mis proyectos'));
        $this->assertSame(0, $this->statValue($data, 'Mis tareas pendientes'));
    }

    public function test_dashboard_query_count_does_not_grow_with_projects(): void
    {
        foreach (range(1, 5) as $i) {
            $project = Project::factory()->withLeaderAsMember()->create(['teacher_id' => $this->teacher->id]);
            Task::factory()->count(3)->create(['project_id' => $project->id]);
        }

        $this->actingAs($this->teacher);
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        $queries = count(DB::getQueryLog());

        // Número fijo de consultas (agregados + eager loading), sin importar cuántos proyectos haya.
        $this->assertLessThanOrEqual(20, $queries, "El dashboard ejecutó {$queries} consultas (posible N+1).");
    }
}
