<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Catálogo de roles y permisos (docs/03, §1). Idempotente: puede ejecutarse varias veces.
 * Agregar un rol nuevo = agregar una entrada en ROLES y ROLE_PERMISSIONS; no requiere cambiar código.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> nombre => [módulo, descripción] */
    private const PERMISSIONS = [
        'proyecto.ver' => ['proyecto', 'Consultar proyectos'],
        'proyecto.crear' => ['proyecto', 'Crear proyectos'],
        'proyecto.editar' => ['proyecto', 'Editar información general del proyecto'],
        'proyecto.cambiar_estado' => ['proyecto', 'Cambiar el estado del proyecto'],
        'proyecto.eliminar' => ['proyecto', 'Eliminar proyectos'],
        'proyecto.gestionar_integrantes' => ['proyecto', 'Agregar y retirar integrantes, cambiar líder'],
        'tarea.ver' => ['tarea', 'Consultar tareas'],
        'tarea.crear' => ['tarea', 'Crear tareas'],
        'tarea.editar' => ['tarea', 'Editar tareas'],
        'tarea.asignar' => ['tarea', 'Asignar responsables a tareas'],
        'tarea.eliminar' => ['tarea', 'Eliminar (lógicamente) y restaurar tareas'],
        'tarea.cambiar_estado' => ['tarea', 'Cambiar estado y avance de tareas'],
        'comentario.ver' => ['comentario', 'Consultar comentarios'],
        'comentario.crear' => ['comentario', 'Crear comentarios'],
        'comentario.editar' => ['comentario', 'Editar comentarios propios'],
        'comentario.eliminar' => ['comentario', 'Eliminar (lógicamente) comentarios propios'],
        'notificacion.ver' => ['notificacion', 'Consultar notificaciones propias'],
        'notificacion.marcar_leida' => ['notificacion', 'Marcar notificaciones como leídas'],
        'auditoria.ver' => ['auditoria', 'Consultar la auditoría de los proyectos supervisados'],
        'auditoria.ver_todo' => ['auditoria', 'Consultar toda la auditoría del sistema'],
        'rol.gestionar' => ['rol', 'Asignar y retirar roles de los usuarios'],
        'sistema.administrar' => ['sistema', 'Acceso global de administración a todos los proyectos y registros'],
    ];

    /** @var array<string, array{0: string, 1: string}> nombre => [nombre visible, descripción] */
    private const ROLES = [
        Role::ESTUDIANTE => ['Estudiante', 'Integrante de proyectos académicos'],
        Role::LIDER => ['Líder de proyecto', 'Estudiante con permisos de gestión sobre los proyectos que lidera'],
        Role::DOCENTE => ['Docente', 'Supervisa y hace seguimiento a proyectos'],
        Role::ADMINISTRADOR => ['Administrador', 'Administra el sistema: acceso global y gestión de roles'],
    ];

    /** Comodín: el rol recibe todos los permisos del catálogo, incluidos los que se agreguen en el futuro. */
    private const ALL = '*';

    /**
     * LIDER solo declara sus permisos adicionales: un líder siempre tiene también el rol ESTUDIANTE
     * y los permisos de sus roles se suman.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        Role::ESTUDIANTE => [
            'proyecto.ver', 'proyecto.crear',
            'tarea.ver', 'tarea.cambiar_estado',
            'comentario.ver', 'comentario.crear', 'comentario.editar', 'comentario.eliminar',
            'notificacion.ver', 'notificacion.marcar_leida',
        ],
        Role::LIDER => [
            'proyecto.editar', 'proyecto.cambiar_estado', 'proyecto.eliminar', 'proyecto.gestionar_integrantes',
            'tarea.crear', 'tarea.editar', 'tarea.asignar', 'tarea.eliminar',
        ],
        Role::DOCENTE => [
            'proyecto.ver', 'proyecto.cambiar_estado',
            'tarea.ver',
            'comentario.ver', 'comentario.crear', 'comentario.editar', 'comentario.eliminar',
            'notificacion.ver', 'notificacion.marcar_leida',
            'auditoria.ver',
        ],
        Role::ADMINISTRADOR => [self::ALL],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => [$module, $description]) {
            Permission::updateOrCreate(['name' => $name], ['module' => $module, 'description' => $description]);
        }

        foreach (self::ROLES as $name => [$displayName, $description]) {
            $role = Role::updateOrCreate(['name' => $name], ['display_name' => $displayName, 'description' => $description]);

            $permissions = self::ROLE_PERMISSIONS[$name] === [self::ALL]
                ? Permission::pluck('id')
                : Permission::whereIn('name', self::ROLE_PERMISSIONS[$name])->pluck('id');

            $role->permissions()->sync($permissions);
        }
    }
}
