<?php

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\AuditService;
use App\Services\RoleService;
use Mockery;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    private function userWith(string ...$roles): User
    {
        $user = (new User)->forceFill(['id' => 1]);
        $user->setRelation('roles', collect($roles)->map(fn ($name) => (new Role)->forceFill(['name' => $name])));

        return $user;
    }

    public function test_assigning_an_existing_role_is_idempotent(): void
    {
        $roles = Mockery::mock(RoleRepositoryInterface::class);
        $audit = Mockery::mock(AuditService::class);
        $roles->shouldNotReceive('attachToUser');
        $audit->shouldNotReceive('record');

        (new RoleService($roles, $audit))->assign($this->userWith(Role::LIDER), Role::LIDER);
    }

    public function test_assigning_a_new_role_is_persisted_and_audited(): void
    {
        $role = (new Role)->forceFill(['id' => 2, 'name' => Role::LIDER]);
        $roles = Mockery::mock(RoleRepositoryInterface::class);
        $audit = Mockery::mock(AuditService::class);
        $roles->shouldReceive('findByName')->with(Role::LIDER)->andReturn($role);
        $roles->shouldReceive('attachToUser')->once();
        $audit->shouldReceive('record')->once()->withArgs(fn ($action) => $action === 'role.assigned');

        $user = $this->userWith(Role::ESTUDIANTE);
        (new RoleService($roles, $audit))->assign($user, Role::LIDER);
    }

    public function test_revoking_a_role_the_user_does_not_have_does_nothing(): void
    {
        $roles = Mockery::mock(RoleRepositoryInterface::class);
        $roles->shouldNotReceive('detachFromUser');

        (new RoleService($roles, Mockery::mock(AuditService::class)))->revoke($this->userWith(Role::ESTUDIANTE), Role::LIDER);
    }
}
