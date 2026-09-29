<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\AuditService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\ProjectMemberService;
use App\Services\RoleService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Pruebas unitarias de las reglas de integrantes: sin base de datos, con repositorios simulados.
 */
class ProjectMemberServiceTest extends TestCase
{
    private MockInterface $members;

    private MockInterface $projects;

    private MockInterface $tasks;

    private MockInterface $roles;

    private MockInterface $audit;

    private MockInterface $notifier;

    private ProjectMemberService $service;

    private Project $project;

    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->members = Mockery::mock(ProjectMemberRepositoryInterface::class);
        $this->projects = Mockery::mock(ProjectRepositoryInterface::class);
        $this->tasks = Mockery::mock(TaskRepositoryInterface::class);
        $this->roles = Mockery::mock(RoleService::class);
        $this->audit = Mockery::mock(AuditService::class);
        $this->notifier = Mockery::mock(NotificationDispatcher::class);

        $this->service = new ProjectMemberService($this->members, $this->projects, $this->tasks, $this->roles, $this->audit, $this->notifier);

        $this->leader = $this->user(1, Role::ESTUDIANTE, Role::LIDER);
        $this->project = (new Project)->forceFill(['id' => 10, 'title' => 'Demo', 'leader_id' => 1]);
        $this->project->setRelation('leader', $this->leader);
    }

    private function user(int $id, string ...$roles): User
    {
        $user = (new User)->forceFill(['id' => $id, 'name' => "Usuario {$id}"]);
        $user->setRelation('roles', collect($roles)->map(fn ($name) => (new Role)->forceFill(['name' => $name])));

        return $user;
    }

    public function test_only_students_can_be_added(): void
    {
        $this->members->shouldNotReceive('add');

        $this->expectException(BusinessRuleException::class);
        $this->service->add($this->project, $this->user(2, Role::DOCENTE), $this->leader);
    }

    public function test_a_member_cannot_be_added_twice(): void
    {
        $student = $this->user(2, Role::ESTUDIANTE);
        $this->members->shouldReceive('isMember')->with($this->project, $student)->andReturnTrue();
        $this->members->shouldNotReceive('add');

        $this->expectException(BusinessRuleException::class);
        $this->service->add($this->project, $student, $this->leader);
    }

    public function test_adding_a_member_persists_audits_and_notifies(): void
    {
        $student = $this->user(2, Role::ESTUDIANTE);
        $this->members->shouldReceive('isMember')->andReturnFalse();
        $this->members->shouldReceive('add')->once()->with($this->project, $student);
        $this->audit->shouldReceive('record')->once()->withArgs(fn ($action) => $action === 'member.added');
        $this->notifier->shouldReceive('send')->once()->withArgs(fn ($to, $notification, $actor) => $to === $student && $actor === $this->leader);

        $this->service->add($this->project, $student, $this->leader);
    }

    public function test_the_leader_cannot_be_removed(): void
    {
        $this->members->shouldNotReceive('remove');
        $this->tasks->shouldNotReceive('unassignOpenTasks');

        $this->expectException(BusinessRuleException::class);
        $this->service->remove($this->project, $this->leader, $this->leader);
    }

    public function test_removing_a_member_unassigns_tasks_before_removal(): void
    {
        $student = $this->user(2, Role::ESTUDIANTE);
        $this->members->shouldReceive('isMember')->andReturnTrue();
        $this->tasks->shouldReceive('unassignOpenTasks')->once()->ordered()->andReturn(3);
        $this->members->shouldReceive('remove')->once()->ordered();
        $this->audit->shouldReceive('record')->once()->withArgs(fn ($action, $module, $model, $old, $new) => $action === 'member.removed' && $new === ['unassigned_tasks' => 3]);
        $this->notifier->shouldReceive('send')->once();

        $this->service->remove($this->project, $student, $this->leader);
    }

    public function test_leadership_can_only_go_to_a_member(): void
    {
        $outsider = $this->user(3, Role::ESTUDIANTE);
        $this->members->shouldReceive('isMember')->andReturnFalse();
        $this->projects->shouldNotReceive('update');

        $this->expectException(BusinessRuleException::class);
        $this->service->changeLeader($this->project, $outsider, $this->leader);
    }

    public function test_leader_role_follows_led_projects(): void
    {
        $user = $this->user(2, Role::ESTUDIANTE);

        $this->projects->shouldReceive('countLedBy')->once()->andReturn(1);
        $this->roles->shouldReceive('assign')->once()->with($user, Role::LIDER, $this->leader);
        $this->service->syncLeaderRole($user, $this->leader);

        $this->projects->shouldReceive('countLedBy')->once()->andReturn(0);
        $this->roles->shouldReceive('revoke')->once()->with($user, Role::LIDER, $this->leader);
        $this->service->syncLeaderRole($user, $this->leader);
    }
}
