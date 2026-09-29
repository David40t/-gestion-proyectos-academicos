@extends('layouts.app')

@section('title', 'Roles de '.$user->name)

@php($roleLabels = [
    App\Models\Role::ESTUDIANTE => ['Estudiante', 'Participa en proyectos; puede crear proyectos y liderarlos.'],
    App\Models\Role::DOCENTE => ['Docente', 'Supervisa proyectos, registra observaciones y consulta la auditoría de sus proyectos.'],
    App\Models\Role::ADMINISTRADOR => ['Administrador', 'Acceso global a todos los proyectos, auditoría completa y gestión de roles.'],
])

@section('content')
    <p class="breadcrumb"><a href="{{ route('users.index') }}">Usuarios</a> / {{ $user->name }}</p>
    <h1>Roles de {{ $user->name }}</h1>
    <p class="muted">{{ $user->email }}</p>

    <form method="POST" action="{{ route('users.update', $user) }}" class="card form-card">
        @csrf
        @method('PUT')

        <fieldset class="field">
            <legend>Roles asignados</legend>
            @foreach ($assignableRoles as $role)
                <label class="checkbox role-option">
                    <input type="checkbox" name="roles[]" value="{{ $role }}"
                           @checked(in_array($role, old('roles', $user->roles->pluck('name')->all()), true))>
                    <span><strong>{{ $roleLabels[$role][0] }}</strong><br><span class="muted small">{{ $roleLabels[$role][1] }}</span></span>
                </label>
            @endforeach
            @error('roles') <p class="field-error">{{ $message }}</p> @enderror
            @error('roles.*') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        @if ($user->hasRole(App\Models\Role::LIDER))
            <p class="muted small">Además tiene el rol <strong>Líder</strong>, asignado automáticamente porque lidera al menos un proyecto.</p>
        @endif

        <div class="form-actions">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar roles</button>
        </div>
    </form>
@endsection
