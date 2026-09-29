<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="guest">
    <main class="guest-card">
        <h1 class="brand">Gestión de Proyectos Académicos</h1>
        <x-alert />
        @yield('content')
    </main>
    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
