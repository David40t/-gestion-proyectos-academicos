@extends('layouts.app')

@section('title', 'Notificaciones')

@php($categoryLabels = ['proyecto' => 'Proyecto', 'tarea' => 'Tarea', 'comentario' => 'Comentario', 'recordatorio' => 'Recordatorio'])

@section('content')
    <div class="page-header">
        <h1>Notificaciones</h1>
        @if ($unreadNotifications > 0)
            @can('notificacion.marcar_leida')
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-secondary">Marcar todas como leídas</button>
                </form>
            @endcan
        @endif
    </div>

    <div class="card">
        @forelse ($notifications as $notification)
            <article @class(['notification', 'notification-unread' => $notification->unread()])>
                <div>
                    <span class="badge badge-category-{{ $notification->data['category'] ?? 'general' }}">
                        {{ $categoryLabels[$notification->data['category'] ?? ''] ?? 'General' }}
                    </span>
                    <strong>{{ $notification->data['title'] ?? '' }}</strong>
                    <p class="notification-message">{{ $notification->data['message'] ?? '' }}</p>
                    <span class="muted small">{{ $notification->created_at->diffForHumans() }}</span>
                </div>
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-link">Ver</button>
                </form>
            </article>
        @empty
            <p class="muted">No tienes notificaciones.</p>
        @endforelse

        {{ $notifications->links() }}
    </div>
@endsection
