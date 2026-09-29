<?php

namespace Tests\Unit\Services;

use App\Models\Audit;
use App\Models\User;
use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Services\AuditContext;
use App\Services\AuditService;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\TestCase;

/**
 * Prueba unitaria pura: el repositorio se simula, no se usa base de datos.
 */
class AuditServiceTest extends TestCase
{
    public function test_it_removes_sensitive_fields_and_records_context(): void
    {
        $captured = null;
        $repository = $this->createMock(AuditRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('create')
            ->willReturnCallback(function (array $attributes) use (&$captured) {
                $captured = $attributes;

                return new Audit;
            });

        $actor = new User;
        $actor->id = 7;

        $guard = $this->createStub(Guard::class);
        $guard->method('user')->willReturn($actor);
        $auth = $this->createStub(AuthFactory::class);
        $auth->method('guard')->willReturn($guard);

        $context = new AuditContext('10.0.0.5', 'PHPUnit');

        (new AuditService($repository, $context, $auth))->record(
            'user.updated',
            'usuarios',
            newValues: ['name' => 'Ana', 'password' => 'secreta', 'remember_token' => 'x', 'api_secret' => 'y'],
        );

        $this->assertSame(7, $captured['user_id']);
        $this->assertSame(['name' => 'Ana'], $captured['new_values']);
        $this->assertNull($captured['old_values']);
        $this->assertSame('10.0.0.5', $captured['ip_address']);
        $this->assertSame('PHPUnit', $captured['user_agent']);
    }
}
