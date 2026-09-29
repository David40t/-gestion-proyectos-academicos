@props(['value' => 0])
@php($percent = (int) round($value ?? 0))
<div class="progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-fill" style="width: {{ $percent }}%"></div>
    <span class="progress-label">{{ $percent }}%</span>
</div>
