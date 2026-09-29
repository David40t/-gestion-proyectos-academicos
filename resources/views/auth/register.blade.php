@extends('layouts.guest')

@section('title', 'Registro')

@section('content')
    <h2>Crear cuenta de estudiante</h2>

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <x-form.input name="name" label="Nombre completo" required autofocus autocomplete="name" />
        <x-form.input name="email" label="Correo electrónico" type="email" required autocomplete="username" />
        <x-form.input name="password" label="Contraseña" type="password" required autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="Confirmar contraseña" type="password" required autocomplete="new-password" />

        <button type="submit" class="btn btn-primary btn-block">Registrarme</button>
    </form>

    <p class="guest-links"><a href="{{ route('login') }}">¿Ya tienes cuenta? Inicia sesión</a></p>
@endsection
