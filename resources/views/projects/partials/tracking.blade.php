{{-- Seguimiento: métricas calculadas por ProgressService + listado de tareas. --}}
<section class="card" id="tareas">
    <div class="page-header">
        <h2>Seguimiento y tareas</h2>
        @if ($canCreateTasks)
            <a href="{{ route('projects.tasks.create', $project) }}" class="btn btn-primary">Nueva tarea</a>
        @endif
    </div>

    <div class="stats">
        <div class="stat"><span class="stat-value">{{ $summary['progress'] }}%</span><span class="stat-label">Avance general</span></div>
        <div class="stat"><span class="stat-value">{{ $summary['total'] }}</span><span class="stat-label">Tareas</span></div>
        @foreach (App\Enums\TaskStatus::cases() as $status)
            <div class="stat stat-{{ $status->value }}">
                <span class="stat-value">{{ $summary['counts'][$status->value] }}</span>
                <span class="stat-label">{{ $status->label() }}</span>
            </div>
        @endforeach
    </div>

    @if ($summary['upcoming']->isNotEmpty())
        <h3>Próximas fechas límite ({{ App\Services\ProgressService::UPCOMING_DAYS }} días)</h3>
        <ul class="upcoming">
            @foreach ($summary['upcoming'] as $task)
                <li>
                    <strong>{{ $task->due_date->format('d/m') }}</strong> ·
                    <a href="{{ route('projects.tasks.show', [$project, $task]) }}">{{ $task->title }}</a>
                    <span class="muted">({{ $task->assignee?->name ?? 'sin responsable' }})</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($tasks->isEmpty())
        <p class="muted">El proyecto aún no tiene tareas.</p>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>Tarea</th><th>Responsable</th><th>Prioridad</th><th>Fecha límite</th><th>Estado</th><th>Avance</th></tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        <tr>
                            <td><a href="{{ route('projects.tasks.show', [$project, $task]) }}">{{ $task->title }}</a></td>
                            <td>{{ $task->assignee?->name ?? '—' }}</td>
                            <td><span class="badge badge-priority-{{ $task->priority->value }}">{{ $task->priority->label() }}</span></td>
                            <td>{{ $task->due_date->format('d/m/Y') }}</td>
                            <td><x-status-badge :status="$task->status" /></td>
                            <td class="cell-progress"><x-progress-bar :value="$task->progress" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($trashedTasks->isNotEmpty())
        <details class="trash">
            <summary>Papelera ({{ $trashedTasks->count() }})</summary>
            <ul>
                @foreach ($trashedTasks as $task)
                    <li>
                        <span>{{ $task->title }} <span class="muted small">· eliminada {{ $task->deleted_at->diffForHumans() }}</span></span>
                        <form method="POST" action="{{ route('projects.tasks.restore', [$project, $task]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-link">Restaurar</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</section>
