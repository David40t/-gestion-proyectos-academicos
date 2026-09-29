<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\AuditService;
use App\Services\CommentService;
use App\Services\Notifications\NotificationDispatcher;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CommentServiceTest extends TestCase
{
    private MockInterface $comments;

    private MockInterface $tasks;

    private CommentService $service;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comments = Mockery::mock(CommentRepositoryInterface::class);
        $this->tasks = Mockery::mock(TaskRepositoryInterface::class);
        $this->service = new CommentService($this->comments, $this->tasks, Mockery::mock(AuditService::class), Mockery::mock(NotificationDispatcher::class));
        $this->project = (new Project)->forceFill(['id' => 5, 'title' => 'Demo', 'leader_id' => 1, 'teacher_id' => 9]);
    }

    public function test_only_the_project_teacher_can_register_observations(): void
    {
        $student = (new User)->forceFill(['id' => 2]);
        $this->comments->shouldNotReceive('create');

        $this->expectException(BusinessRuleException::class);
        $this->service->create($this->project, ['body' => 'Hola', 'is_observation' => true], $student);
    }

    public function test_a_task_from_another_project_is_rejected(): void
    {
        $student = (new User)->forceFill(['id' => 2]);
        $this->tasks->shouldReceive('findInProject')->with($this->project, 77)->andReturnNull();
        $this->comments->shouldNotReceive('create');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('La tarea indicada no pertenece a este proyecto.');
        $this->service->create($this->project, ['body' => 'Hola', 'task_id' => '77'], $student);
    }

    public function test_updating_with_the_same_text_does_nothing(): void
    {
        $comment = (new \App\Models\Comment)->forceFill(['id' => 1, 'body' => 'Igual']);
        $this->comments->shouldNotReceive('update');

        $this->assertSame($comment, $this->service->update($comment, 'Igual', (new User)->forceFill(['id' => 2])));
    }
}
