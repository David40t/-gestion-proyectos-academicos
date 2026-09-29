# 05 · Guía de pruebas

Guía paso a paso para levantar el sistema y verificar cada módulo, de forma manual (navegador) y
automática (PHPUnit). Se actualiza al cerrar cada fase.

---

## 1. Preparación del entorno

### 1.1 Requisitos
- PHP 8.3 o superior, con las extensiones `pdo_mysql`, `mbstring`, `openssl`, `intl` y `bcmath`.
- Composer 2.
- MySQL 8 o MariaDB 10.6+ con una base de datos creada (ver `.env.example`).
- **No** se necesita Node.js (ADR-010).

### 1.2 Primera instalación
```bash
composer install
cp .env.example .env          # completar DB_* y MAIL_* (ver §1.3)
php artisan key:generate
php artisan migrate --seed
```

### 1.3 Configuración de correo (Gmail)
En `.env`:
```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME="tu-cuenta@gmail.com"
MAIL_PASSWORD="contraseñadeaplicacion"     # 16 letras, sin espacios
MAIL_FROM_ADDRESS="tu-cuenta@gmail.com"
MAIL_TO_ADDRESS="tu-cuenta@gmail.com"      # SOLO desarrollo: todos los correos llegan aquí
```
- La contraseña es una **contraseña de aplicación** de Google (Cuenta → Seguridad → Verificación en dos
  pasos → Contraseñas de aplicaciones), no la contraseña normal de la cuenta.
- `MAIL_TO_ADDRESS` es necesario en desarrollo porque los usuarios demo usan correos `@demo.test`, que no
  existen. En producción se deja vacío.
- Para no enviar correos reales, usa `MAIL_MAILER=log`: los correos se escriben en `storage/logs/laravel.log`.
- Después de modificar `.env`: `php artisan config:clear`.

### 1.4 Reiniciar los datos de demostración
```bash
php artisan migrate:fresh --seed
```
Borra todo y recrea las tablas y los datos demo. Los seeders **no envían correos reales**: guardan las
notificaciones en BD y escriben los correos en el log.

---

## 2. Ejecutar el sistema

Se necesitan **dos terminales**:

| Terminal | Comando | Para qué |
|---|---|---|
| 1 | `php artisan serve` | Servidor web en http://127.0.0.1:8000 |
| 2 | `php artisan queue:work` | Procesa la cola: guarda notificaciones y **envía correos** |

Sin la terminal 2, las notificaciones y los correos quedan pendientes en la tabla `jobs`. Se procesan
cuando se inicie el worker.

Para ejecutar manualmente el proceso diario de fechas límite (en producción lo ejecuta el cron):
```bash
php artisan tasks:check-deadlines
```

### Usuarios de demostración
Todos con contraseña `password`:

| Correo | Rol | Situación en el proyecto demo |
|---|---|---|
| `lider@demo.test` | Estudiante + Líder | Líder del proyecto |
| `estudiante@demo.test` | Estudiante | Integrante, con tareas asignadas |
| `estudiante2@demo.test` | Estudiante | **No** es integrante (sirve para probar accesos denegados) |
| `docente@demo.test` | Docente | Docente responsable del proyecto |

---

## 3. Pruebas manuales por módulo

Cada escenario indica **quién**, **qué hacer** y **qué debe ocurrir**. Conviene usar una ventana de
incógnito por usuario para tener varias sesiones abiertas a la vez.

### 3.1 Autenticación
| # | Pasos | Resultado esperado |
|---|---|---|
| A1 | Abrir http://127.0.0.1:8000/dashboard sin sesión | Redirige a `/login` |
| A2 | Iniciar sesión con contraseña incorrecta | Mensaje "Estas credenciales no coinciden…" |
| A3 | Seis intentos fallidos seguidos | Bloqueo temporal ("Demasiados intentos…") |
| A4 | Registrarse en `/register` | Entra al dashboard con rol **Estudiante** |
| A5 | "¿Olvidaste tu contraseña?" con `estudiante@demo.test` | Llega un correo "Restablecer contraseña" a `MAIL_TO_ADDRESS`. El enlace permite definir una nueva contraseña |
| A6 | Cerrar sesión | Vuelve al login. Queda registrado `auth.logout` en auditoría |

### 3.2 Roles y permisos (autorización en backend)
| # | Usuario | Pasos | Resultado esperado |
|---|---|---|---|
| R1 | docente | Abrir `/projects/create` | **403**: los docentes no crean proyectos |
| R2 | estudiante | Abrir `/projects/1/edit` escribiendo la URL | **403** aunque conozca la URL |
| R3 | estudiante2 | Abrir `/projects/1` | **403**: no participa en el proyecto |
| R4 | estudiante | Ver el detalle del proyecto | No aparecen los botones Editar, Eliminar ni Agregar integrante |

### 3.3 Proyectos
| # | Usuario | Pasos | Resultado esperado |
|---|---|---|---|
| P1 | estudiante2 | Proyectos → Nuevo proyecto, con fecha de fin anterior a la de inicio | Error de validación en la fecha de finalización |
| P2 | estudiante2 | Crear un proyecto válido marcando a otro estudiante como integrante | Queda como **líder**, adquiere el rol Líder y el integrante recibe una notificación y un correo |
| P3 | lider | En el proyecto demo (En progreso), cambiar el estado a **En revisión** | Integrantes y docente reciben una notificación interna |
| P4 | lider | Intentar finalizar el proyecto | La opción "Finalizado" no aparece: solo el docente finaliza |
| P5 | docente | Con el proyecto en revisión, cambiar a **Finalizado** | Estado final. Llega un correo a los integrantes y el proyecto queda en solo lectura |
| P6 | lider | Eliminar un proyecto que tiene tareas | Mensaje: solo se eliminan proyectos en planeación sin tareas |

> Para repetir P3–P5, reinicia los datos con `php artisan migrate:fresh --seed`.

### 3.4 Integrantes
| # | Usuario | Pasos | Resultado esperado |
|---|---|---|---|
| I1 | lider | Agregar a "Estudiante Dos" | Aparece en la lista. Estudiante Dos recibe una notificación y un correo |
| I2 | lider | Intentar retirarse a sí mismo | No hay botón. Por URL, el sistema responde "No se puede retirar al líder…" |
| I3 | lider | Retirar a "Estudiante Demo" | Sus tareas abiertas quedan **sin responsable**; las completadas lo conservan |
| I4 | lider | Transferir el liderazgo a otro integrante | El nuevo líder recibe un correo y el anterior pierde los permisos de gestión |

### 3.5 Tareas y seguimiento
| # | Usuario | Pasos | Resultado esperado |
|---|---|---|---|
| T1 | lider | Nueva tarea con fecha límite fuera del rango del proyecto | Error de validación |
| T2 | lider | Nueva tarea asignada a "Estudiante Demo" | El responsable recibe una notificación y un correo |
| T3 | estudiante | Mis tareas → abrir una tarea → registrar avance 100% con estado "En progreso" | Queda **Completada** al 100%; el líder recibe una notificación |
| T4 | estudiante | Registrar estado "En progreso" con avance 0 | Mensaje de error: incoherente |
| T5 | estudiante | Registrar avance en una tarea de **otro** responsable | Aparece el mensaje "Solo el responsable… o el líder…" |
| T6 | cualquiera | Ver el detalle del proyecto | Panel de seguimiento: avance general (promedio), conteo por estado y próximas fechas |
| T7 | lider | Eliminar una tarea → Papelera → Restaurar | Sale del conteo al eliminarla y vuelve al restaurarla |

### 3.6 Comentarios
| # | Usuario | Pasos | Resultado esperado |
|---|---|---|---|
| C1 | docente | Comentar el proyecto marcando **"Registrar como observación docente"** | Se destaca en color. Los integrantes reciben una notificación y un **correo** |
| C2 | estudiante | Comentar una tarea | Líder y docente reciben una notificación interna, sin correo |
| C3 | estudiante | Editar y eliminar un comentario propio | Se permite. Aparece la marca "editado" |
| C4 | lider | Intentar editar un comentario ajeno | No aparece la opción; por URL responde 403 |
| C5 | cualquiera | Comentar `<script>alert(1)</script>` | Se muestra como texto y no se ejecuta |

### 3.7 Notificaciones y correo
| # | Pasos | Resultado esperado |
|---|---|---|
| N1 | Con `queue:work` detenido, hacer la acción T2 | La tabla `jobs` tiene trabajos pendientes. No llega correo |
| N2 | Iniciar `php artisan queue:work` | Se procesan los trabajos y llega el correo |
| N3 | Barra superior → Notificaciones | Lista con las no leídas destacadas y contador |
| N4 | Pulsar "Ver" en una notificación | Lleva al proyecto o tarea y la marca como leída |
| N5 | "Marcar todas como leídas" | El contador desaparece |

### 3.8 Recordatorios de fecha límite
```bash
php artisan tasks:check-deadlines
```
| # | Pasos | Resultado esperado |
|---|---|---|
| D1 | Ejecutar el comando con los datos demo | "Modelo entidad-relación" (vence en 2 días) genera un recordatorio. Salida: `Recordatorios enviados: 1` |
| D2 | Ejecutar el comando otra vez | `Recordatorios enviados: 0`: no se repiten |
| D3 | Como líder, cambiar la fecha límite de esa tarea a mañana y volver a ejecutar | Se envía un nuevo recordatorio |
| D4 | Tareas abiertas con fecha límite pasada | Pasan a **Vencida**. Responsable y líder reciben un correo |

### 3.9 Dashboard por rol
Valores esperados con los datos recién sembrados (`migrate:fresh --seed`):

| Usuario | Tarjetas | Secciones |
|---|---|---|
| estudiante | 1 proyecto · 1 activo · **1 tarea pendiente** · 1 vence en 7 días · **1 vencida** | Mis proyectos, Mis próximas tareas, Notificaciones |
| lider | 1 proyecto · 1 activo · 1 pendiente · 1 vence pronto · 0 vencidas | **Proyectos que lideras** (integrantes, tareas abiertas, vencidas, avance) + las del estudiante |
| docente | 1 supervisado · 1 activo · **3 pendientes** (todo el proyecto) · 2 vencen pronto · 1 vencida | Proyectos supervisados, Próximas entregas, Comentarios recientes, Actividad reciente, Notificaciones |
| estudiante2 | Todo en 0 | Mensaje "Aún no participas en ningún proyecto" con enlace para crear uno |

| # | Pasos | Resultado esperado |
|---|---|---|
| DB1 | Como estudiante, completar "Modelo entidad-relación" y volver al dashboard | Tareas pendientes y "vencen en 7 días" bajan en 1 |
| DB2 | Como docente, registrar una observación y volver al dashboard | Aparece en "Comentarios recientes" y en "Actividad reciente" |
| DB3 | Pulsar "Ver" en una notificación del dashboard | Lleva al recurso y el contador de no leídas baja |

### 3.10 Auditoría
| # | Usuario | Pasos | Resultado esperado |
|---|---|---|---|
| AU1 | docente | Menú → Auditoría | Solo aparecen los registros de sus proyectos, sin inicios de sesión |
| AU2 | docente | Filtrar por Módulo = Tareas y un rango de fechas | Solo acciones de tareas en ese rango |
| AU3 | docente | Abrir "Detalle" de una "Tarea modificada" | Tabla campo / valor anterior (rojo) / valor nuevo (verde), con usuario, IP y navegador |
| AU4 | docente | En el proyecto, botón **Historial** | Auditoría filtrada por ese proyecto |
| AU5 | lider / estudiante | Abrir `/audits` por URL | **403**: no hay enlace en el menú y el backend lo impide |
| AU6 | docente | Enviar `DELETE /audits/1` (p. ej. con curl) | **405**: no existe ninguna ruta para modificar o borrar |
| AU7 | cualquiera | Realizar una acción (p. ej. comentar) y revisar la auditoría | Registro con fecha, usuario, IP y valores, sin contraseñas ni tokens |

---

## 4. Pruebas automatizadas

```bash
php artisan test                               # suite completa
php artisan test --filter=ProjectMemberTest    # una clase
php artisan test tests/Unit                    # solo unitarias
```
- Usan **SQLite en memoria** (ADR-011): no tocan la base MySQL ni envían correos.
- Estructura:
  - `tests/Unit`: reglas puras (`TaskStateResolver`, `AuditService` con repositorio simulado).
  - `tests/Feature/{Auth,Authorization,Projects,Tasks,Comments,Notifications,Audit,Database}`: flujos HTTP completos.

---

## 5. Consultas útiles en la base de datos

```sql
-- Auditoría reciente
SELECT created_at, user_id, action, module, auditable_id, ip_address FROM audits ORDER BY id DESC LIMIT 20;

-- Notificaciones por usuario
SELECT u.email, COUNT(*) total, SUM(n.read_at IS NULL) no_leidas
FROM notifications n JOIN users u ON u.id = n.notifiable_id GROUP BY u.email;

-- Cola pendiente y trabajos fallidos
SELECT COUNT(*) FROM jobs;
SELECT id, failed_at, LEFT(exception, 200) FROM failed_jobs;
```

---

## 6. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| No llegan correos | `queue:work` no está corriendo | Iniciarlo (ver §2) |
| Hay trabajos en `failed_jobs` con "getaddrinfo … failed" | Falla temporal de red o DNS | `php artisan queue:retry all` con el worker activo. Las notificaciones ya se reintentan 3 veces solas |
| "Authentication failed" en `failed_jobs` | Contraseña de aplicación incorrecta o revocada | Generar una nueva en Google y actualizar `.env` → `php artisan config:clear` |
| Los cambios en `.env` no surten efecto | Configuración en caché | `php artisan config:clear`, y reiniciar `queue:work`, que carga la configuración al iniciar |
| Error 419 al enviar un formulario | La sesión expiró (token CSRF) | Recargar la página e intentar de nuevo |
| El correo llega a otro buzón | `MAIL_TO_ADDRESS` redirige todo | Es lo esperado en desarrollo; vaciarlo en producción |
