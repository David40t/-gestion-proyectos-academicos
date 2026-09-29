@extends('layouts.app')

@section('title', 'Mis tareas')

@section('content')
    <h1>Mis tareas</h1>

    <div class="card">
        @if ($tasks->isEmpty())
            <p class="muted">No tienes tareas asignadas.</p>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr><th>Tarea</th><th>Proyecto</th><th>Prioridad</th><th>Fecha límite</th><th>Estado</th><th>Avance</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr>
                                <td><a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}">{{ $task->title }}</a></td>
                                <td>{{ $task->project->title }}</td>
                                <td>{{ $task->priority->label() }}</td>
                                <td>{{ $task->due_date->format('d/m/Y') }}</td>
                                <td><x-status-badge :status="$task->status" /></td>
                                <td class="cell-progress"><x-progress-bar :value="$task->progress" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $tasks->links() }}
        @endif
    </div>
@endsection
