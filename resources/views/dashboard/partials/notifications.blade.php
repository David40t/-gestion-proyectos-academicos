<section class="card">
    <div class="section-header">
        <h2>Notificaciones sin leer</h2>
        @can('notificacion.ver')
            <a href="{{ route('notifications.index') }}" class="small">Ver todas</a>
        @endcan
    </div>
    @forelse ($notifications as $notification)
        <div class="list-item">
            <div>
                <strong class="small">{{ $notification->data['title'] ?? '' }}</strong>
                <p class="muted small">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-link small">Ver</button>
            </form>
        </div>
    @empty
        <p class="muted">Estás al día.</p>
    @endforelse
</section>
