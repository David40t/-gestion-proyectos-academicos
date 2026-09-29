<?php

namespace Tests\Feature\Tasks;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $this->project->update(['start_date' => now()->subMonth(), 'end_date' => now()->addMonths(2)]);
    }

    private function taskData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Diseñar modelo ER',
            'description' => 'Diagrama en draw.io',
            'priority' => 'alta',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'assigned_to' => $this->member->id,
        ], $overrides);
    }

    public function test_the_leader_creates_and_assigns_a_task(): void
    {
        $this->actingAs($this->leader)
            ->post(route('projects.tasks.store', $this->project), $this->taskData())
            ->assertSessionHasNoErrors();

        $task = Task::firstOrFail();
        $this->assertSame(TaskStatus::Pendiente, $task->status);
        $this->assertSame(0, $task->progress);
        $this->assertSame($this->member->id, $task->assigned_to);
        $this->assertSame($this->leader->id, $task->created_by);
        $this->assertDatabaseHas('audits', ['action' => 'task.created', 'auditable_id' => $task->id]);
        $this->assertDatabaseHas('audits', ['action' => 'task.assigned', 'auditable_id' => $task->id]);
    }

    public function test_only_the_project_leader_creates_tasks(): void
    {
        $url = route('projects.tasks.store', $this->project);

        $this->actingAs($this->member)->post($url, $this->taskData())->assertForbidden();
        $this->actingAs($this->outsider)->post($url, $this->taskData())->assertForbidden();
        $this->actingAs($this->teacher)->post($url, $this->taskData())->assertForbidden();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_task_validation(): void
    {
        $this->actingAs($this->leader)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'title' => '',
                'priority' => 'urgente',
                'start_date' => now()->addDays(5)->toDateString(),
                'due_date' => now()->addDays(2)->toDateString(),
                'assigned_to' => $this->outsider->id,
            ]))
            ->assertSessionHasErrors(['title', 'priority', 'due_date', 'assigned_to']);
    }

    public function test_task_dates_must_be_within_the_project_range(): void
    {
        $this->actingAs($this->leader)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'start_date' => $this->project->start_date->copy()->subDay()->toDateString(),
                'due_date' => $this->project->end_date->copy()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors(['start_date', 'due_date']);
    }

    public function test_new_tasks_cannot_be_due_in_the_past(): void
    {
        $this->actingAs($this->leader)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'start_date' => now()->subDays(3)->toDateString(),
                'due_date' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('due_date');
    }

    public function test_no_tasks_in_closed_projects(): void
    {
        $this->project->update(['status' => ProjectStatus::Finalizado]);

        $this->actingAs($this->leader)
            ->post(route('projects.tasks.store', $this->project), $this->taskData())
            ->assertForbidden();
    }

    public function test_the_leader_edits_a_task_and_state_stays_coherent(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->member->id]);

        $this->actingAs($this->leader)
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->taskData([
                'title' => 'Título editado',
                'status' => 'completada',
                'progress' => 40, // incoherente: el backend lo fija en 100
                'assigned_to' => $this->leader->id,
            ]))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Título editado', $task->title);
        $this->assertSame(TaskStatus::Completada, $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNotNull($task->completed_at);
        $this->assertDatabaseHas('audits', ['action' => 'task.updated', 'auditable_id' => $task->id]);
        $this->assertDatabaseHas('audits', ['action' => 'task.assigned', 'auditable_id' => $task->id]);
        $this->assertDatabaseHas('audits', ['action' => 'task.status_changed', 'auditable_id' => $task->id]);
    }

    public function test_status_vencida_cannot_be_chosen_manually(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->leader)
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->taskData(['status' => 'vencida', 'progress' => 10]))
            ->assertSessionHasErrors('status');
    }

    public function test_tasks_are_scoped_to_their_project(): void
    {
        $otherProject = Project::factory()->withLeaderAsMember()->create(['leader_id' => $this->leader->id]);
        $task = Task::factory()->create(['project_id' => $otherProject->id]);

        // La tarea existe, pero no pertenece a este proyecto: 404 (scoped binding).
        $this->actingAs($this->leader)->get(route('projects.tasks.show', [$this->project, $task]))->assertNotFound();
    }

    public function test_participants_view_tasks_and_outsiders_cannot(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $url = route('projects.tasks.show', [$this->project, $task]);

        $this->actingAs($this->member)->get($url)->assertOk()->assertSee($task->title);
        $this->actingAs($this->teacher)->get($url)->assertOk();
        $this->actingAs($this->outsider)->get($url)->assertForbidden();
    }

    public function test_task_forms_render_for_the_leader(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->leader)->get(route('projects.tasks.create', $this->project))->assertOk()->assertSee($this->member->name);
        $this->actingAs($this->leader)->get(route('projects.tasks.edit', [$this->project, $task]))->assertOk();
        $this->actingAs($this->member)->get(route('projects.tasks.edit', [$this->project, $task]))->assertForbidden();
    }
}
