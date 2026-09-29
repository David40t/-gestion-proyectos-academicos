# 04 · Decisiones arquitectónicas (ADR)

Cada decisión sigue el formato **Contexto → Decisión → Justificación → Consecuencias**.

---

## ADR-001 · Monolito modular con Laravel 13 y MySQL 8 (compatible con MariaDB)

- **Contexto:** es un sistema académico con un solo equipo de desarrollo, carga moderada y un plazo de
  un semestre. El enunciado exige MariaDB, pero el entorno de desarrollo disponible tiene MySQL 8 instalado
  y en ejecución.
- **Decisión:** un único proyecto Laravel 13 (PHP 8.5) organizado por módulos. En desarrollo se usa MySQL 8.
  Las migraciones usan solo tipos estándar (sin características exclusivas de MySQL), así que el mismo
  esquema funciona en MariaDB cambiando `DB_CONNECTION=mariadb` en `.env`.
- **Justificación:**
  - Un monolito evita la complejidad operativa de los microservicios (red, despliegues múltiples,
    consistencia distribuida), que no aporta valor a esta escala.
  - La modularidad interna conserva la mantenibilidad.
- **Consecuencias:** hay un solo despliegue y una sola base de datos. Si en el futuro un módulo necesita
  escalar por separado, sus fronteras ya están definidas.

## ADR-002 · Arquitectura en capas con Service Layer y Repository Pattern

- **Contexto:** hay reglas de negocio que involucran varias entidades (crear proyecto + líder + integrantes +
  auditoría) y deben poder probarse y explicarse.
- **Decisión:** el flujo es Controller → Service → Repository → Model. Los Repositories exponen interfaces
  (`Repositories/Contracts`) enlazadas en `RepositoryServiceProvider`.
- **Justificación:**
  - **SRP:** cada capa tiene un solo motivo de cambio.
  - **DIP:** los Services dependen de interfaces, no de Eloquent.
  - Permite probar Services de forma unitaria con repositorios simulados.
- **Consecuencias:** hay más archivos que en un Laravel "clásico". Para no crear capas vacías, los
  Repositories solo exponen los métodos que los Services realmente usan (sin un CRUD genérico gigante).

## ADR-003 · Autenticación con Laravel Fortify y vistas Blade propias

- **Contexto:** se pide autenticación nativa de Laravel sin frameworks de frontend. Los starter kits de
  Laravel 13 usan Livewire, React o Vue, y Breeze fue descontinuado.
- **Decisión:** usar **Laravel Fortify**, el backend oficial de autenticación y sin interfaz. Las vistas de
  login, registro y recuperación de contraseña se escriben en Blade.
- **Justificación:** no se reinventa la autenticación (hashing, throttling, tokens de recuperación, regeneración
  de sesión) y se respeta la restricción de frontend.
- **Consecuencias:** la creación de usuarios se personaliza en `App\Actions\Fortify\CreateNewUser`, que asigna
  el rol `ESTUDIANTE`.

## ADR-004 · Roles y permisos propios con Gate + Policies

- **Contexto:** el enunciado define las tablas `roles`, `permissions`, `role_user` y `permission_role`, y exige
  poder agregar roles sin tocar la lógica central.
- **Decisión:**
  - Implementación propia de roles y permisos, sin paquetes de terceros como spatie/laravel-permission.
  - Un `Gate::before` resuelve cualquier habilidad con formato `modulo.accion` contra los permisos del usuario.
  - Las Policies añaden las reglas por registro.
- **Justificación:**
  - El esquema coincide exactamente con el pedido.
  - Es fácil de explicar.
  - Evita una dependencia externa cuyo esquema (`model_has_roles`, …) difiere del requerido.
- **Consecuencias:** los permisos del usuario se cargan una vez por request (memorización en el modelo `User`)
  para no repetir consultas.

### ADR-004b · Estados y prioridades como Enums de PHP
- **Decisión:** usar `ProjectStatus`, `TaskStatus` y `TaskPriority` como *backed enums*, guardados en BD como
  VARCHAR. Cada Enum incluye su etiqueta visible y sus transiciones permitidas.
- **Justificación:**
  - Hay un solo punto de cambio y tipado fuerte en PHP.
  - No se usa el tipo ENUM de SQL, que obliga a una migración por cada cambio.
  - Tampoco se usan tablas catálogo, que agregarían *joins* y pantallas de administración innecesarias en el MVP.
- **Consecuencias:** agregar un estado implica editar el Enum (y su vista). No hace falta migración.

## ADR-005 · El avance del proyecto se calcula, no se almacena

- **Decisión:** `ProgressService` calcula el avance del proyecto con `AVG(tasks.progress)`. `projects` no tiene
  columna `progress`.
- **Justificación:** evita datos derivados que pueden desincronizarse (normalización). El cálculo es una sola
  consulta indexada por `project_id`.
- **Consecuencias:** los listados de proyectos usan `withAvg('tasks', 'progress')` en el Repository para evitar
  el problema N+1.

## ADR-006 · El liderazgo es por proyecto; el rol LIDER otorga capacidades

- **Contexto:** un estudiante puede liderar un proyecto y ser integrante normal en otro.
- **Decisión:**
  - `projects.leader_id` define el líder de cada proyecto.
  - El rol global `LIDER` da los *permisos* de gestión, y la Policy exige además ser el líder de *ese* proyecto.
  - `RoleService` asigna o retira el rol `LIDER` automáticamente y lo audita.
- **Justificación:** se respetan los tres roles del enunciado y el modelo de permisos por rol, sin dar
  privilegios sobre proyectos ajenos.
- **Consecuencias:** `leader_id` y la membresía se mantienen coherentes en `ProjectMemberService`, dentro de
  una transacción.

## ADR-007 · Comentarios con `project_id` + `task_id` opcional

- **Decisión:** se usan claves foráneas reales en lugar de una relación polimórfica (`commentable`).
- **Justificación:** hay integridad referencial y la autorización es directa, porque todo comentario pertenece
  a un proyecto.
- **Consecuencias:** `CommentService` valida que `task_id` pertenezca al `project_id` indicado.

## ADR-008 · Notificaciones nativas de Laravel, en cola, despachadas desde los Services

- **Decisión:**
  - Una clase por evento en `app/Notifications/{Project,Task,Comment}`, todas heredan de `AppNotification`.
  - Canal `database` siempre. Canal `mail` solo cuando la notificación decide que el evento es crítico
    (`shouldMail()`, que puede decidir por destinatario).
  - Las notificaciones implementan `ShouldQueue` (cola `database`, sin Redis), usan `afterCommit()` y se
    reintentan hasta 3 veces con espera progresiva.
  - `NotificationDispatcher` es el punto único de envío: resuelve destinatarios, quita duplicados y nunca
    notifica al autor de la acción.
  - Los procesos de fechas límite viven en `TaskDeadlineService`, ejecutado a diario por `tasks:check-deadlines`.
- **Justificación:**
  - Desacoplamiento: agregar un canal solo toca `via()`, y la regla "¿merece correo?" vive junto a la
    notificación, no dispersa en los Services.
  - Los correos no bloquean la respuesta HTTP.
  - `afterCommit` garantiza que nunca se notifique algo que se revirtió, aunque el despacho ocurra dentro de
    la transacción (p. ej. integrantes agregados al crear un proyecto).
  - Las notificaciones guardan solo texto y URL calculados al crearlas: el job no depende de que los modelos
    sigan existiendo (p. ej. una tarea eliminada después).
- **Consecuencias:**
  - Hay que ejecutar un worker (`php artisan queue:work`) y el cron de `schedule:run`.
  - Laravel encola un job por canal, así que un fallo de correo no impide la notificación interna.
  - En desarrollo, `MAIL_TO_ADDRESS` redirige todos los correos a un único buzón.
  - Los seeders usan cola síncrona y correo en log: sembrar datos nunca envía correos reales.

## ADR-009 · Auditoría propia, explícita e inmutable

- **Decisión:**
  - Un `AuditService::record()` invocado explícitamente por los Services **dentro de la misma transacción**
    que el cambio.
  - Login y logout se auditan con Listeners de los eventos nativos de Auth.
  - Sin paquetes externos.
- **Justificación:**
  - Registrar de forma explícita (en lugar de *model observers* genéricos) guarda acciones con significado
    de negocio (`task.status_changed`, no solo "updated").
  - Hace el flujo visible en la presentación académica.
- **Consecuencias:**
  - Cada Service debe acordarse de auditar; los Feature Tests verifican que cada acción crítica genere su registro.
  - Escritura (`AuditService`) y consulta (`AuditQueryService`) están separadas (SRP).
  - La inmutabilidad se garantiza en tres niveles: no hay rutas de edición ni borrado (405), `AuditPolicy`
    niega `update`/`delete`, y el modelo `Audit` lanza una excepción si se intenta modificar.
  - Mejora futura: un trigger de BD o un usuario de BD sin permisos UPDATE/DELETE sobre `audits`.

## ADR-010 · Frontend Blade + CSS/JS estáticos (sin Tailwind, sin Vite, sin Node.js)

- **Decisión:** Blade con componentes (`<x-alert>`, …), una hoja de estilos propia en `public/css/app.css` y
  JavaScript *vanilla* en `public/js/app.js`, incluidos con `asset()`. Se retiraron del esqueleto Tailwind,
  Vite, `package.json` y la vista `welcome`.
- **Justificación:** el enunciado limita el frontend a Blade, HTML, CSS y JS. Con archivos estáticos no hace
  falta instalar Node.js ni compilar nada: el proyecto funciona solo con PHP, Composer y la base de datos.
- **Consecuencias:** no hay minificación ni *cache busting* automáticos. Para un proyecto de este tamaño es
  aceptable, y el versionado se puede resolver agregando `?v=` al `asset()`.

## ADR-011 · Pruebas con SQLite en memoria

- **Decisión:** PHPUnit con `RefreshDatabase` sobre SQLite en memoria (la configuración por defecto de
  Laravel en `phpunit.xml`).
- **Justificación:** las pruebas son rápidas y aisladas, y no dependen del servidor MySQL.
- **Consecuencias:** las migraciones deben ser portables (sin SQL crudo específico de un motor), lo que
  además refuerza la compatibilidad con MariaDB del ADR-001.

## ADR-012 · No instalar Laravel Boost

- **Contexto:** el esqueleto de Laravel 13 incluye `CLAUDE.md`/`AGENTS.md`, que sugieren instalar
  `laravel/boost` (una herramienta para asistentes de IA).
- **Decisión:** no instalarlo.
- **Justificación:** no forma parte de la solución ni del stack exigido.

## ADR-013 · Eliminación lógica (soft delete) donde hay historial que conservar

- **Contexto:** eliminar físicamente proyectos, tareas o comentarios haría perder historial académico y
  dejaría registros de auditoría apuntando a entidades inexistentes.
- **Decisión:** usar `SoftDeletes` de Eloquent en `projects`, `tasks` y `comments`. Las tareas se pueden
  restaurar desde la papelera del proyecto (permiso `tarea.eliminar`, solo el líder).
- **Descartado:**
  - `project_members`: rompería el UNIQUE `(project_id, user_id)` al reincorporar a un integrante, y el
    historial de altas y bajas ya queda en auditoría.
  - `audits`: es inmutable.
  - `users` y `notifications`: el MVP no contempla eliminarlos.
- **Consecuencias:**
  - El *scope* global de Eloquent excluye automáticamente los registros eliminados en consultas, conteos,
    promedios y route binding. Solo la ruta de restauración usa `withTrashed()`.
  - Las claves foráneas `ON DELETE CASCADE` solo actúan ante un borrado físico, que la aplicación no realiza.

## ADR-014 · Dashboard compuesto por un Service según la perspectiva del usuario

- **Contexto:** el enunciado pide un dashboard con información distinta para estudiante, líder y docente.
- **Decisión:** `DashboardService::for()` elige la perspectiva (docente o estudiante, y agrega la sección de
  líder cuando corresponde) y obtiene cada bloque de consultas agregadas de los repositorios: `withCount`,
  `withAvg` y `SUM(CASE …)` en una sola consulta para los indicadores de tareas.
- **Justificación:**
  - Es el único punto del sistema donde el contenido depende del rol (en el resto se usan permisos y
    Policies). Queda centralizado y es fácil de explicar.
  - El número de consultas es constante: no crece con la cantidad de proyectos. Una prueba lo verifica
    (≤ 20 consultas).
- **Consecuencias:** un rol nuevo con otra perspectiva requiere agregar un método al Service y sus
  parciales Blade, sin tocar el resto del sistema.

## ADR-015 · Validación en capas y manejo de errores

- **Contexto:** se exige validar en backend, con JavaScript solo como complemento, y dar al usuario mensajes
  claros sin exponer información interna.
- **Decisión:** cada tipo de validación tiene una sola capa responsable.

| Nivel | Dónde | Qué valida | Respuesta |
|---|---|---|---|
| 1. Formato | Form Requests (`app/Http/Requests`) | Obligatorios, tipos, longitudes, fechas, enums, existencia de relaciones | Vuelve al formulario con el error en cada campo |
| 2. Autorización | Policies + Gate de permisos | ¿Puede este usuario actuar sobre este registro? | Página 403 |
| 3. Reglas de negocio | Services → `BusinessRuleException` | Reglas que dependen del estado (no retirar al líder, transiciones de estado, coherencia estado/avance…) | Vuelve con un mensaje de error y los datos escritos; la transacción se revierte |
| 4. Complementaria | `public/js/app.js` (`data-validate`) | Obligatorios, longitudes, rangos y orden de fechas, leídos del HTML | Evita el envío y marca el campo, sin sustituir a los niveles 1 a 3 |

- **Errores inesperados:** se registran en el log y se muestra una página genérica (`errors/500`) sin detalles
  técnicos cuando `APP_DEBUG=false`. Las páginas de error usan un layout independiente (sin BD ni sesión).
- **`BusinessRuleException` no se registra en el log** (`dontReport`): es un resultado esperado del negocio,
  no un fallo del sistema.
- **Mensajes:** traducción completa en `lang/es/validation.php`, nombres legibles de campos y *replacers*
  que muestran fechas en `d/m/Y` y "hoy" en lugar de "today".

