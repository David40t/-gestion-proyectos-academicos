# 03 · Reglas de negocio

## 1. Roles y permisos

### Modelo de autorización en dos niveles

| Nivel | Pregunta | Dónde vive | Ejemplo |
|---|---|---|---|
| **Permiso** (por rol, en BD) | ¿Este *tipo* de usuario puede hacer X? | `permissions` + `Gate::before` | ¿Tiene `tarea.asignar`? |
| **Policy** (por registro) | ¿Puede hacerlo sobre *este* registro? | `app/Policies` | ¿Es el líder de *este* proyecto? |

Una acción se permite solo si **ambos niveles** la aprueban. Cada Policy consulta primero el permiso
(`$user->hasPermission('tarea.asignar')`) y luego la relación del usuario con el registro.

Para agregar un rol nuevo (p. ej. `COORDINADOR`) basta con insertar el rol, asociarle permisos y, solo si
necesita reglas por registro distintas, ampliar la Policy correspondiente. **No hay `if ($user->role == ...)`
en los Controllers.**

### Catálogo de permisos y matriz inicial

| Permiso | ESTUDIANTE | LIDER | DOCENTE | Restricción adicional (Policy) |
|---|:-:|:-:|:-:|---|
| proyecto.ver | ✔ | ✔ | ✔ | Integrante del proyecto, o su docente responsable |
| proyecto.crear | ✔ | ✔ | | Quien lo crea queda como líder |
| proyecto.editar | | ✔ | | Líder de *ese* proyecto; proyecto no finalizado ni cancelado |
| proyecto.cambiar_estado | | ✔ | ✔ | Líder o docente del proyecto; transición válida (§2) |
| proyecto.eliminar | | ✔ | | Líder, y proyecto en `planeacion` sin tareas |
| proyecto.gestionar_integrantes | | ✔ | | Líder de *ese* proyecto |
| tarea.ver | ✔ | ✔ | ✔ | Integrante o docente del proyecto |
| tarea.crear | | ✔ | | Líder del proyecto |
| tarea.editar | | ✔ | | Líder del proyecto |
| tarea.asignar | | ✔ | | Líder; el responsable debe ser integrante |
| tarea.cambiar_estado | ✔ | ✔ | | Responsable de la tarea, o líder del proyecto |
| comentario.ver | ✔ | ✔ | ✔ | Acceso al proyecto |
| comentario.crear | ✔ | ✔ | ✔ | Acceso al proyecto; `is_observation` solo el docente |
| comentario.editar | ✔ | ✔ | ✔ | Solo el autor del comentario |
| notificacion.ver | ✔ | ✔ | ✔ | Solo las propias |
| notificacion.marcar_leida | ✔ | ✔ | ✔ | Solo las propias |
| auditoria.ver | | | ✔ | Docente: auditoría de sus proyectos supervisados |
| rol.gestionar | | | | Reservado para un futuro rol administrador |

**Roles acumulativos:** un usuario puede tener varios roles (`role_user` es N:M) y sus permisos se suman.
Un líder tiene los roles `ESTUDIANTE` y `LIDER`. En la base de datos, `LIDER` solo tiene sus permisos
*adicionales*; la columna LIDER de la matriz muestra el resultado acumulado. El catálogo está en
`database/seeders/RolePermissionSeeder.php`.

### Rol LIDER por proyecto (ADR-006)
- Quien crea un proyecto queda como su líder (`leader_id`) y como integrante.
- Cuando un estudiante pasa a liderar algún proyecto, `RoleService` le asigna el rol `LIDER`.
  Cuando deja de liderar todos sus proyectos, se le retira. Ambos cambios se **auditan** (`role.assigned` / `role.revoked`).
- Tener el rol `LIDER` **no** permite gestionar proyectos ajenos: la Policy siempre exige `leader_id == user.id`.

### Registro de usuarios
El registro público crea siempre usuarios con rol `ESTUDIANTE`. Los docentes se crean por seeder
(o, en el futuro, por un administrador). Nadie puede autoasignarse el rol `DOCENTE`.

## 2. Proyectos

### Estados y transiciones (`App\Enums\ProjectStatus`)

```
planeacion ──▶ en_progreso ──▶ en_revision ──▶ finalizado
    │               │   ▲            │
    │               │   └────────────┘ (el docente devuelve con observaciones)
    ▼               ▼
 cancelado ◀────────┘
```

| Desde | Hacia | Quién |
|---|---|---|
| planeacion | en_progreso, cancelado | Líder |
| en_progreso | en_revision, cancelado | Líder |
| en_revision | en_progreso, finalizado | Docente (el líder puede retirar la entrega a en_progreso) |
| finalizado, cancelado | — (estados finales) | — |

El mapa de transiciones vive en el Enum (`allowedTransitions()`). Para modificar los estados se edita
un solo archivo.

### Validaciones
- `end_date ≥ start_date`.
- `teacher_id` debe tener el rol `DOCENTE`.
- No se edita un proyecto finalizado o cancelado.

## 3. Integrantes

- No se duplica un integrante: lo impide el UNIQUE en BD y además se valida antes con un mensaje claro.
- Solo se agregan usuarios con rol `ESTUDIANTE`. El docente participa como `teacher_id`, no como integrante.
- **El líder no puede ser retirado.** Primero hay que transferir el liderazgo a otro integrante (`changeLeader`).
- Al retirar a un integrante, sus tareas no completadas quedan **sin responsable** (`assigned_to = NULL`) y se
  notifica al líder. Todo ocurre en una sola transacción.
- Al cambiar de líder, el líder anterior sigue como integrante y se recalcula el rol `LIDER` de ambos usuarios.

## 4. Tareas

### Estados (`TaskStatus`) y coherencia con el avance

| Estado | Avance permitido | Efecto |
|---|---|---|
| pendiente | 0 | — |
| en_progreso | 1–99 | — |
| completada | 100 (se fija automáticamente) | `completed_at = now()` |
| vencida | 0–99 (se conserva) | Lo asigna **solo el sistema** |

- `vencida` no se elige manualmente. La asigna el comando diario `tasks:check-deadlines` cuando
  `due_date < hoy` y la tarea no está completada.
- Una tarea vencida puede pasar a `completada` (entrega tardía). Si el líder amplía `due_date` a una fecha
  futura, la tarea vuelve a `en_progreso` o `pendiente` según su avance.
- Si se registra un avance mayor a 0 en una tarea pendiente, pasa automáticamente a `en_progreso`.
  Si se registra 100, pasa a `completada`.
- **Fechas:** `due_date ≥ start_date`, y ambas dentro del rango del proyecto cuando este tiene `end_date`.
- **Prioridad** (`TaskPriority`): baja, media, alta.
- **Implementación:** la regla vive en un solo lugar, `App\Services\Tasks\TaskStateResolver` (clase pura con
  pruebas unitarias). `TaskService` la aplica en toda creación, edición o registro de avance. Así la
  coherencia no depende del formulario ni del JavaScript.
- Al crear una tarea, la fecha límite no puede ser anterior a hoy. El responsable debe ser integrante del proyecto.
- El **estudiante responsable** solo puede cambiar `status` y `progress` de su tarea. El resto de campos
  los edita el líder.

## 5. Seguimiento (calculado, no almacenado)

`ProgressService` calcula todo con consultas agregadas:
- **Avance del proyecto** = promedio del `progress` de sus tareas. Si no tiene tareas, el avance es 0.
- Conteo de tareas por estado: completadas, pendientes, en progreso y vencidas.
- Próximas fechas límite: tareas no completadas con `due_date` en los próximos 7 días.

Si el cálculo llegara a ser costoso, la optimización sería cachearlo. Se descartó persistir el valor
porque podría quedar desincronizado.

## 6. Comentarios
- Asociados a un proyecto y, opcionalmente, a una tarea del mismo proyecto.
- Registran autor, fecha y contenido. Solo el autor puede editarlos (la edición se audita).
- `is_observation = true` solo lo puede marcar un usuario con rol `DOCENTE` sobre sus proyectos supervisados.

## 7. Catálogo de notificaciones

Canal **database** = notificación dentro del sistema. Canal **mail** = correo, siempre en cola (`ShouldQueue`).

| Evento | Destinatarios | database | mail |
|---|---|:-:|:-:|
| Cambio de estado del proyecto | Integrantes + docente | ✔ | Solo si pasa a finalizado o cancelado |
| Modificación importante del proyecto (fechas, docente) | Integrantes | ✔ | |
| Cambio de líder | Integrantes + docente | ✔ | Nuevo líder |
| Integrante agregado | Integrante agregado | ✔ | ✔ |
| Integrante retirado | Integrante retirado | ✔ | ✔ |
| Tarea asignada | Responsable | ✔ | ✔ |
| Tarea modificada por otro usuario | Responsable | ✔ | |
| Tarea completada | Líder | ✔ | |
| Tarea próxima a vencer (≤ 2 días, una sola vez) | Responsable | ✔ | ✔ |
| Tarea vencida | Responsable + líder | ✔ | ✔ |
| Comentario del docente | Integrantes | ✔ | |
| Observación del docente (`is_observation`) | Integrantes | ✔ | ✔ |
| Comentario de un estudiante | Líder + docente | ✔ | |

- Nunca se notifica al propio autor de la acción.
- Para agregar un canal nuevo basta con añadirlo en el método `via()` de la notificación. Los Services no cambian.

## 8. Catálogo de acciones auditadas

| Módulo | Acciones |
|---|---|
| auth | `auth.login`, `auth.logout`, `auth.registered`, `auth.password_reset` |
| proyectos | `project.created`, `project.updated`, `project.status_changed`, `project.deleted`, `project.leader_changed` |
| integrantes | `member.added`, `member.removed` |
| tareas | `task.created`, `task.updated`, `task.assigned`, `task.status_changed`, `task.marked_overdue` |
| comentarios | `comment.created`, `comment.updated` |
| roles | `role.assigned`, `role.revoked`, `permission.changed` |

- **Solo lectura:** no existen rutas, Policies ni métodos de Repository para editar o borrar auditoría.
  El modelo `Audit` lanza una excepción si se intenta actualizar o borrar un registro.
- **Datos sensibles:** `AuditService` elimina `password`, `remember_token` y cualquier campo `*token*`
  antes de guardar. En los cambios se guardan solo los campos modificados (`getChanges()`).
- **IP y user agent** se toman del request cuando existe. Las acciones del Scheduler quedan con `user_id` e IP en NULL.

## 9. Mapa de rutas (borrador)

| Método | URI | Controller | Middleware |
|---|---|---|---|
| GET | /dashboard | DashboardController | auth |
| resource | /projects | ProjectController (index, create, store, show, edit, update, destroy) | auth |
| PATCH | /projects/{project}/status | ProjectStatusController | auth |
| POST / DELETE | /projects/{project}/members[/{user}] | ProjectMemberController | auth |
| PATCH | /projects/{project}/leader | ProjectMemberController@updateLeader | auth |
| resource (anidado, *scoped*, sin destroy) | /projects/{project}/tasks | TaskController | auth |
| PATCH | /projects/{project}/tasks/{task}/progress | TaskProgressController | auth |
| GET | /my-tasks | MyTaskController | auth |
| POST / PATCH | /projects/{project}/comments[/{comment}] | CommentController | auth |
| GET, PATCH | /notifications, /notifications/{id}/read, /notifications/read-all | NotificationController | auth |
| GET | /audits | AuditController | auth |

La autorización fina no se hace en las rutas: cada Controller llama a su Policy.
