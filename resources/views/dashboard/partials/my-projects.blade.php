<section class="card">
    <h2>Mis proyectos</h2>
    @if ($projects->isEmpty())
        <p class="muted">
            Aún no participas en ningún proyecto.
            @can('create', App\Models\Project::class)
                <a href="{{ route('projects.create') }}">Crea el primero</a> o pide a un líder que te agregue.
            @endcan
        </p>
    @else
        <div class="project-cards">
            @foreach ($projects as $project)
                <a href="{{ route('projects.show', $project) }}" class="project-card">
                    <div class="project-card-header">
                        <strong>{{ $project->title }}</strong>
                        <x-status-badge :status="$project->status" />
                    </div>
                    <p class="muted small">Líder: {{ $project->leader->name }} · {{ $project->members_count }} integrantes</p>
                    <x-progress-bar :value="$project->tasks_avg_progress" />
                </a>
            @endforeach
        </div>
    @endif
</section>
