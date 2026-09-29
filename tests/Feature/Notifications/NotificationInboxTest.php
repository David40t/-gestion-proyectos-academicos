<?php

namespace Tests\Feature\Notifications;

use App\Enums\ProjectStatus;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

/**
 * Bandeja interna con notificaciones reales (cola "sync" en pruebas).
 */
class NotificationInboxTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $this->actingAs($this->leader)->patch(route('projects.status.update', $this->project), ['status' => 'en_revision']);
    }

    public function test_notifications_are_stored_and_listed(): void
    {
        $this->assertSame(1, $this->member->unreadNotifications()->count());

        $this->actingAs($this->member)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('En revisión')
            ->assertSee('notification-count', false);
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $notification = $this->member->notifications()->firstOrFail();

        $this->actingAs($this->member)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('projects.show', $this->project));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_users_cannot_open_notifications_of_others(): void
    {
        $notification = $this->member->notifications()->firstOrFail();

        $this->actingAs($this->teacher)->patch(route('notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $this->actingAs($this->member)->patch(route('notifications.read-all'))->assertSessionHas('success');

        $this->assertSame(0, $this->member->unreadNotifications()->count());
    }

    public function test_invalid_notification_ids_are_rejected(): void
    {
        $this->actingAs($this->member)->patch('/notifications/no-es-uuid/read')->assertNotFound();
        $this->actingAs($this->member)->patch('/notifications/'.Str::uuid().'/read')->assertNotFound();
    }

    public function test_notification_email_is_rendered_in_spanish(): void
    {
        $notification = new \App\Notifications\Project\MemberAdded($this->project, $this->leader);
        $html = (string) $notification->toMail($this->member)->render();

        $this->assertStringContainsString('Hola, '.$this->member->name, $html);
        $this->assertStringContainsString('Ver en el sistema', $html);
        $this->assertStringContainsString('Saludos', $html);
    }
}
