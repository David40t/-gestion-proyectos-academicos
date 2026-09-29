<?php

namespace Tests\Feature\Security;

use App\Enums\ProjectStatus;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    private function assertSecurityHeaders($response): void
    {
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_security_headers_on_pages_and_error_pages(): void
    {
        $this->assertSecurityHeaders($this->get('/login'));
        $this->assertSecurityHeaders($this->get('/ruta-que-no-existe')->assertNotFound());
        $this->assertSecurityHeaders($this->actingAs($this->userWithRoles(Role::ESTUDIANTE))->get('/dashboard'));
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_views_have_no_inline_scripts_or_styles_so_the_csp_holds(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso);

        $html = $this->actingAs($this->leader)->get(route('projects.show', $this->project))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<script>(?!\s*<\/script>)|<script(?![^>]*\bsrc=)[^>]*>/i', $html);
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html);
        $this->assertDoesNotMatchRegularExpression('/\son(click|submit|load|change)=/i', $html);
    }

    public function test_notifications_never_redirect_to_external_sites(): void
    {
        $this->createProjectScenario();
        $id = (string) Str::uuid();
        $this->member->notifications()->create([
            'id' => $id,
            'type' => 'manipulada',
            'data' => ['title' => 'x', 'message' => 'x', 'url' => 'https://sitio-malicioso.example/robar'],
        ]);

        $location = $this->actingAs($this->member)->patch(route('notifications.read', $id))->headers->get('Location');

        $this->assertStringNotContainsString('sitio-malicioso', $location);
        $this->assertStringStartsWith(url('/'), $location);
    }

    public function test_password_policy_requires_letters_and_numbers(): void
    {
        foreach (['corta1', 'sinnumeros', '12345678'] as $weak) {
            $this->post('/register', [
                'name' => 'Ana', 'email' => Str::random(6).'@demo.test',
                'password' => $weak, 'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        $this->assertGuest();
    }

    public function test_public_auth_forms_are_rate_limited(): void
    {
        foreach (range(1, 30) as $i) {
            $this->post('/forgot-password', ['email' => "u{$i}@demo.test"]);
        }

        $this->post('/forgot-password', ['email' => 'otro@demo.test'])->assertStatus(429);
    }

    public function test_session_cookie_is_http_only_and_same_site(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }
}
