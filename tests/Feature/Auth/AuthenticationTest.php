<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_users_can_login_and_the_login_is_audited(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audits', ['action' => 'auth.login', 'user_id' => $user->id, 'ip_address' => '127.0.0.1']);
    }

    public function test_users_cannot_login_with_invalid_password(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('audits', ['action' => 'auth.login']);
    }

    public function test_users_can_logout_and_the_logout_is_audited(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('audits', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_web_routes_are_protected_against_csrf(): void
    {
        // Laravel omite la verificación CSRF al ejecutar pruebas; se confirma que el middleware
        // (PreventRequestForgery: token CSRF + verificación de origen) está en el grupo "web".
        $this->assertContains(
            PreventRequestForgery::class,
            app(Kernel::class)->getMiddlewareGroups()['web'],
        );
    }

    public function test_dashboard_shows_the_user_roles(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE, Role::LIDER);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Estudiante')
            ->assertSee('Líder de proyecto');
    }
}
