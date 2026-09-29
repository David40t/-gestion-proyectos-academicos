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
}
