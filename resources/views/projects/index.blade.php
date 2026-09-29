@extends('layouts.app')

@section('title', 'Proyectos')

@section('content')
    <div class="page-header">
        <h1>Proyectos</h1>
        @can('create', App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="btn btn-primary">Nuevo proyecto</a>
        @endcan
    </div>

    <div class="card">
        @if ($projects->isEmpty())
            <p class="muted">Aún no participas en ningún proyecto.</p>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Estado</th>
                            <th>Líder</th>
                            <th>Docente</th>
                            <th>Integrantes</th>
                            <th>Avance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a></td>
                                <td><x-status-badge :status="$project->status" /></td>
                                <td>{{ $project->leader->name }}</td>
                                <td>{{ $project->teacher?->name ?? '—' }}</td>
                                <td>{{ $project->members_count }}</td>
                                <td class="cell-progress"><x-progress-bar :value="$project->tasks_avg_progress" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $projects->links() }}
        @endif
    </div>
@endsection
