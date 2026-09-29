<?php

namespace App\Providers;

use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AuditRepository;
use App\Repositories\Eloquent\ProjectMemberRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaza cada interfaz de repositorio con su implementación Eloquent (ADR-002).
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AuditRepositoryInterface::class => AuditRepository::class,
        ProjectMemberRepositoryInterface::class => ProjectMemberRepository::class,
        ProjectRepositoryInterface::class => ProjectRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        TaskRepositoryInterface::class => TaskRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
    ];
}
