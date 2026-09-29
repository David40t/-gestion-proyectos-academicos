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

/*
 * Validación complementaria en el navegador (formularios con data-validate).
 * Solo mejora la experiencia: evita un viaje al servidor por errores evidentes.
 * La validación real y obligatoria está en los Form Requests / Services del backend.
 *
 * Lee restricciones declaradas en el HTML: required, maxlength, min/max (números)
 * y data-after="otro_campo" (fecha igual o posterior a la de otro campo).
 */
document.addEventListener('DOMContentLoaded', () => {
    const messages = {
        required: 'Este campo es obligatorio.',
        maxlength: (max) => `Máximo ${max} caracteres.`,
        range: (min, max) => `Debe estar entre ${min} y ${max}.`,
        after: (label) => `Debe ser igual o posterior a ${label.toLowerCase()}.`,
    };

    const labelOf = (field) => document.querySelector(`label[for="${field.id}"]`)?.textContent.trim() ?? field.name;

    const errorFor = (field) => {
        const value = field.value.trim();

        if (field.required && value === '') return messages.required;
        if (field.maxLength > 0 && value.length > field.maxLength) return messages.maxlength(field.maxLength);
        if (field.type === 'number' && value !== '') {
            const number = Number(value);
            if ((field.min !== '' && number < Number(field.min)) || (field.max !== '' && number > Number(field.max))) {
                return messages.range(field.min, field.max);
            }
        }
        if (field.dataset.after && value !== '') {
            const other = field.form.elements[field.dataset.after];
            if (other && other.value && value < other.value) return messages.after(labelOf(other));
        }

        return null;
    };

    const showError = (field, message) => {
        const container = field.closest('.field') ?? field.parentElement;
        let error = container.querySelector('.field-error.client');
        container.querySelectorAll('.field-error:not(.client)').forEach((el) => el.remove()); // error previo del servidor

        field.classList.toggle('is-invalid', Boolean(message));
        if (!message) {
            error?.remove();
            return;
        }
        if (!error) {
            error = document.createElement('p');
            error.className = 'field-error client';
            container.appendChild(error);
        }
        error.textContent = message;
    };

    document.querySelectorAll('form[data-validate]').forEach((form) => {
        const fields = () => [...form.querySelectorAll('input, select, textarea')].filter((f) => f.type !== 'hidden' && f.name);

        form.addEventListener('submit', (event) => {
            let firstInvalid = null;
            fields().forEach((field) => {
                const message = errorFor(field);
                showError(field, message);
                if (message && !firstInvalid) firstInvalid = field;
            });

            if (firstInvalid) {
                event.preventDefault();
                event.stopImmediatePropagation(); // no pedir confirmación si el formulario es inválido
                firstInvalid.focus();
            }
        });

        // Al corregir un campo, se limpia su mensaje.
        fields().forEach((field) => field.addEventListener('change', () => showError(field, errorFor(field))));
    });
});
