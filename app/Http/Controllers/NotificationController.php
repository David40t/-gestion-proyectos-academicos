<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja de notificaciones internas. Cada usuario solo accede a las suyas:
 * NotificationService las busca siempre dentro de las del usuario autenticado (404 si son ajenas).
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $this->notifications->inbox($request->user()),
        ]);
    }

    /**
     * Marca como leída y lleva al usuario al recurso relacionado.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        return redirect()->to($this->notifications->open($request->user(), $notification));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $count = $this->notifications->markAllAsRead($request->user());

        return back()->with('success', "Se marcaron {$count} notificaciones como leídas.");
    }
}
