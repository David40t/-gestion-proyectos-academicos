<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditContext;
use App\View\Composers\NavigationComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Contexto de auditoría desde el request actual: AuditService no depende de la capa HTTP.
        $this->app->bind(AuditContext::class, fn ($app) => $app->runningInConsole() && ! $app->runningUnitTests()
            ? new AuditContext
            : new AuditContext($app['request']->ip(), $app['request']->userAgent()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Modo estricto fuera de producción: excepción ante consultas N+1 (lazy loading en listados),
        // atributos no permitidos descartados en silencio (asignación masiva) o atributos inexistentes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Política de contraseñas: mínimo 8 caracteres, con letras y números.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->registerPermissionGate();

        // Marcado HTML simple para la paginación; los estilos están en public/css/app.css.
        Paginator::useBootstrapFive();

        View::composer(['layouts.app', 'notifications.index'], NavigationComposer::class);

        $this->registerDateMessageReplacers();
    }

    /**
     * Mensajes de reglas de fecha legibles: "hoy", nombre del campo comparado o fecha en d/m/Y
     * (en lugar de "today" o "2026-09-01").
     */
    private function registerDateMessageReplacers(): void
    {
        foreach (['after', 'after_or_equal', 'before', 'before_or_equal', 'date_equals'] as $rule) {
            Validator::replacer($rule, function (string $message, string $attribute, string $rule, array $parameters, $validator) {
                $reference = $parameters[0] ?? '';

                $display = match (true) {
                    in_array($reference, ['today', 'now'], true) => 'hoy',
                    $reference === 'tomorrow' => 'mañana',
                    $reference === 'yesterday' => 'ayer',
                    array_key_exists($reference, $validator->getData()) => $validator->getDisplayableAttribute($reference),
                    strtotime($reference) !== false => Carbon::parse($reference)->format('d/m/Y'),
                    default => $reference,
                };

                return str_replace(':date', $display, $message);
            });
        }
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
