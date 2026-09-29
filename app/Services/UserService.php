<?php

namespace App\Services;

use App\Models\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleService $roles,
        private readonly AuditService $audit,
        private readonly ProjectRepositoryInterface $projects,
        private readonly ProjectMemberRepositoryInterface $members,
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

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateWithRoles(?string $search): LengthAwarePaginator
    {
        return $this->users->paginateWithRoles($search);
    }

    /**
     * Ajusta los roles asignables de un usuario (gestión de roles del administrador).
     * El rol LIDER no se toca: se deriva de liderar proyectos (ADR-006).
     *
     * @param  list<string>  $roleNames  Roles asignables que el usuario debe tener.
     */
    public function syncRoles(User $user, array $roleNames, User $actor): void
    {
        $wanted = array_values(array_intersect(Role::ASSIGNABLE, $roleNames));

        if ($wanted === []) {
            throw new BusinessRuleException('El usuario debe conservar al menos un rol.');
        }

        $toAdd = array_diff($wanted, $this->assignableRolesOf($user));
        $toRemove = array_diff($this->assignableRolesOf($user), $wanted);

        foreach ($toRemove as $role) {
            $this->ensureCanRemove($user, $role, $actor);
        }

        DB::transaction(function () use ($user, $toAdd, $toRemove, $actor) {
            foreach ($toAdd as $role) {
                $this->roles->assign($user, $role, $actor);
            }
            foreach ($toRemove as $role) {
                $this->roles->revoke($user, $role, $actor);
            }
        });
    }

    /**
     * Designa un administrador desde la consola (primer administrador en producción).
     */
    public function grantAdministrator(string $email): User
    {
        $user = $this->users->findByEmail($email)
            ?? throw new BusinessRuleException("No existe un usuario con el correo {$email}.");

        $this->roles->assign($user, Role::ADMINISTRADOR);

        return $user;
    }

    /**
     * @return array<string, int>
     */
    public function countByRole(): array
    {
        return $this->users->countByRole();
    }

    /**
     * @return list<string>
     */
    private function assignableRolesOf(User $user): array
    {
        return array_values(array_intersect(Role::ASSIGNABLE, $user->roles->pluck('name')->all()));
    }

    /**
     * Salvaguardas: no dejar el sistema sin administración ni romper datos que dependen del rol.
     */
    private function ensureCanRemove(User $user, string $role, User $actor): void
    {
        $reason = match (true) {
            $role === Role::ADMINISTRADOR && $user->is($actor) => 'No puedes quitarte tu propio rol de administrador.',
            $role === Role::ADMINISTRADOR && ($this->users->countByRole()[Role::ADMINISTRADOR] ?? 0) <= 1 => 'Debe existir al menos un administrador.',
            $role === Role::ESTUDIANTE && $this->members->countProjectsOf($user) > 0 => "{$user->name} participa en proyectos: primero debe ser retirado de ellos.",
            $role === Role::DOCENTE && $this->projects->idsSupervisedBy($user) !== [] => "{$user->name} supervisa proyectos: primero asigna otro docente responsable.",
            default => null,
        };

        if ($reason) {
            throw new BusinessRuleException($reason);
        }
    }
}
