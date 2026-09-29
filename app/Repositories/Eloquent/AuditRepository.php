<?php

namespace App\Repositories\Eloquent;

use App\Models\Audit;
use App\Repositories\Contracts\AuditRepositoryInterface;

/**
 * Solo expone escritura y (en la Fase 9) lectura: no existen métodos para modificar ni eliminar.
 */
class AuditRepository implements AuditRepositoryInterface
{
    public function create(array $attributes): Audit
    {
        return Audit::create($attributes);
    }
}
