<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class ChangeLeaderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Que sea integrante del proyecto lo valida ProjectMemberService (regla de negocio).
        return [
            'leader_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return ['leader_id' => 'nuevo líder'];
    }
}
