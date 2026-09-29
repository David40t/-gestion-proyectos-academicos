<?php

namespace App\Http\Requests\Project;

use App\Models\Role;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas compartidas por la creación y la edición de proyectos.
 * La autorización se hace en el Controller mediante ProjectPolicy.
 */
abstract class ProjectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function projectRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'teacher_id' => ['required', 'integer', new UserHasRole(Role::DOCENTE)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descripción',
            'objectives' => 'objetivos',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de finalización',
            'teacher_id' => 'docente responsable',
            'member_ids' => 'integrantes',
            'member_ids.*' => 'integrante',
        ];
    }
}
