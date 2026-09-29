@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <div>
            <h1>Hola, {{ $user->name }}</h1>
            <p class="muted">{{ $user->roles->pluck('display_name')->join(' · ') }}</p>
        </div>
        @can('create', App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="btn btn-primary">Nuevo proyecto</a>
        @endcan
    </div>

    @include('dashboard.partials.stats')

    @if ($perspective === 'teacher')
        @include('dashboard.partials.projects-table', ['title' => 'Proyectos supervisados', 'items' => $projects, 'empty' => 'Aún no tienes proyectos asignados para supervisar.'])

        <div class="grid-2">
            @include('dashboard.partials.next-tasks', ['title' => 'Próximas entregas', 'showAssignee' => true])
            @include('dashboard.partials.recent-comments')
        </div>
        <div class="grid-2">
            @include('dashboard.partials.recent-activity')
            @include('dashboard.partials.notifications')
        </div>
    @else
        @if ($ledProjects->isNotEmpty())
            @include('dashboard.partials.projects-table', ['title' => 'Proyectos que lideras', 'items' => $ledProjects, 'empty' => ''])
        @endif

        @include('dashboard.partials.my-projects')

        <div class="grid-2">
            @include('dashboard.partials.next-tasks', ['title' => 'Mis próximas tareas', 'showAssignee' => false])
            @include('dashboard.partials.notifications')
        </div>
    @endif
@endsection
