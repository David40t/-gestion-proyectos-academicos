<?php

namespace App\Repositories\Contracts;

use App\Models\Audit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Audit;

    /**
     * Búsqueda con filtros. $projectIds limita el alcance (null = sin límite; [] = nada).
     *
     * @param  array{module?: string, action?: string, user?: string, project_id?: int, from?: string, to?: string}  $filters
     * @param  list<int>|null  $projectIds
     * @return LengthAwarePaginator<int, Audit>
     */
    public function search(array $filters, ?array $projectIds, int $perPage = 20): LengthAwarePaginator;
}
