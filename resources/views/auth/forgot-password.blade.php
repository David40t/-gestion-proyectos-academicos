@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <h2>Recuperar contraseña</h2>
    <p class="muted">Te enviaremos un enlace para restablecer tu contraseña.</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate data-validate>
        @csrf
        <x-form.input name="email" label="Correo electrónico" type="email" required autofocus />

        <button type="submit" class="btn btn-primary btn-block">Enviar enlace</button>
    </form>

    <p class="guest-links"><a href="{{ route('login') }}">Volver al inicio de sesión</a></p>
@endsection
