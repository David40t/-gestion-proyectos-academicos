<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use Carbon\CarbonInterface;

/**
 * Regla única de coherencia entre estado, avance y fecha límite de una tarea (docs/03, §4).
 * Clase pura (sin base de datos) para poder probarla de forma unitaria.
 *
 *  - completada           ⇔ avance 100
 *  - pendiente            ⇔ avance 0
 *  - en progreso          ⇔ avance 1..99 (un avance > 0 en una tarea pendiente la pasa a en progreso)
 *  - vencida              ⇐ fecha límite anterior a hoy y no completada (conserva su avance)
 */
class TaskStateResolver
{
    /**
     * @return array{status: TaskStatus, progress: int}
     */
    public function resolve(TaskStatus $requested, int $progress, CarbonInterface $dueDate, CarbonInterface $today): array
    {
        if ($progress < 0 || $progress > 100) {
            throw new BusinessRuleException('El avance debe estar entre 0 y 100.');
        }

        if ($requested === TaskStatus::Vencida) {
            throw new BusinessRuleException('El estado "Vencida" lo asigna únicamente el sistema.');
        }

        if ($requested === TaskStatus::Completada || $progress === 100) {
            return ['status' => TaskStatus::Completada, 'progress' => 100];
        }

        if ($requested === TaskStatus::EnProgreso && $progress === 0) {
            throw new BusinessRuleException('Una tarea en progreso debe tener un avance mayor a 0%.');
        }

        $status = $progress > 0 ? TaskStatus::EnProgreso : TaskStatus::Pendiente;

        if ($dueDate->lt($today->copy()->startOfDay())) {
            $status = TaskStatus::Vencida;
        }

        return ['status' => $status, 'progress' => $progress];
    }
}
