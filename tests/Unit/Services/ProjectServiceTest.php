<?php

namespace Tests\Unit\Services;

use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuditService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\ProjectMemberService;
use App\Services\ProjectService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    private MockInterface $projects;

    private ProjectService $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projects = Mockery::mock(ProjectRepositoryInterface::class);
        $this->service = new ProjectService(
            $this->projects,
            Mockery::mock(ProjectMemberRepositoryInterface::class),
            Mockery::mock(UserRepositoryInterface::class),
            Mockery::mock(ProjectMemberService::class),
            Mockery::mock(AuditService::class),
            Mockery::mock(NotificationDispatcher::class),
        );
        $this->actor = (new User)->forceFill(['id' => 1, 'name' => 'Líder']);
    }

    private function project(ProjectStatus $status): Project
    {
        return (new Project)->forceFill(['id' => 5, 'title' => 'Demo', 'status' => $status, 'leader_id' => 1]);
    }

    public function test_invalid_status_transitions_are_rejected_without_persisting(): void
    {
        $this->projects->shouldNotReceive('update');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('No se puede pasar de "Planeación" a "Finalizado".');

        $this->service->changeStatus($this->project(ProjectStatus::Planeacion), ProjectStatus::Finalizado, $this->actor);
    }

    public function test_only_projects_in_planning_can_be_deleted(): void
    {
        $this->projects->shouldNotReceive('delete');

        $this->expectException(BusinessRuleException::class);
        $this->service->delete($this->project(ProjectStatus::EnProgreso), $this->actor);
    }

    public function test_projects_with_tasks_cannot_be_deleted(): void
    {
        $project = $this->project(ProjectStatus::Planeacion);
        $this->projects->shouldReceive('hasTasks')->with($project)->andReturnTrue();
        $this->projects->shouldNotReceive('delete');

        $this->expectException(BusinessRuleException::class);
        $this->service->delete($project, $this->actor);
    }
}
