<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Compone el dashboard según la perspectiva del usuario (docs/03 y enunciado §20-21).
 * Es el único lugar donde el contenido depende del rol: el enunciado pide explícitamente
 * un dashboard distinto por rol. Todos los datos salen de consultas agregadas en los repositorios.
 */
class DashboardService
{
    public const UPCOMING_DAYS = 7;

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly TaskRepositoryInterface $tasks,
        private readonly CommentRepositoryInterface $comments,
        private readonly AuditRepositoryInterface $audits,
        private readonly NotificationService $notifications,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return match (true) {
            $user->hasGlobalAccess() => $this->administrator($user),
            $user->hasRole(Role::DOCENTE) => $this->teacher($user),
            default => $this->student($user),
        };
    }

    /**
     * Estudiante (y líder, que es un estudiante con una sección adicional).
     *
     * @return array<string, mixed>
     */
    private function student(User $user): array
    {
        $projects = $this->projects->withStatsWhereMember($user);
        $taskStats = $this->tasks->deadlineStats($user, null, $this->upcomingLimit());

        return [
            'perspective' => 'student',
            'stats' => $this->stats('Mis proyectos', $projects, $taskStats, 'Mis tareas pendientes', $user),
            'projects' => $projects,
            'ledProjects' => $user->hasRole(Role::LIDER) ? $projects->where('leader_id', $user->id)->values() : collect(),
            'nextTasks' => $this->tasks->nextOpen($user, null),
            'notifications' => $this->notifications->latestUnread($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teacher(User $user): array
    {
        $projects = $this->projects->withStatsSupervisedBy($user);
        $projectIds = $projects->modelKeys();
        $taskStats = $this->tasks->deadlineStats(null, $projectIds, $this->upcomingLimit());

        return [
            'perspective' => 'teacher',
            'stats' => $this->stats('Proyectos supervisados', $projects, $taskStats, 'Tareas pendientes', $user),
            'projects' => $projects,
            'nextTasks' => $this->tasks->nextOpen(null, $projectIds),
            'recentComments' => $this->comments->recentInProjects($projectIds),
            'recentActivity' => $this->audits->recentInProjects($projectIds),
            'notifications' => $this->notifications->latestUnread($user),
        ];
    }

    /**
     * Vista global del sistema (todas las consultas sin filtro de proyecto).
     *
     * @return array<string, mixed>
     */
    private function administrator(User $user): array
    {
        $totals = $this->projects->totals();
        $taskStats = $this->tasks->deadlineStats(null, null, $this->upcomingLimit());
        $usersByRole = $this->users->countByRole();

        return [
            'perspective' => 'admin',
            'stats' => [
                ['label' => 'Usuarios', 'value' => array_sum(array_intersect_key($usersByRole, array_flip(Role::ASSIGNABLE))), 'tone' => 'neutral'],
                ['label' => 'Proyectos', 'value' => $totals['total'], 'tone' => 'neutral'],
                ['label' => 'Proyectos activos', 'value' => $totals['active'], 'tone' => 'primary'],
                ['label' => 'Tareas pendientes', 'value' => $taskStats['pending'], 'tone' => 'neutral'],
                ['label' => 'Vencen en '.self::UPCOMING_DAYS.' días', 'value' => $taskStats['due_soon'], 'tone' => 'warning'],
                ['label' => 'Tareas vencidas', 'value' => $taskStats['overdue'], 'tone' => 'danger'],
            ],
            'usersByRole' => $usersByRole,
            'projects' => $this->projects->withStatsLatest(),
            'nextTasks' => $this->tasks->nextOpen(null, null),
            'recentComments' => $this->comments->recentInProjects(null),
            'recentActivity' => $this->audits->recentInProjects(null),
            'notifications' => $this->notifications->latestUnread($user),
        ];
    }

    /**
     * Tarjetas de indicadores comunes a todas las perspectivas.
     *
     * @param  Collection<int, \App\Models\Project>  $projects
     * @param  array{pending: int, due_soon: int, overdue: int}  $taskStats
     * @return list<array{label: string, value: int, tone: string}>
     */
    private function stats(string $projectsLabel, Collection $projects, array $taskStats, string $pendingLabel, User $user): array
    {
        return [
            ['label' => $projectsLabel, 'value' => $projects->count(), 'tone' => 'neutral'],
            ['label' => 'Proyectos activos', 'value' => $projects->filter(fn ($project) => in_array($project->status, ProjectStatus::active(), true))->count(), 'tone' => 'primary'],
            ['label' => $pendingLabel, 'value' => $taskStats['pending'], 'tone' => 'neutral'],
            ['label' => 'Vencen en '.self::UPCOMING_DAYS.' días', 'value' => $taskStats['due_soon'], 'tone' => 'warning'],
            ['label' => 'Tareas vencidas', 'value' => $taskStats['overdue'], 'tone' => 'danger'],
            ['label' => 'Notificaciones sin leer', 'value' => $this->notifications->unreadCount($user), 'tone' => 'primary'],
        ];
    }

    private function upcomingLimit(): Carbon
    {
        return Carbon::today()->addDays(self::UPCOMING_DAYS);
    }
}
