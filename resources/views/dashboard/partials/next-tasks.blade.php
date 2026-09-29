{{-- Parámetros: $title, $showAssignee --}}
<section class="card">
    <div class="section-header">
        <h2>{{ $title }}</h2>
        @if (! $showAssignee)
            @can('viewAny', App\Models\Task::class)
                <a href="{{ route('tasks.mine') }}" class="small">Ver todas</a>
            @endcan
        @endif
    </div>
    @forelse ($nextTasks as $task)
        <div class="list-item">
            <div>
                <a href="{{ route('projects.tasks.show', [$task->project_id, $task]) }}">{{ $task->title }}</a>
                <p class="muted small">
                    {{ $task->project->title }}
                    @if ($showAssignee) · {{ $task->assignee?->name ?? 'Sin responsable' }} @endif
                </p>
            </div>
            <div class="list-item-meta">
                <span @class(['small', 'text-danger' => $task->due_date->isPast() && ! $task->due_date->isToday()])>{{ $task->due_date->format('d/m/Y') }}</span>
                <x-status-badge :status="$task->status" />
            </div>
        </div>
    @empty
        <p class="muted">No hay tareas pendientes.</p>
    @endforelse
</section>
