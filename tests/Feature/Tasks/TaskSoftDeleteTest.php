<?php

namespace Tests\Feature\Tasks;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class TaskSoftDeleteTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $this->task = Task::factory()->inProgress(40)->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
        ]);
    }

    public function test_the_leader_soft_deletes_a_task(): void
    {
        $this->actingAs($this->leader)
            ->delete(route('projects.tasks.destroy', [$this->project, $this->task]))
            ->assertRedirect(route('projects.show', $this->project).'#tareas');

        $this->assertSoftDeleted($this->task);
        $this->assertDatabaseHas('audits', ['action' => 'task.deleted', 'auditable_id' => $this->task->id, 'user_id' => $this->leader->id]);
    }

    public function test_deleted_tasks_are_excluded_from_tracking_and_views(): void
    {
        Task::factory()->completed()->create(['project_id' => $this->project->id]);
        $this->task->delete();

        $summary = app(ProgressService::class)->summary($this->project);
        $this->assertSame(100.0, $summary['progress']);
        $this->assertSame(1, $summary['total']);

        $this->actingAs($this->member)->get(route('projects.tasks.show', [$this->project, $this->task]))->assertNotFound();
        $this->actingAs($this->member)->get(route('tasks.mine'))->assertDontSee($this->task->title);
    }

    public function test_only_the_leader_can_delete_or_restore(): void
    {
        $destroy = route('projects.tasks.destroy', [$this->project, $this->task]);

        $this->actingAs($this->member)->delete($destroy)->assertForbidden();
        $this->actingAs($this->teacher)->delete($destroy)->assertForbidden();
        $this->actingAs($this->outsider)->delete($destroy)->assertForbidden();

        $this->task->delete();
        $restore = route('projects.tasks.restore', [$this->project, $this->task]);
        $this->actingAs($this->member)->patch($restore)->assertForbidden();
        $this->assertSoftDeleted($this->task);
    }

    public function test_the_leader_restores_a_task_from_the_trash(): void
    {
        $this->task->delete();

        $this->actingAs($this->leader)->get(route('projects.show', $this->project))
            ->assertSee('Papelera (1)')->assertSee($this->task->title);
        $this->actingAs($this->member)->get(route('projects.show', $this->project))
            ->assertDontSee('Papelera');

        $this->actingAs($this->leader)
            ->patch(route('projects.tasks.restore', [$this->project, $this->task]))
            ->assertRedirect(route('projects.tasks.show', [$this->project, $this->task]));

        $this->assertNotSoftDeleted($this->task);
        $this->assertDatabaseHas('audits', ['action' => 'task.restored', 'auditable_id' => $this->task->id]);
    }

    public function test_a_restored_task_is_marked_overdue_if_its_deadline_passed(): void
    {
        $this->task->update(['start_date' => now()->subDays(10), 'due_date' => now()->subDay()]);
        $this->task->delete();

        $this->actingAs($this->leader)->patch(route('projects.tasks.restore', [$this->project, $this->task]));

        $this->assertSame(TaskStatus::Vencida, $this->task->fresh()->status);
        $this->assertSame(40, $this->task->fresh()->progress);
    }

    public function test_restore_is_scoped_to_the_project(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso); // otro proyecto con otro líder
        $this->task->delete();

        $this->actingAs($this->leader)
            ->patch(route('projects.tasks.restore', [$this->project, $this->task]))
            ->assertNotFound();
    }

    public function test_tasks_of_closed_projects_cannot_be_deleted(): void
    {
        $this->project->update(['status' => ProjectStatus::Finalizado]);

        $this->actingAs($this->leader)
            ->delete(route('projects.tasks.destroy', [$this->project, $this->task]))
            ->assertForbidden();
    }
}
