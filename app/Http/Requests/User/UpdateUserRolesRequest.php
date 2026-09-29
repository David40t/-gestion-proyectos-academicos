<?php

namespace App\Http\Requests\User;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::in(Role::ASSIGNABLE)], // LIDER no es asignable a mano
        ];
    }

    public function attributes(): array
    {
        return ['roles' => 'roles', 'roles.*' => 'rol'];
    }

    public function messages(): array
    {
        return ['roles.required' => 'Selecciona al menos un rol.'];
    }
}
