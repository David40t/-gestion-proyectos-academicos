<?php

namespace App\Services\Notifications;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Punto único de envío de notificaciones de dominio.
 * Resuelve destinatarios, elimina duplicados y nunca notifica al autor de la acción.
 * Qué canal usa cada notificación (BD / correo) lo decide la propia notificación (ADR-008).
 */
class NotificationDispatcher
{
    public function __construct(private readonly ProjectMemberRepositoryInterface $members) {}

    /**
     * @param  iterable<User|null>|User|null  $recipients
     */
    public function send(iterable|User|null $recipients, Notification $notification, ?User $actor = null): void
    {
        $users = Collection::wrap($recipients)
            ->filter()
            ->unique(fn (User $user) => $user->id)
            ->reject(fn (User $user) => $actor && $user->is($actor));

        if ($users->isNotEmpty()) {
            NotificationFacade::send($users, $notification);
        }
    }

    /**
     * Integrantes del proyecto y, opcionalmente, su docente responsable.
     */
    public function toProject(Project $project, Notification $notification, ?User $actor = null, bool $includeTeacher = true): void
    {
        $recipients = $this->members->membersOf($project);

        if ($includeTeacher) {
            $recipients->push($project->teacher);
        }

        $this->send($recipients, $notification, $actor);
    }
}
