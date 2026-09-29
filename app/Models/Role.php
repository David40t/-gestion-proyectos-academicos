<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'display_name', 'description'])]
class Role extends Model
{
    public const ESTUDIANTE = 'ESTUDIANTE';

    public const LIDER = 'LIDER';

    public const DOCENTE = 'DOCENTE';

    public const ADMINISTRADOR = 'ADMINISTRADOR';

    /**
     * Roles que un administrador puede asignar manualmente.
     * LIDER no se incluye: se asigna automáticamente al liderar un proyecto (ADR-006).
     */
    public const ASSIGNABLE = [self::ESTUDIANTE, self::DOCENTE, self::ADMINISTRADOR];

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('created_at');
    }
}
