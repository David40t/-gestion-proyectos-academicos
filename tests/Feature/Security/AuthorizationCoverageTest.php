<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Toda acción de un Controller propio debe autorizar explícitamente (Policy/Gate) o estar
 * protegida por middleware "can:". Ocultar botones en Blade nunca es suficiente (enunciado §6).
 */
class AuthorizationCoverageTest extends TestCase
{
    /**
     * Acciones sin autorización adicional, con su justificación (solo requieren sesión).
     */
    private const EXEMPT = [
        'App\Http\Controllers\DashboardController' => 'Solo muestra datos del propio usuario (DashboardService filtra por él).',
        'App\Http\Controllers\TaskController@index' => 'Solo redirige al detalle del proyecto, que sí autoriza.',
    ];

    public function test_every_controller_action_is_authorized(): void
    {
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();
            if (! Str::startsWith($action, 'App\\Http\\Controllers\\') || array_key_exists($action, self::EXEMPT)) {
                continue;
            }

            [$class, $method] = str_contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];
            $body = $this->methodSource(new ReflectionMethod($class, $method));
            $hasCanMiddleware = collect($route->gatherMiddleware())->contains(fn ($m) => Str::startsWith($m, 'can:'));

            $this->assertTrue(
                $hasCanMiddleware || str_contains($body, '$this->authorize('),
                "{$action} ({$route->uri()}) no autoriza: usa \$this->authorize() o middleware can:."
            );
            $checked++;
        }

        $this->assertGreaterThan(20, $checked);
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
