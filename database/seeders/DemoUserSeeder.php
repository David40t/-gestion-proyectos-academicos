<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de demostración (credenciales ficticias). Solo se ejecuta en entornos local/testing.
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** @var array<string, array{0: string, 1: list<string>}> email => [nombre, roles] */
    private const USERS = [
        'docente@demo.test' => ['Docente Demo', [Role::DOCENTE]],
        'lider@demo.test' => ['Líder Demo', [Role::ESTUDIANTE]], // LIDER lo asigna DemoProjectSeeder vía ProjectService
        'estudiante@demo.test' => ['Estudiante Demo', [Role::ESTUDIANTE]],
        'estudiante2@demo.test' => ['Estudiante Dos', [Role::ESTUDIANTE]],
    ];

    public function run(): void
    {
        foreach (self::USERS as $email => [$name, $roles]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ]);

            $user->roles()->syncWithoutDetaching(
                Role::whereIn('name', $roles)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]])
            );
        }
    }
}
