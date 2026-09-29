<?php

namespace Tests\Feature\Notifications;

use App\Enums\ProjectStatus;
use App\Models\Role;
use App\Models\Task;
use App\Notifications\Comment\CommentPosted;
use App\Notifications\Project\MemberAdded;
use App\Notifications\Project\MemberRemoved;
use App\Notifications\Project\ProjectLeaderChanged;
use App\Notifications\Project\ProjectStatusChanged;
use App\Notifications\Project\ProjectUpdated;
use App\Notifications\Task\TaskAssigned;
use App\Notifications\Task\TaskCompleted;
use App\Notifications\Task\TaskDueSoon;
use App\Notifications\Task\TaskOverdue;
use App\Notifications\Task\TaskUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

/**
 * Qué evento notifica a quién y por qué canal (docs/03, §7).
 */
class DomainNotificationTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $this->project->update(['start_date' => now()->subMonth(), 'end_date' => now()->addMonths(2)]);
    }

    private function channels(string $notification, object $user): array
    {
        $channels = [];
        Notification::assertSentTo($user, $notification, function ($sent, array $via) use (&$channels) {
            $channels = $via;

            return true;
        });

        return $channels;
    }

    public function test_status_change_notifies_participants_but_not_the_actor(): void
    {
        $this->actingAs($this->leader)->patch(route('projects.status.update', $this->project), ['status' => 'en_revision']);

        Notification::assertSentTo([$this->member, $this->teacher], ProjectStatusChanged::class);
        Notification::assertNotSentTo($this->leader, ProjectStatusChanged::class);
        Notification::assertNotSentTo($this->outsider, ProjectStatusChanged::class);
        $this->assertSame(['database'], $this->channels(ProjectStatusChanged::class, $this->member));
    }

    public function test_closing_a_project_also_sends_email(): void
    {
        $this->project->update(['status' => ProjectStatus::EnRevision]);

        $this->actingAs($this->teacher)->patch(route('projects.status.update', $this->project), ['status' => 'finalizado']);

        $this->assertSame(['database', 'mail'], $this->channels(ProjectStatusChanged::class, $this->member));
        Notification::assertNotSentTo($this->teacher, ProjectStatusChanged::class);
    }

    public function test_important_project_changes_notify_members(): void
    {
        $this->actingAs($this->leader)->put(route('projects.update', $this->project), [
            'title' => 'Título nuevo',
            'description' => $this->project->description,
            'start_date' => $this->project->start_date->toDateString(),
            'end_date' => $this->project->end_date->toDateString(),
            'teacher_id' => $this->teacher->id,
        ]);

        Notification::assertSentTo($this->member, ProjectUpdated::class,
            fn ($notification) => str_contains($notification->toArray($this->member)['message'], 'título'));
    }

    public function test_member_added_and_removed_are_notified_by_email(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($this->leader)->post(route('projects.members.store', $this->project), ['user_id' => $student->id]);
        $this->assertSame(['database', 'mail'], $this->channels(MemberAdded::class, $student));

        $this->actingAs($this->leader)->delete(route('projects.members.destroy', [$this->project, $student]));
        $this->assertSame(['database', 'mail'], $this->channels(MemberRemoved::class, $student));
    }

    public function test_only_the_new_leader_gets_an_email_on_leader_change(): void
    {
        $this->actingAs($this->leader)->patch(route('projects.leader.update', $this->project), ['leader_id' => $this->member->id]);

        $this->assertSame(['database', 'mail'], $this->channels(ProjectLeaderChanged::class, $this->member));
        $this->assertSame(['database'], $this->channels(ProjectLeaderChanged::class, $this->teacher));
    }

    public function test_task_assignment_update_and_completion(): void
    {
        $this->actingAs($this->leader)->post(route('projects.tasks.store', $this->project), [
            'title' => 'Nueva',
            'priority' => 'media',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'assigned_to' => $this->member->id,
        ]);
        $this->assertSame(['database', 'mail'], $this->channels(TaskAssigned::class, $this->member));

        $task = Task::firstOrFail();
        $this->actingAs($this->leader)->patch(route('projects.tasks.progress.update', [$this->project, $task]), ['status' => 'en_progreso', 'progress' => 20]);
        $this->assertSame(['database'], $this->channels(TaskUpdated::class, $this->member));

        $this->actingAs($this->member)->patch(route('projects.tasks.progress.update', [$this->project, $task]), ['status' => 'completada', 'progress' => 100]);
        Notification::assertSentTo($this->leader, TaskCompleted::class);
        Notification::assertNotSentTo($this->member, TaskCompleted::class);
    }

    public function test_teacher_observation_notifies_members_by_email(): void
    {
        $this->actingAs($this->teacher)->post(route('projects.comments.store', $this->project), ['body' => 'Revisar', 'is_observation' => '1']);

        $this->assertSame(['database', 'mail'], $this->channels(CommentPosted::class, $this->member));
        $this->assertSame(['database', 'mail'], $this->channels(CommentPosted::class, $this->leader));
        Notification::assertNotSentTo($this->teacher, CommentPosted::class);
    }

    public function test_student_comment_notifies_leader_and_teacher_internally(): void
    {
        $this->actingAs($this->member)->post(route('projects.comments.store', $this->project), ['body' => 'Duda']);

        $this->assertSame(['database'], $this->channels(CommentPosted::class, $this->leader));
        $this->assertSame(['database'], $this->channels(CommentPosted::class, $this->teacher));
        Notification::assertNotSentTo($this->member, CommentPosted::class);
    }

    public function test_deadline_command_sends_overdue_and_due_soon_notifications_once(): void
    {
        $factory = Task::factory()->state(['project_id' => $this->project->id, 'assigned_to' => $this->member->id]);
        $late = $factory->inProgress(30)->create(['start_date' => now()->subDays(5), 'due_date' => now()->subDay()]);
        $soon = $factory->create(['due_date' => now()->addDays(2)]);
        $factory->create(['due_date' => now()->addDays(10)]); // lejana: sin recordatorio

        $this->artisan('tasks:check-deadlines')
            ->expectsOutputToContain('Tareas marcadas como vencidas: 1')
            ->expectsOutputToContain('Recordatorios enviados: 1')
            ->assertSuccessful();

        Notification::assertSentTo([$this->member, $this->leader], TaskOverdue::class);
        $this->assertSame(['database', 'mail'], $this->channels(TaskDueSoon::class, $this->member));
        $this->assertNotNull($soon->fresh()->due_reminder_sent_at);

        // Segunda ejecución: no repite recordatorios ni avisos de vencimiento.
        $this->artisan('tasks:check-deadlines')->expectsOutputToContain('Recordatorios enviados: 0');
        Notification::assertSentToTimes($this->member, TaskDueSoon::class, 1);
        Notification::assertSentToTimes($this->member, TaskOverdue::class, 1);
    }

    public function test_changing_the_due_date_allows_a_new_reminder(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to' => $this->member->id,
            'due_date' => now()->addDays(2), // fecha fija: la factory usa una aleatoria
            'due_reminder_sent_at' => now(),
        ]);

        $this->actingAs($this->leader)->put(route('projects.tasks.update', [$this->project, $task]), [
            'title' => $task->title,
            'priority' => 'media',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'assigned_to' => $this->member->id,
            'status' => 'pendiente',
            'progress' => 0,
        ]);

        $this->assertNull($task->fresh()->due_reminder_sent_at);
    }
}
