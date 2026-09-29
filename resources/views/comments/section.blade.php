{{--
    Sección de comentarios reutilizable.
    Parámetros: $project, $comments, $task (opcional: null = comentarios generales del proyecto).
--}}
@php($task ??= null)
<section class="card" id="comentarios">
    <h2>{{ $task ? 'Comentarios de la tarea' : 'Comentarios del proyecto' }} ({{ $comments->count() }})</h2>

    @can('create', [App\Models\Comment::class, $project])
        <form method="POST" action="{{ route('projects.comments.store', $project) }}" class="comment-form" novalidate data-validate>
            @csrf
            @if ($task)
                <input type="hidden" name="task_id" value="{{ $task->id }}">
            @endif
            <x-form.textarea name="body" label="Nuevo comentario" rows="3" maxlength="2000" required />
            <div class="comment-form-actions">
                @can('markObservation', [App\Models\Comment::class, $project])
                    <label class="checkbox">
                        <input type="checkbox" name="is_observation" value="1" @checked(old('is_observation'))>
                        Registrar como observación docente
                    </label>
                @endcan
                <button type="submit" class="btn btn-primary">Publicar</button>
            </div>
        </form>
    @endcan

    @forelse ($comments as $comment)
        <article @class(['comment', 'comment-observation' => $comment->is_observation]) id="comentario-{{ $comment->id }}">
            <header class="comment-header">
                <strong>{{ $comment->author->name }}</strong>
                @if ($comment->is_observation)
                    <span class="badge badge-observation">Observación docente</span>
                @endif
                <span class="muted small">
                    {{ $comment->created_at->format('d/m/Y H:i') }}
                    @if ($comment->updated_at->gt($comment->created_at))
                        · editado
                    @endif
                </span>
            </header>

            {{-- Blade escapa el contenido: el texto del usuario nunca se interpreta como HTML. --}}
            <p class="comment-body">{{ $comment->body }}</p>

            @canany(['update', 'delete'], $comment)
                <div class="comment-actions">
                    @can('update', $comment)
                        <details>
                            <summary class="btn-link">Editar</summary>
                            <form method="POST" action="{{ route('projects.comments.update', [$project, $comment]) }}">
                                @csrf
                                @method('PUT')
                                <textarea name="body" rows="3" maxlength="2000" required>{{ $comment->body }}</textarea>
                                <button type="submit" class="btn btn-secondary">Guardar</button>
                            </form>
                        </details>
                    @endcan
                    @can('delete', $comment)
                        <form method="POST" action="{{ route('projects.comments.destroy', [$project, $comment]) }}" data-confirm="¿Eliminar este comentario?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger">Eliminar</button>
                        </form>
                    @endcan
                </div>
            @endcanany
        </article>
    @empty
        <p class="muted">Aún no hay comentarios.</p>
    @endforelse
</section>
