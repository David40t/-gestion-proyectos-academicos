<?php

namespace App\Policies;

use App\Models\Audit;
use App\Models\Project;
use App\Models\User;

/**
 * La auditoría es de solo consulta (ADR-009): update y delete se niegan siempre,
 * y además no existen rutas ni métodos de repositorio para esas acciones.
 */
class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('auditoria.ver') || $user->hasPermission('auditoria.ver_todo');
    }

    public function view(User $user, Audit $audit): bool
    {
        if ($user->hasPermission('auditoria.ver_todo')) {
            return true;
        }

        return $audit->project !== null && $this->viewProjectHistory($user, $audit->project);
    }

    /**
     * Historial de auditoría de un proyecto concreto.
     */
    public function viewProjectHistory(User $user, Project $project): bool
    {
        return $user->hasPermission('auditoria.ver_todo')
            || ($user->hasPermission('auditoria.ver') && $project->isSupervisedBy($user));
    }

    public function update(User $user, Audit $audit): bool
    {
        return false;
    }

    public function delete(User $user, Audit $audit): bool
    {
        return false;
    }
}
