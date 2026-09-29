<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Recorre TODAS las rutas de la aplicación: cualquier ruta nueva queda cubierta automáticamente.
 */
class RouteProtectionTest extends TestCase
{
    /** Rutas públicas permitidas (autenticación de Fortify y utilidades del framework). */
    private const PUBLIC_ROUTES = [
        'login', 'login.store', 'register', 'register.store',
        'password.request', 'password.email', 'password.reset', 'password.update',
    ];

    /**
     * @return list<Route>
     */
    private function applicationRoutes(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->reject(fn (Route $route) => in_array($route->getName(), self::PUBLIC_ROUTES, true))
            ->reject(fn (Route $route) => in_array($route->uri(), ['/', 'up'], true))
            ->values()
            ->all();
    }

    public function test_every_non_public_route_requires_authentication(): void
    {
        $checked = 0;

        foreach ($this->applicationRoutes() as $route) {
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            // Valores que respetan las restricciones de la ruta (p. ej. whereUuid), para llegar al middleware.
            $uri = preg_replace_callback('/\{([^}?]+)\??\}/', fn ($match) => isset($route->wheres[$match[1]]) ? (string) Str::uuid() : '1', $route->uri());

            $response = $this->call($method, '/'.ltrim($uri, '/'));

            $this->assertContains($response->getStatusCode(), [302, 401],
                "La ruta {$method} /{$route->uri()} no exige autenticación (respondió {$response->getStatusCode()}).");
            if ($response->getStatusCode() === 302) {
                $this->assertStringEndsWith('/login', $response->headers->get('Location'), "{$method} /{$route->uri()}");
            }
            $checked++;
        }

        $this->assertGreaterThan(25, $checked, 'Se esperaban más rutas protegidas.');
    }

    public function test_all_state_changing_routes_use_the_web_group_with_csrf(): void
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) && ! Str::startsWith($route->uri(), '_')) {
                $this->assertContains('web', $route->gatherMiddleware(), "{$route->uri()} no pertenece al grupo web (CSRF).");
            }
        }
    }
}
