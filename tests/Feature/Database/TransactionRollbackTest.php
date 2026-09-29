<?php

namespace Tests\Feature\Database;

use App\Models\Role;
use App\Repositories\Contracts\AuditRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Si una operación crítica falla a mitad de camino, no deben quedar datos parciales (enunciado §19).
 */
class TransactionRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failure_while_auditing_rolls_back_the_whole_project_creation(): void
    {
        Notification::fake();
        $student = $this->userWithRoles(Role::ESTUDIANTE);
        $classmate = $this->userWithRoles(Role::ESTUDIANTE);
        $teacher = $this->userWithRoles(Role::DOCENTE);

        // Simula una falla de infraestructura DESPUÉS de insertar el proyecto y su líder.
        $this->mock(AuditRepositoryInterface::class)
            ->shouldReceive('create')
            ->andThrow(new RuntimeException('Fallo simulado al registrar auditoría'));

        $this->actingAs($student)->post('/projects', [
            'title' => 'Proyecto', 'description' => 'Desc', 'start_date' => '2026-10-01',
            'teacher_id' => $teacher->id, 'member_ids' => [$classmate->id],
        ])->assertStatus(500);

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_members', 0);
        $this->assertFalse($student->fresh()->hasRole(Role::LIDER));
        Notification::assertNothingSent(); // afterCommit: no se avisa de algo que no se guardó
    }
}
