<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;

/**
 * Intentos de inicio de sesión fallidos (monitoreo de seguridad). Se registra el correo intentado,
 * NUNCA la contraseña. Si el correo pertenece a un usuario, se asocia a él.
 */
class RecordFailedLogin
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->record('auth.failed', 'auth', $user, [], [
            'email' => (string) ($event->credentials['email'] ?? ''),
        ], $user);
    }
}
