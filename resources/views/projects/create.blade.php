@extends('layouts.app')

@section('title', 'Nuevo proyecto')

@section('content')
    <h1>Nuevo proyecto</h1>

    <form method="POST" action="{{ route('projects.store') }}" class="card form-card" novalidate>
        @csrf
        @include('projects.partials.form')

        <fieldset class="field">
            <legend>Integrantes iniciales (opcional)</legend>
            <p class="muted small">Tú quedarás como líder del proyecto.</p>
            <div class="checkbox-list">
                @forelse ($students as $student)
                    <label class="checkbox">
                        <input type="checkbox" name="member_ids[]" value="{{ $student->id }}"
                               @checked(in_array($student->id, old('member_ids', [])))>
                        {{ $student->name }} <span class="muted">({{ $student->email }})</span>
                    </label>
                @empty
                    <p class="muted">No hay otros estudiantes registrados.</p>
                @endforelse
            </div>
            @error('member_ids.*') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        <div class="form-actions">
            <a href="{{ route('projects.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Crear proyecto</button>
        </div>
    </form>
@endsection
