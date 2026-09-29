<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Crea un usuario con los roles indicados (siembra el catálogo si aún no existe).
     */
    protected function userWithRoles(string ...$roles): User
    {
        if (Role::count() === 0) {
            $this->seed(RolePermissionSeeder::class);
        }

        $user = User::factory()->create();
        $user->roles()->attach(
            Role::whereIn('name', $roles)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]])
        );

        return $user;
    }
}
