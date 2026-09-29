<section class="card">
    <div class="section-header">
        <h2>Usuarios por rol</h2>
        @can('viewAny', App\Models\User::class)
            <a href="{{ route('users.index') }}" class="small">Gestionar</a>
        @endcan
    </div>
    @foreach ($usersByRole as $role => $count)
        <div class="list-item">
            <span class="badge badge-role-{{ strtolower($role) }}">{{ ucfirst(strtolower($role)) }}</span>
            <strong>{{ $count }}</strong>
        </div>
    @endforeach
</section>
