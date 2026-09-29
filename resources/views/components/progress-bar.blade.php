@props(['value' => 0])
@php($percent = (int) round($value ?? 0))
{{-- Elemento nativo <progress>: accesible y sin estilos inline (compatible con la CSP estricta). --}}
<div class="progress">
    <progress value="{{ $percent }}" max="100" aria-label="Avance {{ $percent }}%">{{ $percent }}%</progress>
    <span class="progress-label">{{ $percent }}%</span>
</div>
