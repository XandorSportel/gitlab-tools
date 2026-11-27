document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-open-modal]');

    if (!trigger) {
        return;
    }

    const modal = document.getElementById(trigger.dataset.openModal);

    if (modal instanceof HTMLDialogElement && !modal.open) {
        modal.showModal();
    }
});

document.addEventListener('change', (event) => {
    const input = event.target;

    if (input.matches('[data-submit-on-change]')) {
        input.form.requestSubmit();
    }
});
