<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateUserRolesRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Módulo de usuarios: listado y asignación de roles (administrador).
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('q')->trim()->limit(100, '')->toString() ?: null;

        return view('users.index', [
            'users' => $this->users->paginateWithRoles($search),
            'search' => $search,
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('updateRoles', $user);

        return view('users.edit', [
            'user' => $user->load('roles:id,name,display_name'),
            'assignableRoles' => Role::ASSIGNABLE,
        ]);
    }

    public function update(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $this->authorize('updateRoles', $user);

        $this->users->syncRoles($user, $request->validated('roles'), $request->user());

        return redirect()->route('users.index')->with('success', "Roles de {$user->name} actualizados.");
    }
}
