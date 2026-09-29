<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGate();
    }

    /**
     * Cualquier habilidad con formato "modulo.accion" (p. ej. "proyecto.crear") se resuelve
     * contra los permisos del usuario en BD. Las demás (update, view, ...) siguen a su Policy.
     * Así un rol nuevo solo requiere datos, no código (ADR-004).
     */
    private function registerPermissionGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            return str_contains($ability, '.') ? $user->hasPermission($ability) : null;
        });
    }
}
