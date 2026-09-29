{{-- Campos compartidos por crear y editar tarea. --}}
<x-form.input name="title" label="Título" :value="$task->title" required maxlength="150" />
<x-form.textarea name="description" label="Descripción" :value="$task->description" rows="3" />

<div class="grid-2">
    <x-form.select name="priority" label="Prioridad" :value="$task->priority?->value"
                   :options="collect($priorities)->mapWithKeys(fn ($priority) => [$priority->value => $priority->label()])" required />
    <x-form.select name="assigned_to" label="Responsable" :value="$task->assigned_to"
                   :options="$members" placeholder="Sin asignar" />
</div>

<div class="grid-2">
    <x-form.input name="start_date" label="Fecha de inicio" type="date"
                  :value="$task->start_date?->format('Y-m-d')" required
                  min="{{ $project->start_date->format('Y-m-d') }}" />
    <x-form.input name="due_date" label="Fecha límite" type="date"
                  :value="$task->due_date?->format('Y-m-d')" required
                  max="{{ $project->end_date?->format('Y-m-d') }}" />
</div>
<p class="muted small">
    Rango del proyecto: {{ $project->start_date->format('d/m/Y') }} — {{ $project->end_date?->format('d/m/Y') ?? 'sin fecha de cierre' }}
</p>
