<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Bandeja de notificaciones del usuario (consulta y marcado como leídas).
 */
class NotificationService
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications) {}

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function inbox(User $user): LengthAwarePaginator
    {
        return $this->notifications->paginateFor($user);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCount($user);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function latestUnread(User $user, int $limit = 5): Collection
    {
        return $this->notifications->latestUnread($user, $limit);
    }

    /**
     * Marca como leída y devuelve la URL a la que apunta la notificación.
     */
    public function open(User $user, string $id): string
    {
        $notification = $this->notifications->findForUser($user, $id);
        $this->notifications->markAsRead($notification);

        return $this->internalPath($notification->data['url'] ?? null);
    }

    /**
     * Solo se redirige a rutas internas (ruta + fragmento), nunca a otro dominio:
     * evita redirecciones abiertas aunque el dato guardado fuera manipulado.
     */
    private function internalPath(?string $url): string
    {
        $path = parse_url((string) $url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return route('notifications.index', absolute: false);
        }

        $fragment = parse_url((string) $url, PHP_URL_FRAGMENT);

        return $path.($fragment ? '#'.$fragment : '');
    }

    public function markAllAsRead(User $user): int
    {
        return $this->notifications->markAllAsRead($user);
    }
}
