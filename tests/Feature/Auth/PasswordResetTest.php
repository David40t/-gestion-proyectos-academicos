<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested_and_used(): void
    {
        Notification::fake();
        $user = $this->userWithRoles(Role::ESTUDIANTE);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get('/reset-password/'.$notification->token)->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NuevaClave123!',
                'password_confirmation' => 'NuevaClave123!',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('NuevaClave123!', $user->fresh()->password));
        $this->assertDatabaseHas('audits', ['action' => 'auth.password_reset', 'user_id' => $user->id]);
    }
}
