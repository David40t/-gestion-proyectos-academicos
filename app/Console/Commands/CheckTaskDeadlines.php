<?php

namespace App\Console\Commands;

use App\Services\TaskDeadlineService;
use Illuminate\Console\Command;

/**
 * Tarea programada diaria: marca tareas vencidas y envía recordatorios de fechas próximas.
 */
class CheckTaskDeadlines extends Command
{
    protected $signature = 'tasks:check-deadlines';

    protected $description = 'Marca tareas vencidas y recuerda las próximas a vencer';

    public function handle(TaskDeadlineService $deadlines): int
    {
        $overdue = $deadlines->markOverdueTasks();
        $reminded = $deadlines->sendDueSoonReminders();

        $this->info("Tareas marcadas como vencidas: {$overdue->count()}");
        $this->info("Recordatorios enviados: {$reminded->count()}");

        return self::SUCCESS;
    }
}
