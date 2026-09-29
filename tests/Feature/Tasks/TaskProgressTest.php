<?php

namespace Tests\Feature\Tasks;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class TaskProgressTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $this->task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->member->id]);
    }

    private function progressUrl(): string
    {
        return route('projects.tasks.progress.update', [$this->project, $this->task]);
    }

    public function test_the_assignee_registers_progress(): void
    {
        $this->actingAs($this->member)
            ->patch($this->progressUrl(), ['status' => 'pendiente', 'progress' => 30])
            ->assertSessionHas('success');

        $this->task->refresh();
        $this->assertSame(TaskStatus::EnProgreso, $this->task->status, 'Un avance > 0 pasa la tarea a en progreso.');
        $this->assertSame(30, $this->task->progress);
        $this->assertDatabaseHas('audits', ['action' => 'task.status_changed', 'user_id' => $this->member->id]);
    }

    public function test_completing_a_task_sets_progress_and_date(): void
    {
        $this->actingAs($this->member)->patch($this->progressUrl(), ['status' => 'completada', 'progress' => 70]);

        $this->task->refresh();
        $this->assertSame(TaskStatus::Completada, $this->task->status);
        $this->assertSame(100, $this->task->progress);
        $this->assertNotNull($this->task->completed_at);
    }

    public function test_incoherent_progress_is_rejected(): void
    {
        $this->actingAs($this->member)
            ->patch($this->progressUrl(), ['status' => 'en_progreso', 'progress' => 0])
            ->assertSessionHas('error');

        $this->actingAs($this->member)
            ->patch($this->progressUrl(), ['status' => 'en_progreso', 'progress' => 150])
            ->assertSessionHasErrors('progress');

        $this->assertSame(TaskStatus::Pendiente, $this->task->fresh()->status);
    }

    public function test_other_members_and_the_teacher_cannot_update_progress(): void
    {
        $this->actingAs($this->outsider)->patch($this->progressUrl(), ['status' => 'en_progreso', 'progress' => 50])->assertForbidden();
        $this->actingAs($this->teacher)->patch($this->progressUrl(), ['status' => 'en_progreso', 'progress' => 50])->assertForbidden();

        $this->task->update(['assigned_to' => $this->leader->id]);
        $this->actingAs($this->member)->patch($this->progressUrl(), ['status' => 'en_progreso', 'progress' => 50])->assertForbidden();
    }

    public function test_the_leader_can_update_any_task_progress(): void
    {
        $this->actingAs($this->leader)
            ->patch($this->progressUrl(), ['status' => 'en_progreso', 'progress' => 55])
            ->assertSessionHas('success');

        $this->assertSame(55, $this->task->fresh()->progress);
    }

    public function test_an_overdue_task_stays_overdue_until_completed(): void
    {
        $this->task->update(['status' => TaskStatus::Vencida, 'start_date' => now()->subDays(10), 'due_date' => now()->subDay()]);

        $this->actingAs($this->member)->patch($this->progressUrl(), ['status' => 'en_progreso', 'progress' => 80]);
        $this->assertSame(TaskStatus::Vencida, $this->task->fresh()->status);
        $this->assertSame(80, $this->task->fresh()->progress);

        $this->actingAs($this->member)->patch($this->progressUrl(), ['status' => 'completada', 'progress' => 80]);
        $this->assertSame(TaskStatus::Completada, $this->task->fresh()->status);
    }

    public function test_my_tasks_lists_only_my_assigned_tasks(): void
    {
        $mine = $this->task;
        $notMine = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->leader->id, 'title' => 'Tarea del líder']);

        $this->actingAs($this->member)->get(route('tasks.mine'))
            ->assertOk()->assertSee($mine->title)->assertDontSee($notMine->title);
    }
}
