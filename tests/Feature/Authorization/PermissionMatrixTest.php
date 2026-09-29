<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La matriz documentada en docs/03-reglas-de-negocio.md §1 debe coincidir EXACTAMENTE
 * con los permisos sembrados. Si alguien cambia uno sin actualizar la documentación, esta prueba falla.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Permisos efectivos (acumulados) por combinación de roles, según docs/03. */
    private const MATRIX = [
        'ESTUDIANTE' => [
            'comentario.crear', 'comentario.editar', 'comentario.eliminar', 'comentario.ver',
            'notificacion.marcar_leida', 'notificacion.ver',
            'proyecto.crear', 'proyecto.ver',
            'tarea.cambiar_estado', 'tarea.ver',
        ],
        'ESTUDIANTE+LIDER' => [
            'comentario.crear', 'comentario.editar', 'comentario.eliminar', 'comentario.ver',
            'notificacion.marcar_leida', 'notificacion.ver',
            'proyecto.cambiar_estado', 'proyecto.crear', 'proyecto.editar', 'proyecto.eliminar',
            'proyecto.gestionar_integrantes', 'proyecto.ver',
            'tarea.asignar', 'tarea.cambiar_estado', 'tarea.crear', 'tarea.editar', 'tarea.eliminar', 'tarea.ver',
        ],
        'DOCENTE' => [
            'auditoria.ver',
            'comentario.crear', 'comentario.editar', 'comentario.eliminar', 'comentario.ver',
            'notificacion.marcar_leida', 'notificacion.ver',
            'proyecto.cambiar_estado', 'proyecto.ver',
            'tarea.ver',
        ],
    ];

    /** Permisos que ningún rol inicial debe tener (reservados para un futuro administrador). */
    private const RESERVED = ['rol.gestionar', 'auditoria.ver_todo'];

    public function test_effective_permissions_match_the_documented_matrix(): void
    {
        foreach (self::MATRIX as $combination => $expected) {
            $user = $this->userWithRoles(...explode('+', $combination));

            $this->assertSame($expected, $user->permissionNames()->sort()->values()->all(), "Permisos de {$combination}");
        }
    }

    public function test_reserved_permissions_exist_but_are_unassigned(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (self::RESERVED as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
            foreach ([Role::ESTUDIANTE, Role::LIDER, Role::DOCENTE] as $role) {
                $this->assertFalse($this->userWithRoles($role)->hasPermission($permission), "{$role} no debe tener {$permission}");
            }
        }
    }

    public function test_the_catalog_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $counts = [\DB::table('roles')->count(), \DB::table('permissions')->count(), \DB::table('permission_role')->count()];

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($counts, [\DB::table('roles')->count(), \DB::table('permissions')->count(), \DB::table('permission_role')->count()]);
    }
}
