<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\User;
use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Consulta de la auditoría (solo lectura). El registro lo hace AuditService.
 *
 * Alcance:
 *  - auditoria.ver_todo → toda la auditoría (reservado para un futuro rol administrador).
 *  - auditoria.ver      → solo lo ocurrido en los proyectos que el usuario supervisa.
 */
class AuditQueryService
{
    public function __construct(
        private readonly AuditRepositoryInterface $audits,
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  Filtros validados.
     * @return LengthAwarePaginator<int, Audit>
     */
    public function search(User $viewer, array $filters): LengthAwarePaginator
    {
        return $this->audits->search($filters, $this->visibleProjectIds($viewer));
    }

    /**
     * Proyectos que el usuario puede usar como filtro.
     *
     * @return Collection<int, \App\Models\Project>
     */
    public function projectOptions(User $viewer): Collection
    {
        return $this->projects->options($this->visibleProjectIds($viewer));
    }

    /**
     * @return list<int>|null null = sin restricción.
     */
    public function visibleProjectIds(User $viewer): ?array
    {
        return $viewer->hasPermission('auditoria.ver_todo') ? null : $this->projects->idsSupervisedBy($viewer);
    }
}
