{{-- Tabla de proyectos con estadísticas (líder y docente). Parámetros: $title, $items, $empty --}}
<section class="card">
    <h2>{{ $title }}</h2>
    @if ($items->isEmpty())
        <p class="muted">{{ $empty }}</p>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>Proyecto</th><th>Estado</th><th>Líder</th><th>Integrantes</th><th>Tareas abiertas</th><th>Vencidas</th><th>Avance</th></tr>
                </thead>
                <tbody>
                    @foreach ($items as $project)
                        <tr>
                            <td><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a></td>
                            <td><x-status-badge :status="$project->status" /></td>
                            <td>{{ $project->leader->name }}</td>
                            <td>{{ $project->members_count }}</td>
                            <td>{{ $project->open_tasks_count }}</td>
                            <td @class(['text-danger' => $project->overdue_tasks_count > 0])>{{ $project->overdue_tasks_count }}</td>
                            <td class="cell-progress"><x-progress-bar :value="$project->tasks_avg_progress" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
