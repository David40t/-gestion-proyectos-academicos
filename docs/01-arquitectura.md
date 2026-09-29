# 01 · Arquitectura del sistema

Sistema web para la gestión y seguimiento de proyectos académicos de estudiantes universitarios.

## 1. Trazabilidad arquitectónica

| Nivel | Contenido |
|---|---|
| **Problema** | Información de proyectos académicos dispersa (chats, correos, hojas de cálculo) y dificultad del docente para hacer seguimiento del avance real. |
| **Requerimientos** | Gestión de proyectos, integrantes, tareas, seguimiento, comentarios, notificaciones, auditoría, roles y permisos. |
| **Atributos de calidad** | Seguridad · Mantenibilidad · Escalabilidad · Usabilidad · Rendimiento · Trazabilidad |
| **Decisiones** | Laravel · MVC · Capas · Service Layer · Repository Pattern · Policies/Gates · Notificaciones nativas · Auditoría propia · MySQL/MariaDB |
| **Componentes** | Blade · Controllers · Form Requests · Policies · Services · Repositories · Models · Notifications · Commands (Scheduler) · Base de datos |
| **Tecnologías** | PHP 8.5 · Laravel 13 · Blade/HTML/CSS/JS · MySQL 8 (compatible MariaDB) · Eloquent · Git/GitHub · draw.io |

### Atributo de calidad → decisión que lo atiende

| Atributo | Decisión / táctica |
|---|---|
| Seguridad | Fortify (auth nativa), CSRF, hash bcrypt, Policies + permisos validados en backend, Form Requests, `$fillable` explícito, auditoría inmutable. |
| Mantenibilidad | Capas con responsabilidad única, Services pequeños por caso de uso, Repositories con interfaces, estados centralizados en Enums. |
| Escalabilidad | Monolito modular (módulos con fronteras claras), colas para correos, roles/permisos configurables por datos. |
| Usabilidad | Interfaz Blade simple, dashboard por rol, notificaciones dentro del sistema. |
| Rendimiento | Índices en columnas de filtrado, *eager loading* en repositories, avance calculado con agregados SQL, correos en cola. |
| Trazabilidad | Módulo de auditoría (quién, qué, cuándo, desde dónde, valores antes/después). |

## 2. Estilo arquitectónico

**Monolito modular + arquitectura en capas + MVC.** Una sola aplicación desplegable, organizada en módulos
funcionales y, dentro de cada módulo, en capas con dependencia estrictamente descendente.

```
Usuario
  │
  ▼
Presentación ── Blade · HTML · CSS · JavaScript
  │
  ▼
Routes ──────── routes/web.php + middleware (auth, verified, throttle)
  │
  ▼
Controllers ─── reciben la petición, Form Request valida, $this->authorize() / Policy
  │
  ▼
Services ────── reglas de negocio, transacciones, auditoría, notificaciones
  │
  ▼
Repositories ── consultas Eloquent (interfaces en Contracts/, implementación en Eloquent/)
  │
  ▼
Models ──────── entidades, relaciones, casts
  │
  ▼
MySQL / MariaDB
```

**Regla de dependencia:** cada capa conoce solo la capa inmediatamente inferior (los Models son compartidos
como objetos de datos). Un Controller nunca consulta un Repository ni hace `Model::where(...)`; una vista
Blade nunca ejecuta consultas.

### Responsabilidades por capa

| Capa | Hace | No hace |
|---|---|---|
| Blade | Mostrar datos ya preparados, `@can` para ocultar acciones | Consultas, reglas de negocio, autorización real |
| Routes | Mapear URL → Controller, aplicar middleware | Lógica |
| Form Requests | Validar formato, tipos, fechas, existencia de relaciones | Reglas que dependen del estado del negocio |
| Policies | Decidir si *este usuario* puede hacer *esta acción* sobre *este registro* | Modificar datos |
| Controllers | Orquestar HTTP: validar → autorizar → invocar Service → responder | Reglas de negocio, consultas |
| Services | Reglas de negocio, transacciones, auditoría, notificaciones | SQL/Eloquent complejo, HTTP |
| Repositories | Encapsular consultas Eloquent | Reglas de negocio |
| Models | Relaciones, casts, scopes simples | Procesos de negocio |

## 3. Módulos

| # | Módulo | Controllers | Services | Repositories | Otros |
|---|---|---|---|---|---|
| 1 | Autenticación | (Fortify) | — | — | Vistas `auth/`, Listeners Login/Logout |
| 2 | Usuarios | — | `UserService`* | `UserRepository` | `User` |
| 3 | Roles y permisos | — | `RoleService` | `RoleRepository` | `Role`, `Permission`, `Gate::before` en `AppServiceProvider` |
| 4 | Proyectos | `ProjectController` | `ProjectService` | `ProjectRepository` | `ProjectPolicy`, `ProjectStatus` |
| 5 | Integrantes | `ProjectMemberController` | `ProjectMemberService` | `ProjectMemberRepository` | — |
| 6 | Tareas | `TaskController`, `TaskProgressController`, `MyTaskController` | `TaskService`, `Tasks\TaskStateResolver` | `TaskRepository` | `TaskPolicy`, `TaskStatus`, `TaskPriority` |
| 7 | Seguimiento | `ProjectController@show`, `DashboardController` | `ProgressService`, `DashboardService` | `TaskRepository` (`deadlineStats`, `nextOpen`), `ProjectRepository` (`withStats*`), `CommentRepository`, `AuditRepository` | Comando `tasks:check-deadlines` (diario, `routes/console.php`) |
| 8 | Comentarios | `CommentController` | `CommentService` | `CommentRepository` | `CommentPolicy` |
| 9 | Notificaciones | `NotificationController` | `Notifications\NotificationDispatcher`, `Notifications\NotificationService`, `TaskDeadlineService` | `NotificationRepository` | `AppNotification` y clases en `app/Notifications`, `NavigationComposer` |
| 10 | Auditoría | `AuditController` (solo index/show) | `AuditService` (registro), `AuditQueryService` (consulta) | `AuditRepository` | `AuditPolicy`, Listeners, `lang/es/audit.php` (etiquetas) |

\* Solo si se requiere lógica de usuarios más allá del registro de Fortify (p. ej. listar docentes para un select).

**Relación entre módulos:** los Services pueden colaborar entre sí por inyección de dependencias
(p. ej. `ProjectService` usa `ProjectMemberService` y `AuditService`), pero nunca acceden al Repository de
otro módulo saltándose su Service cuando hay regla de negocio de por medio.

## 4. Flujo estándar de una acción importante

Ejemplo: *el docente agrega una observación a un proyecto*.

```
Docente ──POST /projects/{project}/comments──▶ routes/web.php [auth]
   │
   ▼
CommentController@store
   ├─ StoreCommentRequest       → valida contenido, task_id pertenece al proyecto
   ├─ authorize('create', [Comment::class, $project]) → CommentPolicy
   └─ CommentService::create($project, $author, $data)
          │
          ├─ DB::transaction
          │     ├─ CommentRepository::create()          (1) persistir
          │     └─ AuditService::record('comment.created')  (2) auditar
          │
          └─ después del commit
                └─ Notification::send(integrantes, new TeacherCommentNotification)
                        ├─ canal database  → notificación interna
                        └─ canal mail      → solo si es observación (en cola)
   ▼
redirect()->back()->with('success', ...)
```

Orden garantizado: **autorizar → regla de negocio → persistir → auditar (misma transacción) → notificar (después del commit)**.
La auditoría va dentro de la transacción para que no exista un registro de auditoría de algo que se revirtió;
las notificaciones van después del commit para no avisar de algo que no llegó a guardarse.

## 5. Estructura de carpetas

```
app/
├── Actions/Fortify/         CreateNewUser (valida y delega en UserService), ResetUserPassword
├── Console/Commands/        CheckTaskDeadlines (tasks:check-deadlines)
├── Enums/                   ProjectStatus, TaskStatus, TaskPriority
├── Http/
│   ├── Controllers/
│   └── Requests/            Project/, Task/, Comment/, Member/
├── Listeners/               RecordSuccessfulLogin, RecordLogout
├── Models/                 Concerns/HasRoles (hasRole, hasPermission)
├── Notifications/           Project/, Task/, Comment/
├── Policies/
├── Providers/               AppServiceProvider (Gate, Policies), RepositoryServiceProvider (interfaz → implementación)
├── Repositories/
│   ├── Contracts/
│   └── Eloquent/
└── Services/
database/{migrations, seeders, factories}
public/{css,js}              estilos y JS estáticos (ADR-010)
resources/views/{layouts, components, auth, dashboard, projects, tasks, comments, notifications, audits}
routes/web.php · routes/console.php (Scheduler)
tests/{Feature, Unit}
docs/                        esta documentación + diagramas draw.io
```

## 6. Diagramas

Archivos editables en draw.io / diagrams.net:

- `docs/diagramas/arquitectura-capas.drawio`: vista de capas y componentes.
- `docs/diagramas/modelo-entidad-relacion.drawio`: modelo de datos.
