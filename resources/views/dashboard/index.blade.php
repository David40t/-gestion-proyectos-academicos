@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Bienvenido, {{ $user->name }}</h1>
    <p class="muted">Roles: {{ $roles }}</p>
    {{-- El contenido por rol se implementa en la Fase 10. --}}
@endsection
