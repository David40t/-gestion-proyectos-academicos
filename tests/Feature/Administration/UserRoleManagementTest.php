<?php

namespace Tests\Feature\Administration;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRoles(Role::ADMINISTRADOR);
    }

    public function test_only_administrators_manage_roles(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);

        foreach ([Role::ESTUDIANTE, Role::DOCENTE] as $role) {
            $user = $this->userWithRoles($role);
            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
            $this->actingAs($user)->put(route('users.update', $student), ['roles' => [Role::ADMINISTRADOR]])->assertForbidden();
        }

        $this->assertFalse($student->fresh()->hasRole(Role::ADMINISTRADOR));
    }

    public function test_the_administrator_lists_and_searches_users(): void
    {
        $this->userWithRoles(Role::DOCENTE)->update(['name' => 'Profesora Ramírez']);

        $this->actingAs($this->admin)->get(route('users.index', ['q' => 'Ramírez']))
            ->assertOk()->assertSee('Profesora Ramírez')->assertSee('Docente');
    }

    public function test_the_administrator_promotes_a_student_to_teacher_and_it_is_audited(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), ['roles' => [Role::DOCENTE]])
            ->assertRedirect(route('users.index'));

        $user = $user->fresh();
        $this->assertTrue($user->hasRole(Role::DOCENTE));
        $this->assertFalse($user->hasRole(Role::ESTUDIANTE));
        $this->assertDatabaseHas('audits', ['action' => 'role.assigned', 'auditable_id' => $user->id, 'user_id' => $this->admin->id]);
        $this->assertDatabaseHas('audits', ['action' => 'role.revoked', 'auditable_id' => $user->id, 'user_id' => $this->admin->id]);
    }

    public function test_the_leader_role_cannot_be_assigned_manually(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), ['roles' => [Role::ESTUDIANTE, Role::LIDER]])
            ->assertSessionHasErrors('roles.1');
    }

    public function test_the_administrator_cannot_remove_their_own_admin_role(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->admin), ['roles' => [Role::ESTUDIANTE]])
            ->assertSessionHas('error', 'No puedes quitarte tu propio rol de administrador.');

        $this->assertTrue($this->admin->fresh()->hasRole(Role::ADMINISTRADOR));
    }

    public function test_roles_in_use_cannot_be_removed(): void
    {
        $leader = $this->userWithRoles(Role::ESTUDIANTE, Role::LIDER);
        $teacher = $this->userWithRoles(Role::DOCENTE);
        Project::factory()->withLeaderAsMember()->create(['leader_id' => $leader->id, 'teacher_id' => $teacher->id]);

        $this->actingAs($this->admin)->put(route('users.update', $leader), ['roles' => [Role::DOCENTE]])->assertSessionHas('error');
        $this->actingAs($this->admin)->put(route('users.update', $teacher), ['roles' => [Role::ESTUDIANTE]])->assertSessionHas('error');

        $this->assertTrue($leader->fresh()->hasRole(Role::ESTUDIANTE));
        $this->assertTrue($teacher->fresh()->hasRole(Role::DOCENTE));
    }

    public function test_the_leader_role_is_preserved_when_editing_other_roles(): void
    {
        $leader = $this->userWithRoles(Role::ESTUDIANTE, Role::LIDER);

        $this->actingAs($this->admin)->put(route('users.update', $leader), ['roles' => [Role::ESTUDIANTE, Role::DOCENTE]]);

        $leader = $leader->fresh();
        $this->assertTrue($leader->hasRole(Role::LIDER));
        $this->assertTrue($leader->hasRole(Role::DOCENTE));
    }

    public function test_at_least_one_role_is_required(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($this->admin)->put(route('users.update', $user), ['roles' => []])->assertSessionHasErrors('roles');
    }

    public function test_grant_admin_command(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->artisan('users:grant-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasRole(Role::ADMINISTRADOR));
        $this->assertDatabaseHas('audits', ['action' => 'role.assigned', 'auditable_id' => $user->id, 'user_id' => null]);

        $this->artisan('users:grant-admin', ['email' => 'no-existe@demo.test'])
            ->expectsOutputToContain('No existe un usuario')
            ->assertFailed();
    }

    public function test_role_management_pages_render(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($this->admin)->get(route('users.edit', $user))
            ->assertOk()->assertSee('Roles de '.$user->name)->assertSee('Administrador')->assertDontSee('value="LIDER"', false);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('users.index'), false);
    }
}
