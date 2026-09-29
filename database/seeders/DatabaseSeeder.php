<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Catálogo base: necesario en cualquier entorno.
        $this->call(RolePermissionSeeder::class);

        // Datos de demostración: nunca en producción.
        if (app()->environment(['local', 'testing'])) {
            // Los datos demo se crean con los Services, que generan notificaciones.
            // Se guardan de inmediato en BD (cola síncrona) y sus correos van al log: sembrar nunca envía correos reales.
            config(['queue.default' => 'sync', 'mail.default' => 'log']);

            $this->call([DemoUserSeeder::class, DemoProjectSeeder::class, DemoTaskSeeder::class, DemoCommentSeeder::class]);
        }
    }
}
