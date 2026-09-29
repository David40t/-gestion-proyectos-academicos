<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.login', 'auth', $event->user, actor: $event->user);
        }
    }
}
