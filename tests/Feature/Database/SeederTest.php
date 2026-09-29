<?php

namespace Tests\Feature\Database;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_coherent_with_business_rules(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, User::count());
        $project = Project::sole();

        $this->assertTrue($project->members->contains($project->leader), 'El líder es integrante.');
        $this->assertTrue($project->leader->hasRole(Role::LIDER));
        $this->assertTrue($project->teacher->hasRole(Role::DOCENTE));
        $this->assertSame(5, $project->tasks()->count());
        $this->assertGreaterThan(0, $project->comments()->count());
        $this->assertGreaterThan(0, \DB::table('audits')->count());
        $this->assertGreaterThan(0, \DB::table('notifications')->count());

        // Cada tarea respeta la coherencia estado/avance/fecha (docs/03, §4).
        Task::all()->each(fn (Task $task) => $this->assertTrue(match ($task->status) {
            TaskStatus::Pendiente => $task->progress === 0,
            TaskStatus::EnProgreso => $task->progress >= 1 && $task->progress <= 99,
            TaskStatus::Completada => $task->progress === 100 && $task->completed_at !== null,
            TaskStatus::Vencida => $task->progress < 100 && $task->due_date->lt(today()),
        }, "Tarea incoherente: {$task->title} ({$task->status->value}, {$task->progress}%)"));

        Mail::assertNothingOutgoing(); // sembrar nunca envía correos reales
    }

    public function test_seeding_twice_does_not_duplicate_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = [User::count(), Project::count(), Task::count(), \DB::table('comments')->count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [User::count(), Project::count(), Task::count(), \DB::table('comments')->count()]);
    }
}
