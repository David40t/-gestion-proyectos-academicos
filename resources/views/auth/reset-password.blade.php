@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
    <h2>Restablecer contraseña</h2>

    <form method="POST" action="{{ route('password.update') }}" novalidate data-validate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form.input name="email" label="Correo electrónico" type="email" :value="$request->email" required />
        <x-form.input name="password" label="Nueva contraseña" type="password" required autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="Confirmar contraseña" type="password" required autocomplete="new-password" />

        <button type="submit" class="btn btn-primary btn-block">Restablecer</button>
    </form>
@endsection
