<?php

namespace App\Repositories\Eloquent;

use App\Models\Audit;
use App\Repositories\Contracts\AuditRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Solo expone escritura (create) y lectura (search): no existen métodos para modificar ni eliminar.
 */
class AuditRepository implements AuditRepositoryInterface
{
    public function create(array $attributes): Audit
    {
        return Audit::create($attributes);
    }

    public function search(array $filters, ?array $projectIds, int $perPage = 20): LengthAwarePaginator
    {
        return Audit::query()
            ->with(['user:id,name,email', 'project:id,title'])
            ->when($projectIds !== null, fn (Builder $query) => $query->whereIn('project_id', $projectIds))
            ->when($filters['project_id'] ?? null, fn (Builder $query, $id) => $query->where('project_id', $id))
            ->when($filters['module'] ?? null, fn (Builder $query, $module) => $query->where('module', $module))
            ->when($filters['action'] ?? null, fn (Builder $query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['user'] ?? null, fn (Builder $query, $term) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function recentInProjects(array $projectIds, int $limit = 8): Collection
    {
        return Audit::query()
            ->with(['user:id,name', 'project:id,title'])
            ->whereIn('project_id', $projectIds)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
