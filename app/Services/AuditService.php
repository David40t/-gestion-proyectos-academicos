<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\AuditRepositoryInterface;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Registra movimientos importantes del sistema (ADR-009).
 * Los Services lo invocan dentro de la misma transacción que el cambio auditado.
 */
class AuditService
{
    /** Fragmentos de nombre de campo que nunca deben guardarse en auditoría. */
    private const SENSITIVE_FRAGMENTS = ['password', 'token', 'secret'];

    public function __construct(
        private readonly AuditRepositoryInterface $audits,
        private readonly Request $request,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  User|null  $actor  Usuario que realiza la acción; por defecto, el autenticado.
     */
    public function record(
        string $action,
        string $module,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): Audit {
        $actor ??= $this->auth->guard()->user();

        return $this->audits->create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'module' => $module,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'project_id' => $this->projectContext($auditable),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, '') ?: null,
        ]);
    }

    /**
     * Proyecto al que pertenece la entidad auditada (para consultar y limitar la auditoría por proyecto).
     */
    private function projectContext(?Model $auditable): ?int
    {
        return match (true) {
            $auditable instanceof Project => $auditable->getKey(),
            $auditable instanceof Task, $auditable instanceof Comment => $auditable->project_id,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(array $values): ?array
    {
        $clean = array_filter(
            $values,
            fn (string $key) => ! Str::contains(Str::lower($key), self::SENSITIVE_FRAGMENTS),
            ARRAY_FILTER_USE_KEY,
        );

        return $clean === [] ? null : $clean;
    }
}
