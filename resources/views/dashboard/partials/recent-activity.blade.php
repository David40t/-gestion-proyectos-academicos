<section class="card">
    <div class="section-header">
        <h2>Actividad reciente</h2>
        @can('viewAny', App\Models\Audit::class)
            <a href="{{ route('audits.index') }}" class="small">Ver auditoría</a>
        @endcan
    </div>
    @forelse ($recentActivity as $audit)
        <div class="list-item">
            <div>
                <span class="small"><strong>{{ $audit->user?->name ?? 'Sistema' }}</strong> · {{ trans('audit.actions')[$audit->action] ?? $audit->action }}</span>
                <p class="muted small">{{ $audit->project?->title }} · {{ $audit->created_at->diffForHumans() }}</p>
            </div>
        </div>
    @empty
        <p class="muted">Sin actividad reciente.</p>
    @endforelse
</section>
