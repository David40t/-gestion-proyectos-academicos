<?php

namespace App\View\Composers;

use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Datos del layout principal (contador de notificaciones no leídas).
 * Evita que la vista Blade haga consultas por su cuenta.
 */
class NavigationComposer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly Request $request,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        $view->with('unreadNotifications', $user ? $this->notifications->unreadCount($user) : 0);
    }
}
