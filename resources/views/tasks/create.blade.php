@extends('layouts.app')

@section('title', 'Nueva tarea')

@section('content')
    <p class="breadcrumb"><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a> / Nueva tarea</p>
    <h1>Nueva tarea</h1>

    <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="card form-card" novalidate data-validate>
        @csrf
        @include('tasks.partials.form')

        <div class="form-actions">
            <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Crear tarea</button>
        </div>
    </form>
@endsection
