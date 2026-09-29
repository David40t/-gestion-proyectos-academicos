<?php

namespace App\Console\Commands;

use App\Services\TaskService;
use Illuminate\Console\Command;

/**
 * Tarea programada diaria: marca tareas vencidas.
 * (Los recordatorios de fechas próximas se agregan en la Fase 8, Notificaciones.)
 */
class CheckTaskDeadlines extends Command
{
    protected $signature = 'tasks:check-deadlines';

    protected $description = 'Marca como vencidas las tareas abiertas cuya fecha límite ya pasó';

    public function handle(TaskService $tasks): int
    {
        $overdue = $tasks->markOverdueTasks();

        $this->info("Tareas marcadas como vencidas: {$overdue->count()}");

        return self::SUCCESS;
    }
}
