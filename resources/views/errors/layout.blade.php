{{--
    Layout de errores: independiente a propósito (sin consultas, sesión ni View Composers),
    para poder mostrarse incluso cuando falla la base de datos o el propio layout principal.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="guest">
    <main class="guest-card error-card">
        <p class="error-code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="muted">@yield('message')</p>
        <div class="error-actions">
            @hasSection('actions')
                @yield('actions')
            @else
                {{-- previousPath(): solo la ruta interna, nunca un Referer externo. --}}
                <a href="{{ url(url()->previousPath() ?: '/') }}" class="btn btn-secondary">Volver</a>
                <a href="{{ url('/') }}" class="btn btn-primary">Ir al inicio</a>
            @endif
        </div>
    </main>
</body>
</html>
