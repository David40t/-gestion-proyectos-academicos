<?php

namespace Tests\Feature\Comments;

use App\Enums\ProjectStatus;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectScenario(ProjectStatus::EnProgreso);
    }

    private function storeUrl(): string
    {
        return route('projects.comments.store', $this->project);
    }

    public function test_a_member_comments_on_the_project(): void
    {
        $this->actingAs($this->member)
            ->post($this->storeUrl(), ['body' => 'Avancé con el modelo ER'])
            ->assertSessionHas('success');

        $comment = Comment::firstOrFail();
        $this->assertSame($this->member->id, $comment->user_id);
        $this->assertNull($comment->task_id);
        $this->assertFalse($comment->is_observation);
        $this->assertDatabaseHas('audits', ['action' => 'comment.created', 'auditable_id' => $comment->id, 'user_id' => $this->member->id]);

        $this->actingAs($this->teacher)->get(route('projects.show', $this->project))
            ->assertSee('Avancé con el modelo ER')->assertSee($this->member->name);
    }

    public function test_the_teacher_registers_an_observation(): void
    {
        $this->actingAs($this->teacher)
            ->post($this->storeUrl(), ['body' => 'Falta justificar la arquitectura', 'is_observation' => '1'])
            ->assertSessionHas('success', 'Observación registrada.');

        $this->assertTrue(Comment::firstOrFail()->is_observation);
        $this->actingAs($this->member)->get(route('projects.show', $this->project))->assertSee('Observación docente');
    }

    public function test_students_cannot_register_observations(): void
    {
        $this->actingAs($this->leader)
            ->post($this->storeUrl(), ['body' => 'Intento', 'is_observation' => '1'])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_a_teacher_of_another_project_cannot_comment(): void
    {
        $otherTeacher = $this->userWithRoles(\App\Models\Role::DOCENTE);

        $this->actingAs($otherTeacher)->post($this->storeUrl(), ['body' => 'Hola'])->assertForbidden();
        $this->actingAs($this->outsider)->post($this->storeUrl(), ['body' => 'Hola'])->assertForbidden();
    }

    public function test_task_comments_are_linked_to_the_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->member)
            ->post($this->storeUrl(), ['body' => 'Comentario de tarea', 'task_id' => $task->id])
            ->assertSessionHas('success');

        $this->assertSame($task->id, Comment::firstOrFail()->task_id);

        $this->actingAs($this->leader)->get(route('projects.tasks.show', [$this->project, $task]))->assertSee('Comentario de tarea');
        $this->actingAs($this->leader)->get(route('projects.show', $this->project))->assertDontSee('Comentario de tarea');
    }

    public function test_a_task_from_another_project_is_rejected(): void
    {
        $foreignTask = Task::factory()->create(['project_id' => Project::factory()->create()->id]);

        $this->actingAs($this->member)
            ->post($this->storeUrl(), ['body' => 'Intento', 'task_id' => $foreignTask->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_validation(): void
    {
        $this->actingAs($this->member)->post($this->storeUrl(), ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($this->member)->post($this->storeUrl(), ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
    }

    public function test_comment_content_is_escaped(): void
    {
        Comment::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'body' => '<script>alert("xss")</script>',
        ]);

        $this->actingAs($this->leader)->get(route('projects.show', $this->project))
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_only_the_author_edits_a_comment_and_the_change_is_audited(): void
    {
        $comment = Comment::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->member->id, 'body' => 'Original']);
        $url = route('projects.comments.update', [$this->project, $comment]);

        $this->actingAs($this->leader)->put($url, ['body' => 'Hackeado'])->assertForbidden();
        $this->actingAs($this->teacher)->put($url, ['body' => 'Hackeado'])->assertForbidden();

        $this->actingAs($this->member)->put($url, ['body' => 'Corregido'])->assertSessionHas('success');

        $this->assertSame('Corregido', $comment->fresh()->body);
        $audit = \App\Models\Audit::where('action', 'comment.updated')->firstOrFail();
        $this->assertSame(['body' => 'Original'], $audit->old_values);
        $this->assertSame(['body' => 'Corregido'], $audit->new_values);
    }

    public function test_only_the_author_soft_deletes_a_comment(): void
    {
        $comment = Comment::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->member->id]);
        $url = route('projects.comments.destroy', [$this->project, $comment]);

        $this->actingAs($this->leader)->delete($url)->assertForbidden();
        $this->actingAs($this->member)->delete($url)->assertSessionHas('success');

        $this->assertSoftDeleted($comment);
        $this->assertDatabaseHas('audits', ['action' => 'comment.deleted', 'auditable_id' => $comment->id]);
        $this->actingAs($this->leader)->get(route('projects.show', $this->project))->assertDontSee($comment->body);
    }

    public function test_comments_are_scoped_to_their_project(): void
    {
        $foreign = Comment::factory()->create(['user_id' => $this->member->id]);

        $this->actingAs($this->member)
            ->put(route('projects.comments.update', [$this->project, $foreign]), ['body' => 'x'])
            ->assertNotFound();
    }

    public function test_comment_controls_are_shown_only_when_allowed(): void
    {
        Comment::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->member->id]);

        $this->actingAs($this->teacher)->get(route('projects.show', $this->project))
            ->assertSee('Registrar como observación docente')->assertDontSee('>Editar</summary>', false);

        $this->actingAs($this->member)->get(route('projects.show', $this->project))
            ->assertDontSee('Registrar como observación docente')->assertSee('>Editar</summary>', false);
    }
}
