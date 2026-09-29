<?php

/*
| Etiquetas legibles del módulo de auditoría. Las claves son los valores guardados en BD
| (audits.module y audits.action); agregar una acción nueva = agregar su etiqueta aquí.
*/

return [
    'modules' => [
        'auth' => 'Autenticación',
        'roles' => 'Roles y permisos',
        'proyectos' => 'Proyectos',
        'integrantes' => 'Integrantes',
        'tareas' => 'Tareas',
        'comentarios' => 'Comentarios',
    ],

    'actions' => [
        'auth.login' => 'Inicio de sesión',
        'auth.logout' => 'Cierre de sesión',
        'auth.registered' => 'Registro de usuario',
        'auth.password_reset' => 'Restablecimiento de contraseña',
        'role.assigned' => 'Rol asignado',
        'role.revoked' => 'Rol retirado',
        'project.created' => 'Proyecto creado',
        'project.updated' => 'Proyecto modificado',
        'project.status_changed' => 'Cambio de estado del proyecto',
        'project.deleted' => 'Proyecto eliminado',
        'project.leader_changed' => 'Cambio de líder',
        'member.added' => 'Integrante agregado',
        'member.removed' => 'Integrante retirado',
        'task.created' => 'Tarea creada',
        'task.updated' => 'Tarea modificada',
        'task.assigned' => 'Tarea asignada',
        'task.status_changed' => 'Cambio de estado de tarea',
        'task.marked_overdue' => 'Tarea marcada como vencida',
        'task.deleted' => 'Tarea eliminada',
        'task.restored' => 'Tarea restaurada',
        'comment.created' => 'Comentario creado',
        'comment.updated' => 'Comentario modificado',
        'comment.deleted' => 'Comentario eliminado',
    ],

    'entities' => [
        'App\\Models\\Project' => 'Proyecto',
        'App\\Models\\Task' => 'Tarea',
        'App\\Models\\Comment' => 'Comentario',
        'App\\Models\\User' => 'Usuario',
    ],
];
