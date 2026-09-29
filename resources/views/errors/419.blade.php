@extends('errors.layout')

@section('code', '419')
@section('title', 'La sesión expiró')
@section('message', 'Por seguridad, el formulario caducó. Vuelve a la página anterior, recárgala e inténtalo de nuevo.')

@section('actions')
    <a href="{{ url(url()->previousPath() ?: '/') }}" class="btn btn-primary">Volver y recargar</a>
@endsection
