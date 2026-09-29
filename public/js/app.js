// Comportamiento complementario de la interfaz. Nunca sustituye validaciones ni autorización del backend.
document.addEventListener('DOMContentLoaded', () => {
    // Formularios que requieren confirmación: <form data-confirm="¿Seguro?">
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});

// Ayuda visual: sincroniza estado y avance en los formularios de tareas.
// La coherencia real la garantiza el backend (TaskStateResolver).
document.addEventListener('DOMContentLoaded', () => {
    const status = document.querySelector('[data-progress-status]');
    const progress = document.querySelector('[data-progress-input]');
    if (!status || !progress) {
        return;
    }

    status.addEventListener('change', () => {
        if (status.value === 'completada') progress.value = 100;
        if (status.value === 'pendiente') progress.value = 0;
        if (status.value === 'en_progreso' && (+progress.value === 0 || +progress.value === 100)) progress.value = 10;
    });

    progress.addEventListener('change', () => {
        const value = +progress.value;
        if (value === 100) status.value = 'completada';
        else if (value === 0) status.value = 'pendiente';
        else status.value = 'en_progreso';
    });
});
