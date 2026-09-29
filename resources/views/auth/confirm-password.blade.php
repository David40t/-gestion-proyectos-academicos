@extends('layouts.guest')

@section('title', 'Confirmar contraseña')

@section('content')
    <h2>Confirmar contraseña</h2>
    <p class="muted">Por seguridad, confirma tu contraseña para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}" novalidate>
        @csrf
        <x-form.input name="password" label="Contraseña" type="password" required autofocus autocomplete="current-password" />

        <button type="submit" class="btn btn-primary btn-block">Confirmar</button>
    </form>
@endsection
