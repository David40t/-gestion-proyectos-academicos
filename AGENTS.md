# Guía para asistentes de IA y colaboradores

Proyecto académico: monolito modular Laravel 13 en capas (MVC + Service Layer + Repository Pattern).
Antes de modificar código, lee `docs/01-arquitectura.md` y `docs/04-decisiones-arquitectonicas.md`.

## Reglas obligatorias
- **Stack fijo:** PHP/Laravel, Blade, HTML, CSS y JS *vanilla* (en `public/`), MySQL/MariaDB. Sin frameworks de
  frontend, Node/Vite, microservicios ni API REST como arquitectura principal. No instalar Laravel Boost (ADR-012).
- **Capas:** Route → Controller → Service → Repository (interfaz) → Model.
  - Controllers delgados: `$this->authorize()` + Form Request + llamada a un Service.
  - Reglas de negocio en Services; violaciones con `BusinessRuleException`.
  - Consultas solo en `app/Repositories/Eloquent`; los Services dependen de `Repositories/Contracts`.
  - Vistas sin consultas; los datos llegan preparados.
- **Autorización** siempre en backend: permiso (`modulo.accion`, Gate) + Policy por registro.
- **Cada acción importante:** transacción → auditoría (`AuditService`) → notificación (`NotificationDispatcher`).
- **Estados** solo mediante los Enums de `app/Enums`.
- **Idioma:** código en inglés; interfaz, mensajes y documentación en español.

## Verificación antes de terminar
```bash
php artisan test          # incluye la suite Architecture, que hace cumplir las reglas de capas
composer audit
```
Mantén actualizados `docs/` (en especial `05-guia-de-pruebas.md`) y agrega un ADR si tomas una decisión arquitectónica.
