<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_new_users_are_registered_as_students_with_hashed_password(): void
    {
        $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@demo.test',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'ana@demo.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole(Role::ESTUDIANTE));
        $this->assertCount(1, $user->roles);
        $this->assertNotSame('Secreta123!', $user->password);
        $this->assertTrue(Hash::check('Secreta123!', $user->password));
    }

    public function test_registration_cannot_escalate_role(): void
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@demo.test',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
            'role' => Role::DOCENTE,
            'roles' => [Role::DOCENTE],
        ]);

        $user = User::where('email', 'intruso@demo.test')->firstOrFail();

        $this->assertFalse($user->hasRole(Role::DOCENTE));
        $this->assertTrue($user->hasRole(Role::ESTUDIANTE));
    }

    public function test_registration_is_audited_without_sensitive_data(): void
    {
        $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@demo.test',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
        ]);

        $user = User::where('email', 'ana@demo.test')->firstOrFail();

        $this->assertDatabaseHas('audits', ['action' => 'auth.registered', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audits', ['action' => 'role.assigned', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('audits', ['new_values' => '%Secreta123!%']);
        $this->assertStringNotContainsString('Secreta123!', json_encode(\App\Models\Audit::all()->toArray()));
    }

    public function test_registration_validates_input(): void
    {
        $this->post('/register', ['name' => '', 'email' => 'no-es-correo', 'password' => '1', 'password_confirmation' => '2'])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }
}
