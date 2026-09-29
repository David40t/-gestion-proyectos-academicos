{{-- Campos compartidos por crear y editar proyecto. --}}
<x-form.input name="title" label="Título" :value="$project->title" required maxlength="150" />
<x-form.textarea name="description" label="Descripción" :value="$project->description" required />
<x-form.textarea name="objectives" label="Objetivos" :value="$project->objectives" />

<div class="grid-2">
    <x-form.input name="start_date" label="Fecha de inicio" type="date" :value="$project->start_date?->format('Y-m-d')" required />
    <x-form.input name="end_date" label="Fecha de finalización" type="date" :value="$project->end_date?->format('Y-m-d')" />
</div>

<x-form.select name="teacher_id" label="Docente responsable" :value="$project->teacher_id"
               :options="$teachers->pluck('name', 'id')" required />
