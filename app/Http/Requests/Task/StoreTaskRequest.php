<?php

namespace App\Http\Requests\Task;

class StoreTaskRequest extends TaskRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->taskRules();
        $rules['due_date'][] = 'after_or_equal:today';

        return $rules;
    }
}
