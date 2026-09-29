<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seguimiento del proyecto calculado a partir de sus tareas; nada se almacena (ADR-005).
 */
class ProgressService
{
    public const UPCOMING_DAYS = 7;

    public function __construct(private readonly TaskRepositoryInterface $tasks) {}

    /**
     * @return array{progress: float, total: int, counts: array<string, int>, upcoming: Collection}
     */
    public function summary(Project $project): array
    {
        $counts = $this->tasks->countByStatus($project);

        return [
            'progress' => round($this->tasks->averageProgress($project), 1),
            'total' => array_sum($counts),
            // Todos los estados presentes, aunque tengan 0 tareas.
            'counts' => collect(TaskStatus::cases())
                ->mapWithKeys(fn (TaskStatus $status) => [$status->value => $counts[$status->value] ?? 0])
                ->all(),
            'upcoming' => $this->tasks->upcoming($project, Carbon::today()->addDays(self::UPCOMING_DAYS)),
        ];
    }
}
