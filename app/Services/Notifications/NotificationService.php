<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

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
     * Marca como leída y devuelve la URL a la que apunta la notificación.
     */
    public function open(User $user, string $id): string
    {
        $notification = $this->notifications->findForUser($user, $id);
        $this->notifications->markAsRead($notification);

        return $notification->data['url'] ?? route('notifications.index');
    }

    public function markAllAsRead(User $user): int
    {
        return $this->notifications->markAllAsRead($user);
    }
}
