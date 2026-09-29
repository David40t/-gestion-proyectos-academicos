<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Registro de auditoría. Inmutable: solo se crea, nunca se modifica ni elimina (ADR-009).
 */
#[Fillable([
    'user_id', 'action', 'module', 'auditable_type', 'auditable_id', 'project_id',
    'old_values', 'new_values', 'ip_address', 'user_agent',
])]
class Audit extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de auditoría no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los registros de auditoría no se pueden eliminar.'));
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Proyecto de contexto (incluye proyectos eliminados lógicamente).
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
