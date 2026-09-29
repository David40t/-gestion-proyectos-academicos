{{-- Mensajes flash de éxito / error y resumen de errores de validación. --}}
@if (session('success') || session('status'))
    <div class="alert alert-success" role="status">{{ session('success') ?? session('status') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-error" role="alert">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-error" role="alert">Revisa los campos marcados.</div>
@endif
