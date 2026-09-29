<?php

namespace App\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'module' => ['nullable', Rule::in(array_keys(trans('audit.modules')))],
            'action' => ['nullable', Rule::in(array_keys(trans('audit.actions')))],
            'user' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function attributes(): array
    {
        return ['module' => 'módulo', 'action' => 'acción', 'user' => 'usuario', 'project_id' => 'proyecto', 'from' => 'desde', 'to' => 'hasta'];
    }
}
