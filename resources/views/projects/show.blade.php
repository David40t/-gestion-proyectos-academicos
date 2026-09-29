@extends('layouts.app')

@section('title', $project->title)

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $project->title }}</h1>
            <x-status-badge :status="$project->status" />
        </div>
        <div class="actions">
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary">Editar</a>
            @endcan
            @can('delete', $project)
                <form method="POST" action="{{ route('projects.destroy', $project) }}" data-confirm="¿Eliminar este proyecto?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid-main">
        <section class="card">
            <h2>Información general</h2>
            <dl class="details">
                <dt>Descripción</dt>
                <dd>{{ $project->description }}</dd>
                <dt>Objetivos</dt>
                <dd>{{ $project->objectives ?: '—' }}</dd>
                <dt>Fechas</dt>
                <dd>{{ $project->start_date->format('d/m/Y') }} — {{ $project->end_date?->format('d/m/Y') ?? 'sin fecha de cierre' }}</dd>
                <dt>Líder</dt>
                <dd>{{ $project->leader->name }}</dd>
                <dt>Docente responsable</dt>
                <dd>{{ $project->teacher?->name ?? 'Sin asignar' }}</dd>
                <dt>Avance general</dt>
                <dd><x-progress-bar :value="$summary['progress']" /></dd>
            </dl>
        </section>

        <aside class="card">
            <h2>Estado</h2>
            <p>Actual: <x-status-badge :status="$project->status" /></p>

            @if ($nextStatuses->isNotEmpty())
                <form method="POST" action="{{ route('projects.status.update', $project) }}">
                    @csrf
                    @method('PATCH')
                    <x-form.select name="status" label="Cambiar a"
                                   :options="$nextStatuses->mapWithKeys(fn ($status) => [$status->value => $status->label()])" required />
                    <button type="submit" class="btn btn-primary btn-block">Actualizar estado</button>
                </form>
            @elseif ($project->status->isFinal())
                <p class="muted">El proyecto está cerrado.</p>
            @else
                <p class="muted">No tienes cambios de estado disponibles.</p>
            @endif
        </aside>
    </div>

    <section class="card">
        <h2>Integrantes ({{ $project->members->count() }})</h2>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>Nombre</th><th>Correo</th><th>Rol en el proyecto</th><th>Desde</th>@if ($canManageMembers)<th></th>@endif</tr>
                </thead>
                <tbody>
                    @foreach ($project->members as $member)
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>
                                @if ($project->isLedBy($member))
                                    <span class="badge badge-leader">Líder</span>
                                @else
                                    Integrante
                                @endif
                            </td>
                            <td>{{ $member->pivot->created_at->format('d/m/Y') }}</td>
                            @if ($canManageMembers)
                                <td class="cell-actions">
                                    @unless ($project->isLedBy($member))
                                        <form method="POST" action="{{ route('projects.members.destroy', [$project, $member]) }}"
                                              data-confirm="¿Retirar a {{ $member->name }}? Sus tareas pendientes quedarán sin responsable.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link text-danger">Retirar</button>
                                        </form>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($canManageMembers)
            <div class="grid-2 member-forms">
                <form method="POST" action="{{ route('projects.members.store', $project) }}">
                    @csrf
                    <x-form.select name="user_id" label="Agregar integrante"
                                   :options="$availableStudents->pluck('name', 'id')"
                                   placeholder="{{ $availableStudents->isEmpty() ? 'No hay estudiantes disponibles' : 'Selecciona un estudiante' }}" />
                    <button type="submit" class="btn btn-primary" @disabled($availableStudents->isEmpty())>Agregar</button>
                </form>

                <form method="POST" action="{{ route('projects.leader.update', $project) }}"
                      data-confirm="¿Transferir el liderazgo? Perderás los permisos de gestión sobre este proyecto.">
                    @csrf
                    @method('PATCH')
                    <x-form.select name="leader_id" label="Transferir liderazgo"
                                   :options="$project->members->reject(fn ($member) => $project->isLedBy($member))->pluck('name', 'id')" />
                    <button type="submit" class="btn btn-secondary">Cambiar líder</button>
                </form>
            </div>
        @endif
    </section>

    @include('projects.partials.tracking')

    @can('viewAny', [App\Models\Comment::class, $project])
        @include('comments.section', ['comments' => $comments])
    @endcan
@endsection
