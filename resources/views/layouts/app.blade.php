<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="navbar">
        <a href="{{ route('dashboard') }}" class="navbar-brand">Proyectos Académicos</a>
        <div class="navbar-user">
            @can('notificacion.ver')
                <a href="{{ route('notifications.index') }}" class="navbar-notifications" title="Notificaciones">
                    Notificaciones
                    @if ($unreadNotifications > 0)
                        <span class="notification-count">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </a>
            @endcan
            <span>{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-link">Cerrar sesión</button>
            </form>
        </div>
    </header>

    <div class="layout">
        <nav class="sidebar">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
            @can('viewAny', App\Models\Project::class)
                <a href="{{ route('projects.index') }}" @class(['active' => request()->routeIs('projects.*')])>Proyectos</a>
            @endcan
            @can('tarea.cambiar_estado')
                {{-- Solo quienes pueden ser responsables de tareas (estudiantes). --}}
                <a href="{{ route('tasks.mine') }}" @class(['active' => request()->routeIs('tasks.mine')])>Mis tareas</a>
            @endcan
            @can('notificacion.ver')
                <a href="{{ route('notifications.index') }}" @class(['active' => request()->routeIs('notifications.*')])>
                    Notificaciones @if ($unreadNotifications > 0)<span class="notification-count">{{ $unreadNotifications }}</span>@endif
                </a>
            @endcan
            @can('viewAny', App\Models\Audit::class)
                <a href="{{ route('audits.index') }}" @class(['active' => request()->routeIs('audits.*')])>Auditoría</a>
            @endcan
            @can('viewAny', App\Models\User::class)
                <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>Usuarios</a>
            @endcan
            {{-- Los enlaces de cada módulo se agregan en sus fases, protegidos con @can. --}}
        </nav>

        <main class="content">
            <x-alert />
            @yield('content')
        </main>
    </div>
    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
