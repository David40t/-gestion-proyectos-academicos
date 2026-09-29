<div class="stats stats-dashboard">
    @foreach ($stats as $stat)
        <div class="stat stat-tone-{{ $stat['tone'] }}">
            <span class="stat-value">{{ $stat['value'] }}</span>
            <span class="stat-label">{{ $stat['label'] }}</span>
        </div>
    @endforeach
</div>
