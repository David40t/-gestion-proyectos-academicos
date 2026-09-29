@props(['status'])
{{-- Recibe cualquier Enum con label(): ProjectStatus o TaskStatus. --}}
<span {{ $attributes->class(['badge', 'badge-'.$status->value]) }}>{{ $status->label() }}</span>
