<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Services\UserService;
use Illuminate\Console\Command;

/**
 * Designa al primer administrador en un entorno sin datos demo (p. ej. producción):
 * el usuario se registra normalmente y luego se ejecuta este comando en el servidor.
 */
class GrantAdministrator extends Command
{
    protected $signature = 'users:grant-admin {email : Correo de un usuario ya registrado}';

    protected $description = 'Asigna el rol ADMINISTRADOR a un usuario existente (queda auditado)';

    public function handle(UserService $users): int
    {
        try {
            $user = $users->grantAdministrator($this->argument('email'));
        } catch (BusinessRuleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$user->name} ({$user->email}) ahora es administrador.");

        return self::SUCCESS;
    }
}
