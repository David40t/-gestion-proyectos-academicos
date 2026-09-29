<?php

namespace Tests\Feature\Tasks;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class ProjectTrackingTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    public function test_summary_is_calculated_from_tasks(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $factory = Task::factory()->state(['project_id' => $this->project->id]);

        $factory->completed()->create();                 // 100
        $factory->inProgress(50)->dueIn(3)->create();    // 50, próxima
        $factory->create(['due_date' => now()->addDays(20)]); // 0
        $factory->overdue()->create(['progress' => 10]); // 10

        $summary = app(ProgressService::class)->summary($this->project);

        $this->assertSame(40.0, $summary['progress']); // (100 + 50 + 0 + 10) / 4
        $this->assertSame(4, $summary['total']);
        $this->assertSame(1, $summary['counts'][TaskStatus::Completada->value]);
        $this->assertSame(1, $summary['counts'][TaskStatus::EnProgreso->value]);
        $this->assertSame(1, $summary['counts'][TaskStatus::Pendiente->value]);
        $this->assertSame(1, $summary['counts'][TaskStatus::Vencida->value]);
        $this->assertCount(1, $summary['upcoming']);
    }

    public function test_project_without_tasks_has_zero_progress(): void
    {
        $this->createProjectScenario();

        $summary = app(ProgressService::class)->summary($this->project);

        $this->assertSame(0.0, $summary['progress']);
        $this->assertSame(0, $summary['total']);
    }

    public function test_project_page_shows_tracking(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        Task::factory()->completed()->create(['project_id' => $this->project->id, 'title' => 'Tarea terminada']);

        $this->actingAs($this->teacher)->get(route('projects.show', $this->project))
            ->assertOk()->assertSee('Seguimiento y tareas')->assertSee('Tarea terminada')->assertSee('100%')
            ->assertDontSee('Nueva tarea');
    }
}
