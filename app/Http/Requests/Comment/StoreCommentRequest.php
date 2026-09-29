<?php

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Que la tarea pertenezca al proyecto lo garantiza CommentService (regla de negocio, ADR-007).
        return [
            'body' => ['required', 'string', 'max:2000'],
            'task_id' => ['nullable', 'integer'],
            'is_observation' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['body' => 'comentario', 'task_id' => 'tarea', 'is_observation' => 'observación'];
    }
}
