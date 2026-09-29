<?php

namespace App\Http\Requests\Project;

use App\Models\Role;
use App\Rules\UserHasRole;

class StoreProjectRequest extends ProjectRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->projectRules(),
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'distinct', new UserHasRole(Role::ESTUDIANTE)],
        ];
    }
}
