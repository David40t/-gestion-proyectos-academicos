@extends('layouts.app')

@section('title', $task->title)

@section('content')
    <p class="breadcrumb"><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a> / Tarea</p>

    <div class="page-header">
        <div>
            <h1>{{ $task->title }}</h1>
            <x-status-badge :status="$task->status" />
            <span class="badge badge-priority-{{ $task->priority->value }}">Prioridad {{ $task->priority->label() }}</span>
        </div>
        @can('update', $task)
            <div class="actions">
                <a href="{{ route('projects.tasks.edit', [$project, $task]) }}" class="btn btn-secondary">Editar</a>
            </div>
        @endcan
    </div>

    <div class="grid-main">
        <section class="card">
            <h2>Detalle</h2>
            <dl class="details">
                <dt>Descripción</dt>
                <dd>{{ $task->description ?: '—' }}</dd>
                <dt>Responsable</dt>
                <dd>{{ $task->assignee?->name ?? 'Sin asignar' }}</dd>
                <dt>Fechas</dt>
                <dd>{{ $task->start_date?->format('d/m/Y') ?? '—' }} — <strong>{{ $task->due_date->format('d/m/Y') }}</strong></dd>
                <dt>Avance</dt>
                <dd><x-progress-bar :value="$task->progress" /></dd>
                @if ($task->completed_at)
                    <dt>Completada</dt>
                    <dd>{{ $task->completed_at->format('d/m/Y H:i') }}</dd>
                @endif
                <dt>Creada por</dt>
                <dd>{{ $task->creator->name }} · {{ $task->created_at->format('d/m/Y') }}</dd>
            </dl>
        </section>

        <aside class="card">
            <h2>Registrar avance</h2>
            @can('updateProgress', $task)
                <form method="POST" action="{{ route('projects.tasks.progress.update', [$project, $task]) }}" novalidate>
                    @csrf
                    @method('PATCH')
                    @include('tasks.partials.state-fields')
                    <button type="submit" class="btn btn-primary btn-block">Guardar avance</button>
                </form>
            @else
                <p class="muted">Solo el responsable de la tarea o el líder del proyecto pueden registrar avance.</p>
            @endcan
        </aside>
    </div>

    {{-- Comentarios de la tarea: Fase 7. --}}
@endsection
