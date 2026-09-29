# 06 · Estrategia de pruebas

**215 pruebas automatizadas · 1126 aserciones · ~20 s.** Se ejecutan con `php artisan test` sobre SQLite en
memoria (ADR-011), sin tocar la base MySQL ni enviar correos.

## 1. Pirámide de pruebas

| Suite | Pruebas | Qué verifica | Cómo |
|---|---:|---|---|
| **Unit** (`tests/Unit`) | 32 | Reglas de negocio aisladas en los Services | Repositorios **simulados** (Mockery): sin BD, milisegundos |
| **Feature** (`tests/Feature`) | 171 | Flujos reales: HTTP → Controller → Policy → Service → Repository → BD | Peticiones HTTP completas con `RefreshDatabase` |
| **Architecture** (`tests/Architecture`) | 12 | Que el código respeta la arquitectura en capas | Análisis estático del código fuente |

```bash
php artisan test                          # todo
php artisan test --testsuite=Unit         # una suite
php artisan test --filter=ProjectMember   # por nombre
```

## 2. Trazabilidad: requisito → pruebas

Requisitos mínimos del enunciado (§23) y dónde se verifican:

| Requisito | Archivos de prueba | Casos clave |
|---|---|---|
| Autenticación | `Feature/Auth/*`, `Security/LoginThrottleTest` | Login/logout auditados, registro como estudiante, contraseña con hash, recuperación de contraseña, bloqueo tras 5 intentos |
| Autorización | `Authorization/*`, `Security/RouteProtectionTest`, 403 en cada módulo | Un LIDER **de otro proyecto** no puede editar por URL; **todas** las rutas exigen sesión |
| Permisos por rol | `Authorization/PermissionMatrixTest`, `PermissionGateTest` | Los permisos en BD coinciden **exactamente** con la matriz de `docs/03`; el administrador tiene todos (incluidos los futuros); un rol nuevo funciona sin código |
| Administración | `Administration/AdministratorAccessTest`, `UserRoleManagementTest` | Acceso global, reglas que el admin **no** omite (auditoría, comentarios ajenos, observaciones, creación de proyectos, reglas de negocio), gestión de roles con salvaguardas, comando `users:grant-admin` |
| Creación de proyectos | `Projects/ProjectManagementTest`, `Unit/Services/ProjectServiceTest` | El creador queda como líder, transiciones de estado, borrado restringido |
| Gestión de integrantes | `Projects/ProjectMemberTest`, `Unit/Services/ProjectMemberServiceTest` | Sin duplicados, el líder no se retira, transferencia de liderazgo, tareas liberadas |
| Creación de tareas | `Tasks/TaskManagementTest` | Fechas dentro del proyecto, responsable integrante, *scoped binding* |
| Cambio de estados | `Unit/Services/Tasks/TaskStateResolverTest` (13 casos), `Tasks/TaskProgressTest` | Coherencia estado/avance, vencida solo por el sistema |
| Notificaciones | `Notifications/*`, `Unit/Services/NotificationDispatcherTest` | Destinatario y **canal** correctos por evento, sin avisar al autor, recordatorio único |
| Auditoría | `Audit/AuditTest`, `Unit/Services/AuditServiceTest` | Contexto de proyecto, alcance del docente, solo lectura (405), sin datos sensibles |
| Transacciones | `Database/TransactionRollbackTest` | Una falla a mitad de camino no deja datos parciales ni envía notificaciones |
| Seguridad | `Security/*`, `Comments/CommentTest` (XSS), `Errors/*` | Todas las acciones autorizan, asignación masiva ignorada, CSRF en todas las rutas de escritura, cabeceras y CSP, sin redirección abierta, política de contraseñas, límites de intentos, el error 500 no expone detalles. Ver `docs/07-seguridad.md` |
| Seeders | `Database/SeederTest`, `PermissionMatrixTest` | Datos demo coherentes, idempotencia, sembrar no envía correos |

## 3. Garantías transversales

### 3.1 Pruebas de arquitectura (`tests/Architecture/LayeredArchitectureTest.php`)
Si alguien rompe una regla de capas, la suite falla:
- Controllers y Services no consultan modelos directamente (`Modelo::where(…)`, `DB::table(…)`).
- Las vistas Blade no construyen consultas.
- Los Controllers no usan repositorios: pasan por los Services.
- Los Services dependen de **interfaces** de repositorio y **no dependen de HTTP**.
- Los repositorios y modelos no dependen de capas superiores.
- Cada interfaz de repositorio está enlazada a una implementación.
- Tamaño máximo: Controllers < 150 líneas y Services < 250 líneas (alerta de clases gigantes).
- El repositorio de auditoría no expone métodos de modificación ni borrado.

> Al crearse, esta prueba detectó una violación real: `AuditService` dependía de `Illuminate\Http\Request`.
> Se corrigió con el objeto de valor `AuditContext`, que el contenedor construye desde el request.

### 3.2 Detección de consultas N+1
`Model::preventLazyLoading()` está activo fuera de producción. Cargar una relación de forma perezosa dentro
de un listado lanza una excepción, así que **toda la suite actúa como detector de N+1**. Además,
`DashboardTest` limita el dashboard a un máximo de 20 consultas aunque crezca el número de proyectos.

### 3.3 Verificación por mutación (manual)
Para comprobar que las pruebas detectan fallos reales, se introdujeron errores a propósito y se confirmó que
la suite falla:

| Mutación introducida | Pruebas que la detectaron |
|---|---|
| El dispatcher no excluye al autor de la acción | `NotificationDispatcherTest` |
| Se permite retirar al líder del proyecto | `ProjectMemberServiceTest` y `ProjectMemberTest` (unitaria y de funcionalidad) |

### 3.4 Determinismo
Las pruebas no dependen del azar: cuando una aserción depende de una fecha, esta se fija explícitamente
(se corrigió una prueba que fallaba en ~1 de cada 28 ejecuciones por una fecha aleatoria de la factory).

## 4. Cobertura de código
El PHP del entorno de desarrollo no incluye Xdebug ni PCOV, por lo que la cobertura se evalúa con la
trazabilidad de la sección 2. Para medir el porcentaje, instala PCOV y ejecuta:
```bash
php artisan test --coverage --min=80
```

## 5. Qué NO cubren las pruebas automatizadas
- **JavaScript** (`public/js/app.js`): se verifica solo su sintaxis (`node --check`). Su comportamiento se
  prueba manualmente (guía `05`, §3.11). Es un complemento de la validación del backend, que sí está probado.
- **Entrega real de correo SMTP**: las pruebas verifican qué correo se genera y a quién, no la entrega.
  Se verificó manualmente con Gmail (guía `05`, §3.7).
- **Apariencia visual y diseño responsive**: revisión manual.
