document.querySelectorAll('[data-confirm-delete]').forEach((button) => {
    button.addEventListener('click', (event) => {
        const message = button.getAttribute('data-confirm-delete') || 'Delete this record?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});
