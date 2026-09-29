<?php

namespace Tests\Feature\Database;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Audit;
use App\Models\Comment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Verifica el esquema de la Fase 3: relaciones Eloquent, casts e integridad.
 */
class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_are_many_to_many(): void
    {
        $role = Role::create(['name' => Role::LIDER, 'display_name' => 'Líder']);
        $permission = Permission::create(['name' => 'tarea.asignar', 'module' => 'tarea']);
        $user = User::factory()->create();

        $role->permissions()->attach($permission);
        $user->roles()->attach($role, ['created_at' => now()]);

        $this->assertTrue($user->roles()->first()->permissions->contains($permission));
        $this->assertTrue($permission->roles->contains($role));
    }

    public function test_project_relations_and_casts(): void
    {
        $teacher = User::factory()->create();
        $project = Project::factory()->withLeaderAsMember()->create(['teacher_id' => $teacher->id]);

        $this->assertInstanceOf(ProjectStatus::class, $project->status);
        $this->assertTrue($project->members->contains($project->leader));
        $this->assertTrue($project->leader->ledProjects->contains($project));
        $this->assertTrue($teacher->supervisedProjects->contains($project));
        $this->assertTrue($project->leader->projects->contains($project));
    }

    public function test_a_user_cannot_be_member_of_the_same_project_twice(): void
    {
        $project = Project::factory()->withLeaderAsMember()->create();

        $this->expectException(QueryException::class);

        $project->members()->attach($project->leader_id);
    }

    public function test_task_and_comment_relations(): void
    {
        $member = User::factory()->create();
        $task = Task::factory()->completed()->create(['assigned_to' => $member->id]);
        $comment = Comment::factory()->observation()->create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
        ]);

        $this->assertSame(TaskStatus::Completada, $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertTrue($member->assignedTasks->contains($task));
        $this->assertTrue($task->project->tasks->contains($task));
        $this->assertTrue($task->comments->contains($comment));
        $this->assertTrue($task->project->comments->contains($comment));
        $this->assertTrue($comment->is_observation);
    }

    public function test_project_soft_delete_keeps_its_tasks(): void
    {
        $task = Task::factory()->create();

        $task->project->delete();

        $this->assertSoftDeleted('projects', ['id' => $task->project_id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_audit_records_are_immutable(): void
    {
        $audit = Audit::create([
            'action' => 'project.created',
            'module' => 'proyectos',
            'new_values' => ['title' => 'Demo'],
        ]);

        $this->assertSame(['title' => 'Demo'], $audit->new_values);

        try {
            $audit->update(['action' => 'otro']);
            $this->fail('Se permitió modificar un registro de auditoría.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $audit->delete();
    }

    public function test_project_status_transitions_are_defined_in_the_enum(): void
    {
        $this->assertTrue(ProjectStatus::Planeacion->canTransitionTo(ProjectStatus::EnProgreso));
        $this->assertFalse(ProjectStatus::Planeacion->canTransitionTo(ProjectStatus::Finalizado));
        $this->assertTrue(ProjectStatus::Finalizado->isFinal());
        $this->assertNotContains(ProjectStatus::Cancelado, ProjectStatus::active());
    }
}
