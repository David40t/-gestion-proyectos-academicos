<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas de creación/edición de tareas. Las fechas deben caer dentro
 * del rango del proyecto y el responsable debe ser integrante del proyecto.
 */
abstract class TaskRequest extends FormRequest
{
    protected function project(): Project
    {
        return $this->route('project');
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskRules(): array
    {
        $project = $this->project();
        $dueDateRules = ['required', 'date', 'after_or_equal:start_date', 'after_or_equal:'.$project->start_date->toDateString()];
        if ($project->end_date) {
            $dueDateRules[] = 'before_or_equal:'.$project->end_date->toDateString();
        }

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'start_date' => ['required', 'date', 'after_or_equal:'.$project->start_date->toDateString()],
            'due_date' => $dueDateRules,
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')->where('project_id', $project->id),
            ],
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
            'priority' => 'prioridad',
            'start_date' => 'fecha de inicio',
            'due_date' => 'fecha límite',
            'assigned_to' => 'responsable',
            'status' => 'estado',
            'progress' => 'avance',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'El responsable debe ser integrante del proyecto.',
            'start_date.after_or_equal' => 'La fecha de inicio no puede ser anterior al inicio del proyecto.',
            'due_date.before_or_equal' => 'La fecha límite no puede superar la finalización del proyecto.',
        ];
    }
}
