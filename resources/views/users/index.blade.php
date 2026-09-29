@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <div class="page-header">
        <div>
            <h1>Usuarios</h1>
            <p class="muted">Asignación de roles. El rol Líder se asigna automáticamente al liderar un proyecto.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="card filters">
        <x-form.input name="q" label="Buscar por nombre o correo" :value="$search" maxlength="100" />
        <div class="filters-actions">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
    </form>

    <div class="card">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Correo</th><th>Roles</th><th>Registro</th><th></th></tr></thead>
                <tbody>
                    @forelse ($users as $listed)
                        <tr>
                            <td>{{ $listed->name }}</td>
                            <td>{{ $listed->email }}</td>
                            <td>
                                @foreach ($listed->roles as $role)
                                    <span class="badge badge-role-{{ strtolower($role->name) }}">{{ $role->display_name }}</span>
                                @endforeach
                            </td>
                            <td>{{ $listed->created_at->format('d/m/Y') }}</td>
                            <td class="cell-actions">
                                @can('updateRoles', $listed)
                                    <a href="{{ route('users.edit', $listed) }}">Roles</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No se encontraron usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
