@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h2>Iniciar sesión</h2>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-form.input name="email" label="Correo electrónico" type="email" required autofocus autocomplete="username" />
        <x-form.input name="password" label="Contraseña" type="password" required autocomplete="current-password" />

        <label class="checkbox">
            <input type="checkbox" name="remember"> Recordarme
        </label>

        <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
    </form>

    <p class="guest-links">
        <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a> ·
        <a href="{{ route('register') }}">Crear cuenta</a>
    </p>
@endsection
