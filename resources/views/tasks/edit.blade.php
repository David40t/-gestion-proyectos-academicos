@extends('layouts.app')

@section('title', 'Editar tarea')

@section('content')
    <p class="breadcrumb">
        <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a> /
        <a href="{{ route('projects.tasks.show', [$project, $task]) }}">{{ $task->title }}</a> / Editar
    </p>
    <h1>Editar tarea</h1>

    <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}" class="card form-card" novalidate>
        @csrf
        @method('PUT')
        @include('tasks.partials.form')
        @include('tasks.partials.state-fields')

        <div class="form-actions">
            <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection
