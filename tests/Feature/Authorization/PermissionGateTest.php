<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_permissions(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);

        $this->assertTrue($student->can('proyecto.crear'));
        $this->assertTrue($student->can('tarea.cambiar_estado'));
        $this->assertFalse($student->can('tarea.asignar'));
        $this->assertFalse($student->can('proyecto.gestionar_integrantes'));
        $this->assertFalse($student->can('auditoria.ver'));
    }

    public function test_leader_accumulates_student_and_leader_permissions(): void
    {
        $leader = $this->userWithRoles(Role::ESTUDIANTE, Role::LIDER);

        $this->assertTrue($leader->can('proyecto.crear'));
        $this->assertTrue($leader->can('tarea.asignar'));
        $this->assertTrue($leader->can('proyecto.gestionar_integrantes'));
        $this->assertFalse($leader->can('auditoria.ver'));
    }

    public function test_teacher_permissions(): void
    {
        $teacher = $this->userWithRoles(Role::DOCENTE);

        $this->assertTrue($teacher->can('auditoria.ver'));
        $this->assertTrue($teacher->can('comentario.crear'));
        $this->assertFalse($teacher->can('proyecto.crear'));
        $this->assertFalse($teacher->can('tarea.asignar'));
    }

    public function test_reserved_permission_is_not_granted_to_any_initial_role(): void
    {
        foreach ([Role::ESTUDIANTE, Role::LIDER, Role::DOCENTE] as $role) {
            $this->assertFalse($this->userWithRoles($role)->can('rol.gestionar'));
        }
    }

    public function test_a_new_role_works_without_code_changes(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $coordinator = Role::create(['name' => 'COORDINADOR', 'display_name' => 'Coordinador']);
        $coordinator->permissions()->attach(Permission::where('name', 'auditoria.ver')->value('id'));
        $user->roles()->attach($coordinator->id, ['created_at' => now()]);
        $user->flushRolesCache();

        $this->assertTrue($user->can('auditoria.ver'));
    }
}
