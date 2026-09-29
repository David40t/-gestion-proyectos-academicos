<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429)
            ->assertSee('Demasiadas solicitudes');

        $this->assertGuest();
    }

    public function test_failed_logins_and_lockouts_are_audited_without_passwords(): void
    {
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        foreach (range(1, 6) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'ClaveSecreta99']);
        }
        $this->post('/login', ['email' => 'no-existe@demo.test', 'password' => 'x']);

        $this->assertSame(5, \App\Models\Audit::where('action', 'auth.failed')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('audits', ['action' => 'auth.lockout']);
        $this->assertDatabaseHas('audits', ['action' => 'auth.failed', 'user_id' => null]); // correo inexistente
        $this->assertStringNotContainsString('ClaveSecreta99', \App\Models\Audit::all()->toJson());
    }
}
