<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleService $roles,
        private readonly AuditService $audit,
    ) {}

    /**
     * El registro público siempre crea estudiantes; nadie puede autoasignarse otro rol.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function registerStudent(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->users->create($data);

            $this->roles->assign($user, Role::ESTUDIANTE, $user);
            $this->audit->record('auth.registered', 'auth', $user, [], [
                'name' => $user->name,
                'email' => $user->email,
            ], $user);

            return $user;
        });
    }

    public function find(int $id): User
    {
        return $this->users->findOrFail($id);
    }

    /**
     * @return Collection<int, User>
     */
    public function teachers(): Collection
    {
        return $this->users->withRole(Role::DOCENTE);
    }

    /**
     * Estudiantes que se pueden agregar a un proyecto (o a uno nuevo si $project es null).
     *
     * @return Collection<int, User>
     */
    public function availableStudents(?Project $project = null): Collection
    {
        return $project
            ? $this->users->withRoleNotInProject(Role::ESTUDIANTE, $project)
            : $this->users->withRole(Role::ESTUDIANTE);
    }
}
