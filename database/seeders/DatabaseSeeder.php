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
            $this->call([DemoUserSeeder::class, DemoProjectSeeder::class]);
        }
    }
}
