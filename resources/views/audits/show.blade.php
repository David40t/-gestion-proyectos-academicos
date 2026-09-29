@extends('layouts.app')

@section('title', 'Registro de auditoría')

@php
    $format = fn ($value) => match (true) {
        $value === null => '—',
        is_bool($value) => $value ? 'Sí' : 'No',
        is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
        default => (string) $value,
    };
    $old = $audit->old_values ?? [];
    $new = $audit->new_values ?? [];
    $fields = array_unique([...array_keys($old), ...array_keys($new)]);
@endphp

@section('content')
    <p class="breadcrumb"><a href="{{ route('audits.index') }}">Auditoría</a> / Registro #{{ $audit->id }}</p>
    <h1>{{ trans('audit.actions')[$audit->action] ?? $audit->action }}</h1>

    <div class="grid-main">
        <section class="card">
            <h2>Cambios registrados</h2>
            @if ($fields === [])
                <p class="muted">Esta acción no registra cambios de datos.</p>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Campo</th><th>Valor anterior</th><th>Valor nuevo</th></tr></thead>
                        <tbody>
                            @foreach ($fields as $field)
                                <tr>
                                    <td><code>{{ $field }}</code></td>
                                    <td class="audit-old">{{ $format($old[$field] ?? null) }}</td>
                                    <td class="audit-new">{{ $format($new[$field] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <aside class="card">
            <h2>Contexto</h2>
            <dl class="details">
                <dt>Fecha y hora</dt><dd>{{ $audit->created_at->format('d/m/Y H:i:s') }}</dd>
                <dt>Usuario</dt><dd>{{ $audit->user ? $audit->user->name.' ('.$audit->user->email.')' : 'Sistema (proceso automático)' }}</dd>
                <dt>Módulo</dt><dd>{{ trans('audit.modules')[$audit->module] ?? $audit->module }}</dd>
                <dt>Entidad</dt><dd>{{ trans('audit.entities')[$audit->auditable_type] ?? ($audit->auditable_type ?? '—') }} #{{ $audit->auditable_id ?? '—' }}</dd>
                <dt>Proyecto</dt><dd>{{ $audit->project?->title ?? '—' }}</dd>
                <dt>Dirección IP</dt><dd>{{ $audit->ip_address ?? '—' }}</dd>
                <dt>Navegador</dt><dd class="small">{{ $audit->user_agent ?? '—' }}</dd>
            </dl>
        </aside>
    </div>
@endsection
