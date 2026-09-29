<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskProgressRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)->only(TaskStatus::selectable())],
            'progress' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'estado', 'progress' => 'avance'];
    }

    public function status(): TaskStatus
    {
        return TaskStatus::from($this->validated('status'));
    }
}
