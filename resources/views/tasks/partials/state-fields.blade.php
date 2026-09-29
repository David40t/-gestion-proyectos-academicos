{{-- Estado + avance. La coherencia final la garantiza el backend (TaskStateResolver). --}}
<div class="grid-2">
    <x-form.select name="status" label="Estado" :value="$task->status === App\Enums\TaskStatus::Vencida ? ($task->progress > 0 ? 'en_progreso' : 'pendiente') : $task->status?->value"
                   :options="collect($selectableStatuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                   data-progress-status required />
    <x-form.input name="progress" label="Avance (%)" type="number" min="0" max="100" step="5"
                  :value="$task->progress" data-progress-input required />
</div>
<p class="muted small">Pendiente = 0%, En progreso = 1–99%, Completada = 100%. "Vencida" la asigna el sistema.</p>
