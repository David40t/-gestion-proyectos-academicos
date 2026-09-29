<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Database\Seeder;

/**
 * Comentarios y una observación docente de demostración, creados mediante CommentService.
 */
class DemoCommentSeeder extends Seeder
{
    public function run(CommentService $comments): void
    {
        $project = Project::where('title', 'Sistema de gestión de proyectos académicos')->firstOrFail();

        if ($project->comments()->exists()) {
            return; // idempotente
        }

        $teacher = $project->teacher;
        $student = User::where('email', 'estudiante@demo.test')->firstOrFail();
        $task = $project->tasks()->where('title', 'Modelo entidad-relación')->firstOrFail();

        $comments->create($project, [
            'body' => 'Revisé la propuesta. Justifiquen en el documento por qué eligieron un monolito modular.',
            'is_observation' => true,
        ], $teacher);

        $comments->create($project, [
            'body' => 'Profesor, agregamos la justificación en docs/04 (ADR-001).',
        ], $project->leader);

        $comments->create($project, [
            'body' => 'Ya normalicé la tabla de comentarios; falta revisar índices.',
            'task_id' => $task->id,
        ], $student);
    }
}
