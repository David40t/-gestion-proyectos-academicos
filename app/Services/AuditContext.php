<?php

namespace App\Services;

/**
 * Datos de contexto de la acción auditada (origen de la petición).
 *
 * Lo construye el contenedor a partir del request HTTP (AppServiceProvider), de modo que
 * AuditService no depende de la capa HTTP. Desde consola (Scheduler) ambos valores son null.
 */
final readonly class AuditContext
{
    public function __construct(
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
