<?php

namespace Tests\Feature\Tasks;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckTaskDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_past_due_open_tasks_as_overdue(): void
    {
        $project = Project::factory()->create();
        $factory = Task::factory()->state(['project_id' => $project->id, 'start_date' => now()->subDays(10)]);

        $late = $factory->inProgress(40)->create(['due_date' => now()->subDay()]);
        $onTime = $factory->create(['due_date' => now()]);
        $doneLate = $factory->completed()->create(['due_date' => now()->subDay()]);

        $this->artisan('tasks:check-deadlines')
            ->expectsOutputToContain('Tareas marcadas como vencidas: 1')
            ->assertSuccessful();

        $this->assertSame(TaskStatus::Vencida, $late->fresh()->status);
        $this->assertSame(40, $late->fresh()->progress);
        $this->assertSame(TaskStatus::Pendiente, $onTime->fresh()->status);
        $this->assertSame(TaskStatus::Completada, $doneLate->fresh()->status);
        $this->assertDatabaseHas('audits', ['action' => 'task.marked_overdue', 'auditable_id' => $late->id, 'user_id' => null]);
    }

    public function test_it_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('tasks:check-deadlines')->assertSuccessful();
    }
}
