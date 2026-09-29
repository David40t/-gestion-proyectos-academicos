<?php

namespace Tests\Architecture;

use App\Providers\RepositoryServiceProvider;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Hace cumplir las reglas de la arquitectura en capas (docs/01, §2) analizando el código fuente.
 * Si alguien viola una regla (p. ej. una consulta en un Controller), esta prueba falla.
 */
class LayeredArchitectureTest extends TestCase
{
    private const BASE = __DIR__.'/../../';

    /** Consultas Eloquent/DB estáticas: `Modelo::where(`, `DB::table(`, etc. */
    private const STATIC_QUERY = '/\b(?!Rule\b|Validator\b|Route\b|Gate\b)[A-Z]\w*::(query|where\w*|find\w*|all|create|with|first\w*|count|pluck|latest|paginate)\(|\bDB::(table|select|insert|update|delete|raw)\(/';

    /**
     * @return array<string, string> ruta => contenido
     */
    private static function files(string $directory, string $pattern = '*.php'): array
    {
        $files = [];
        foreach ((new Finder)->files()->in(self::BASE.$directory)->name($pattern) as $file) {
            $files[Str::after($file->getRealPath(), realpath(self::BASE).'/')] = $file->getContents();
        }

        return $files;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function forbiddenDependencies(): array
    {
        return [
            'Controllers no usan repositorios (pasan por Services)' => ['app/Http/Controllers', '/use App\\\\Repositories\\\\/', 'repositorio'],
            'Services dependen de interfaces, no de Eloquent' => ['app/Services', '/use App\\\\Repositories\\\\Eloquent\\\\/', 'implementación Eloquent'],
            'Services no dependen de HTTP' => ['app/Services', '/use Illuminate\\\\Http\\\\(Request|Response|RedirectResponse)|use App\\\\Http\\\\/', 'capa HTTP'],
            'Repositorios no dependen de Services ni HTTP' => ['app/Repositories', '/use App\\\\(Services|Http)\\\\/', 'capa superior'],
            'Modelos no dependen de capas superiores' => ['app/Models', '/use App\\\\(Services|Repositories|Http)\\\\/', 'capa superior'],
            'Policies no usan implementaciones Eloquent' => ['app/Policies', '/use App\\\\Repositories\\\\Eloquent\\\\/', 'implementación Eloquent'],
        ];
    }

    #[DataProvider('forbiddenDependencies')]
    public function test_layer_dependencies(string $directory, string $pattern, string $what): void
    {
        foreach (self::files($directory) as $path => $code) {
            $this->assertDoesNotMatchRegularExpression($pattern, $code, "{$path} depende de una {$what}.");
        }
    }

    public function test_controllers_and_services_do_not_query_models_directly(): void
    {
        foreach ([...self::files('app/Http/Controllers'), ...self::files('app/Services')] as $path => $code) {
            $this->assertDoesNotMatchRegularExpression(self::STATIC_QUERY, $code, "{$path} consulta la base de datos directamente; usa un repositorio.");
        }
    }

    public function test_blade_views_do_not_query_the_database(): void
    {
        foreach (self::files('resources/views', '*.blade.php') as $path => $code) {
            $this->assertDoesNotMatchRegularExpression(self::STATIC_QUERY, $code, "{$path} ejecuta una consulta estática.");
            $this->assertDoesNotMatchRegularExpression('/->(\w+)\(\)->(where|get|first|paginate|count)\(/', $code, "{$path} construye una consulta sobre una relación.");
        }
    }

    public function test_every_repository_contract_is_bound_to_an_implementation(): void
    {
        $bindings = (new \ReflectionClass(RepositoryServiceProvider::class))->getProperty('bindings')->getDefaultValue();

        foreach (self::files('app/Repositories/Contracts') as $path => $code) {
            $interface = 'App\\Repositories\\Contracts\\'.basename($path, '.php');
            $this->assertArrayHasKey($interface, $bindings, "{$interface} no está enlazada en RepositoryServiceProvider.");
            $this->assertContains($interface, class_implements($bindings[$interface]), "{$bindings[$interface]} no implementa {$interface}.");
        }
    }

    public function test_controllers_extend_the_base_controller_and_stay_small(): void
    {
        foreach (self::files('app/Http/Controllers') as $path => $code) {
            if (str_ends_with($path, '/Controller.php')) {
                continue;
            }
            $this->assertMatchesRegularExpression('/extends Controller\b/', $code, "{$path} no extiende el Controller base.");
            $this->assertLessThan(150, substr_count($code, "\n"), "{$path} es demasiado grande: mueve lógica a un Service.");
        }
    }

    public function test_services_stay_focused(): void
    {
        foreach (self::files('app/Services') as $path => $code) {
            $this->assertLessThan(250, substr_count($code, "\n"), "{$path} es demasiado grande: considera separarlo.");
        }
    }

    public function test_audit_repository_exposes_no_update_or_delete(): void
    {
        foreach (['app/Repositories/Contracts/AuditRepositoryInterface.php', 'app/Repositories/Eloquent/AuditRepository.php'] as $path) {
            $this->assertDoesNotMatchRegularExpression('/function\s+(update|delete|destroy|forceDelete)/i', file_get_contents(self::BASE.$path), "{$path} permite modificar auditoría.");
        }
    }
}
