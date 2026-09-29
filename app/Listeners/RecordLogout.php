<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Logout;

class RecordLogout
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.logout', 'auth', $event->user, actor: $event->user);
        }
    }
}
