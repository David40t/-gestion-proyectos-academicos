<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;

class RecordPasswordReset
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.password_reset', 'auth', $event->user, actor: $event->user);
        }
    }
}
