<section class="card">
    <h2>Comentarios recientes</h2>
    @forelse ($recentComments as $comment)
        <div class="list-item">
            <div>
                <strong class="small">{{ $comment->author->name }}</strong>
                @if ($comment->is_observation) <span class="badge badge-observation">Observación</span> @endif
                <p class="small">{{ str($comment->body)->limit(90) }}</p>
                <p class="muted small">
                    <a href="{{ $comment->task ? route('projects.tasks.show', [$comment->project_id, $comment->task]) : route('projects.show', $comment->project_id) }}#comentario-{{ $comment->id }}">
                        {{ $comment->project->title }}{{ $comment->task ? ' · '.$comment->task->title : '' }}
                    </a>
                    · {{ $comment->created_at->diffForHumans() }}
                </p>
            </div>
        </div>
    @empty
        <p class="muted">Sin comentarios recientes.</p>
    @endforelse
</section>
