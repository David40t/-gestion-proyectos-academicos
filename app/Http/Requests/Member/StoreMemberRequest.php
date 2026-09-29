<?php

namespace App\Http\Requests\Member;

use App\Models\Role;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', new UserHasRole(Role::ESTUDIANTE)],
        ];
    }

    public function attributes(): array
    {
        return ['user_id' => 'estudiante'];
    }
}
