# 07 · Seguridad

Revisión sistemática basada en **OWASP Top 10 (2021)**. Cada control indica dónde está implementado y
qué prueba automatizada lo verifica.

## 1. Resumen de controles

| OWASP | Riesgo | Control implementado | Dónde | Prueba |
|---|---|---|---|---|
| **A01** Control de acceso | Acceder o modificar recursos ajenos | Permiso por rol (Gate) + Policy por registro en **cada** acción; ocultar botones es solo UX | `Policies/*`, `Gate::before` | `AuthorizationCoverageTest` (todas las acciones autorizan), 403 en cada módulo |
| A01 | IDOR (cambiar ids en la URL) | *Scoped bindings*: una tarea o comentario solo se resuelve dentro de su proyecto; las notificaciones se buscan solo entre las del usuario | `routes/web.php`, `NotificationRepository` | `TaskManagementTest`, `CommentTest`, `NotificationInboxTest` (404) |
| A01 | Rutas sin protección | Todas las rutas exigen sesión salvo las de autenticación | `routes/web.php` | `RouteProtectionTest` (barre todas las rutas) |
| A01 | Redirección abierta | Las notificaciones redirigen solo a rutas internas; "Volver" en errores usa `previousPath()` | `NotificationService`, `errors/layout` | `SecurityHardeningTest` |
| **A02** Criptografía | Contraseñas o sesiones legibles | bcrypt (12 rondas), cookies cifradas, **contenido de sesión cifrado** (`SESSION_ENCRYPT=true`), HSTS en HTTPS | `User` (cast `hashed`), `.env` | `RegistrationTest` (hash), `SecurityHardeningTest` (HSTS) |
| **A03** Inyección SQL | Datos del usuario concatenados en SQL | Eloquent/Query Builder con parámetros. El SQL crudo (`selectRaw`, `orderByRaw`) usa `?` y valores de Enums, nunca input | `Repositories/Eloquent/*` | Revisión de código (sección 3) |
| A03 | XSS | Blade escapa todo (`{{ }}`); no existe `{!! !!}`. **CSP estricta** sin `unsafe-inline` y vistas sin scripts ni estilos inline | Vistas, `SecurityHeaders` | `CommentTest` (`<script>` escapado), `SecurityHardeningTest` (CSP y HTML sin inline) |
| **A04** Diseño inseguro | Reglas evitables desde el cliente | Reglas de negocio en Services; el JS es solo complemento | `Services/*` | Pruebas unitarias de Services, mutaciones (`docs/06`) |
| A04 | Asignación masiva | Controllers pasan solo `validated()`; campos sensibles fijados por el Service; modo estricto de Eloquent en desarrollo | Controllers, `AppServiceProvider` | `MassAssignmentTest`, `RegistrationTest` (no escala rol) |
| **A05** Configuración | Cabeceras ausentes, detalles expuestos | CSP, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, sin `X-Powered-By`; páginas de error sin trazas con `APP_DEBUG=false`; ruta `storage/{path}` desactivada | `SecurityHeaders` (global), `errors/*`, `config/filesystems.php` | `SecurityHardeningTest`, `ErrorHandlingTest` |
| **A06** Componentes vulnerables | Dependencias con CVE | `composer audit` sin avisos (29/09/2026); dependencias mínimas (sin Node) | `composer.lock` | Ejecutar `composer audit` antes de cada entrega |
| **A07** Autenticación | Fuerza bruta, credenciales débiles | 5 intentos de login por minuto por correo+IP; 30 solicitudes por minuto por IP en las rutas públicas; contraseña de mínimo 8 caracteres con letras y números; regeneración de sesión al iniciar sesión (Fortify); CSRF en todos los formularios | `FortifyServiceProvider`, `AppServiceProvider` | `LoginThrottleTest`, `SecurityHardeningTest`, `RouteProtectionTest` (CSRF) |
| A07 | Enumeración de usuarios | La recuperación de contraseña responde igual exista o no el correo | `lang/es/passwords.php` | Revisión manual |
| **A08** Integridad | Registros alterables | Auditoría inmutable (sin rutas, Policy niega, el modelo lanza excepción); roles solo asignados por el sistema | `Audit`, `AuditPolicy`, `RoleService` | `AuditTest`, `DomainModelTest` |
| **A09** Registro y monitoreo | Ataques sin rastro | Auditoría de login, logout, **intentos fallidos** y **bloqueos**, sin contraseñas; errores inesperados en el log | `Listeners/*`, `AuditService` | `LoginThrottleTest`, `AuditServiceTest` |
| **A10** SSRF | Peticiones a URLs del usuario | No aplica: el sistema no realiza peticiones a URLs externas indicadas por el usuario | — | — |

## 2. Datos sensibles y secretos
- Credenciales solo en `.env` (nunca versionado; verificado en el historial de git). `.env.example` sin valores reales.
- La auditoría descarta automáticamente cualquier campo que contenga `password`, `token` o `secret`.
- Los usuarios demo (`@demo.test`, contraseña `password`) solo se siembran en `local` y `testing`.
- En desarrollo, `MAIL_TO_ADDRESS` evita enviar correos a direcciones de terceros.

## 3. SQL crudo revisado
| Archivo | Uso | Parámetros |
|---|---|---|
| `TaskRepository::deadlineStats` | `SUM(CASE …)` para indicadores | Valores de `TaskStatus` y fechas del servidor, con `?` |
| `TaskRepository::forProject / paginateAssignedTo` | `ORDER BY CASE` | `TaskStatus::Completada->value` con `?` |
| `ProjectRepository::withStats` | `ORDER BY CASE` | Constantes con `?` |
| `TaskRepository::countByStatus` | `selectRaw('status, COUNT(*)')` | Sin parámetros externos |

Ningún fragmento SQL contiene datos introducidos por el usuario.

## 4. Configuración para producción
```dotenv
APP_ENV=production
APP_DEBUG=false            # páginas de error sin trazas
APP_URL=https://…          # HTTPS habilita HSTS
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true # cookie solo por HTTPS
MAIL_TO_ADDRESS=           # vacío: correos a sus destinatarios reales
LOG_LEVEL=warning
```
Además:
- Usar un usuario de BD con permisos solo sobre su esquema (como `gestion_user`).
- Ejecutar `php artisan config:cache` y `route:cache`.
- Mantener `queue:work` y el cron de `schedule:run`.

## 5. Riesgos aceptados y mejoras futuras
| Tema | Situación | Mejora posible |
|---|---|---|
| Verificación de correo | No se exige verificar el correo al registrarse | Activar `Features::emailVerification()` de Fortify |
| Contraseñas filtradas | No se consulta HIBP (sería una integración externa, fuera del MVP) | `Password::defaults()->uncompromised()` |
| Inmutabilidad de auditoría en BD | Garantizada por la aplicación, no por el motor | Usuario de BD sin UPDATE/DELETE sobre `audits`, o triggers |
| 2FA | Desactivado (fuera del alcance) | `Features::twoFactorAuthentication()` de Fortify |
| Cobertura de JS | Sin pruebas automatizadas | Pruebas E2E con un navegador |
