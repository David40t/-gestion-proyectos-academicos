<?php

namespace Tests\Feature\Security;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

/**
 * Campos que el usuario NO controla no pueden inyectarse agregando parámetros al formulario:
 * los Controllers solo pasan $request->validated() y los Services fijan los campos sensibles.
 */
class MassAssignmentTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    public function test_project_creation_ignores_injected_fields(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);
        $victim = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($student)->post('/projects', [
            'title' => 'Proyecto', 'description' => 'Desc', 'start_date' => '2026-10-01',
            'teacher_id' => $this->userWithRoles(Role::DOCENTE)->id,
            // Inyectados:
            'status' => 'finalizado', 'leader_id' => $victim->id, 'created_by' => $victim->id, 'id' => 999,
        ]);

        $project = Project::sole();
        $this->assertSame(ProjectStatus::Planeacion, $project->status);
        $this->assertSame($student->id, $project->leader_id);
        $this->assertSame($student->id, $project->created_by);
        $this->assertNotSame(999, $project->id);
    }

    public function test_project_update_cannot_change_leader_or_status(): void
    {
        $this->createProjectScenario();

        $this->actingAs($this->leader)->put(route('projects.update', $this->project), [
            'title' => 'Editado', 'description' => 'Desc', 'start_date' => $this->project->start_date->toDateString(),
            'teacher_id' => $this->teacher->id,
            'leader_id' => $this->member->id, 'status' => 'finalizado',
        ]);

        $project = $this->project->fresh();
        $this->assertSame('Editado', $project->title);
        $this->assertTrue($project->isLedBy($this->leader));
        $this->assertSame(ProjectStatus::Planeacion, $project->status);
    }

    public function test_progress_update_cannot_move_a_task_to_another_project_or_reassign_it(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $other = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to' => $this->member->id]);

        $this->actingAs($this->member)->patch(route('projects.tasks.progress.update', [$this->project, $task]), [
            'status' => 'en_progreso', 'progress' => 30,
            'project_id' => $other->id, 'assigned_to' => $this->outsider->id, 'title' => 'Hackeado',
        ]);

        $task->refresh();
        $this->assertSame($this->project->id, $task->project_id);
        $this->assertSame($this->member->id, $task->assigned_to);
        $this->assertNotSame('Hackeado', $task->title);
        $this->assertSame(TaskStatus::EnProgreso, $task->status);
    }
}
