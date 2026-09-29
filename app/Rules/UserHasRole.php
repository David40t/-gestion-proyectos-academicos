<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que el id recibido corresponda a un usuario con el rol indicado.
 * Uso: new UserHasRole(Role::DOCENTE)
 */
class UserHasRole implements ValidationRule
{
    public function __construct(private readonly string $role) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = User::whereKey($value)
            ->whereHas('roles', fn ($query) => $query->where('name', $this->role))
            ->exists();

        if (! $exists) {
            $fail('El usuario seleccionado no es válido para este campo.');
        }
    }
}
