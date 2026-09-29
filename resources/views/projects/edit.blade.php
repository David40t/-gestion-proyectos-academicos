@extends('layouts.app')

@section('title', 'Editar proyecto')

@section('content')
    <h1>Editar proyecto</h1>

    <form method="POST" action="{{ route('projects.update', $project) }}" class="card form-card" novalidate>
        @csrf
        @method('PUT')
        @include('projects.partials.form')

        <div class="form-actions">
            <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection
