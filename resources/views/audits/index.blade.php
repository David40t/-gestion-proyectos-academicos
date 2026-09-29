@extends('layouts.app')

@section('title', 'Auditoría')

@section('content')
    <div class="page-header">
        <div>
            <h1>Auditoría</h1>
            <p class="muted">Registro de solo lectura de las acciones realizadas en el sistema.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('audits.index') }}" class="card filters">
        <x-form.select name="project_id" label="Proyecto" :value="$filters['project_id'] ?? null"
                       :options="$projects->pluck('title', 'id')" placeholder="Todos" />
        <x-form.select name="module" label="Módulo" :value="$filters['module'] ?? null"
                       :options="trans('audit.modules')" placeholder="Todos" />
        <x-form.select name="action" label="Acción" :value="$filters['action'] ?? null"
                       :options="trans('audit.actions')" placeholder="Todas" />
        <x-form.input name="user" label="Usuario (nombre o correo)" :value="$filters['user'] ?? null" />
        <x-form.input name="from" label="Desde" type="date" :value="$filters['from'] ?? null" />
        <x-form.input name="to" label="Hasta" type="date" :value="$filters['to'] ?? null" />
        <div class="filters-actions">
            <a href="{{ route('audits.index') }}" class="btn btn-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    <div class="card">
        <p class="muted small">{{ $audits->total() }} registros</p>

        @if ($audits->isEmpty())
            <p class="muted">No hay registros para los filtros seleccionados.</p>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Proyecto</th><th>IP</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($audits as $audit)
                            <tr>
                                <td>{{ $audit->created_at->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $audit->user?->name ?? 'Sistema' }}</td>
                                <td>{{ trans('audit.actions')[$audit->action] ?? $audit->action }}</td>
                                <td>{{ trans('audit.modules')[$audit->module] ?? $audit->module }}</td>
                                <td>{{ $audit->project?->title ?? '—' }}</td>
                                <td>{{ $audit->ip_address ?? '—' }}</td>
                                <td class="cell-actions"><a href="{{ route('audits.show', $audit) }}">Detalle</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $audits->links() }}
        @endif
    </div>
@endsection
