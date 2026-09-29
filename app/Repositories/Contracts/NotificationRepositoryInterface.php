<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

interface NotificationRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateFor(User $user, int $perPage = 15): LengthAwarePaginator;

    public function unreadCount(User $user): int;

    /**
     * Busca solo entre las notificaciones del propio usuario (404 si es ajena).
     */
    public function findForUser(User $user, string $id): DatabaseNotification;

    public function markAsRead(DatabaseNotification $notification): void;

    public function markAllAsRead(User $user): int;
}
