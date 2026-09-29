<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Lockout;

/**
 * Bloqueo temporal por exceso de intentos (posible ataque de fuerza bruta).
 */
class RecordLockout
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Lockout $event): void
    {
        $this->audit->record('auth.lockout', 'auth', newValues: [
            'email' => (string) $event->request->input('email', ''),
        ]);
    }
}
