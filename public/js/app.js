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
