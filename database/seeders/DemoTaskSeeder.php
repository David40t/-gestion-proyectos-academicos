<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use App\Services\TaskService;
use Illuminate\Database\Seeder;

/**
 * Tareas de demostración creadas mediante TaskService (estado y avance coherentes, auditoría).
 * Cubre todos los estados para que el seguimiento y el dashboard tengan datos.
 */
class DemoTaskSeeder extends Seeder
{
    public function run(TaskService $tasks, ProjectService $projects): void
    {
        $project = Project::where('title', 'Sistema de gestión de proyectos académicos')->firstOrFail();

        if ($project->tasks()->exists()) {
            return; // idempotente
        }

        $leader = $project->leader;
        $student = User::where('email', 'estudiante@demo.test')->firstOrFail();

        $projects->changeStatus($project, ProjectStatus::EnProgreso, $leader);

        $definitions = [
            // [título, prioridad, responsable, días inicio, días límite, estado, avance]
            ['Levantamiento de requerimientos', 'alta', $leader, -14, -7, TaskStatus::Completada, 100],
            ['Modelo entidad-relación', 'alta', $student, -10, 2, TaskStatus::EnProgreso, 60],
            ['Diagrama de arquitectura en draw.io', 'media', $leader, -5, 5, TaskStatus::EnProgreso, 30],
            ['Prototipo de interfaz', 'media', $student, -3, -1, TaskStatus::EnProgreso, 20],
            ['Plan de pruebas', 'baja', null, 0, 20, TaskStatus::Pendiente, 0],
        ];

        foreach ($definitions as [$title, $priority, $assignee, $start, $due, $status, $progress]) {
            $task = $tasks->create($project, [
                'title' => $title,
                'description' => "Actividad de demostración: {$title}.",
                'priority' => $priority,
                'assigned_to' => $assignee?->id,
                'start_date' => now()->addDays($start)->toDateString(),
                'due_date' => now()->addDays($due)->toDateString(),
            ], $leader);

            if ($status !== TaskStatus::Pendiente) {
                $tasks->updateProgress($task, $status, $progress, $assignee ?? $leader);
            }
        }
    }
}
