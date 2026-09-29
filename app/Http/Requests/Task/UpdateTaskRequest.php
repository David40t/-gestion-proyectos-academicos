<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends TaskRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->taskRules(),
            'status' => ['required', Rule::enum(TaskStatus::class)->only(TaskStatus::selectable())],
            'progress' => ['required', 'integer', 'between:0,100'],
        ];
    }
}
