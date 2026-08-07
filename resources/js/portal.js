const initPortalMenu = () => {
    const toggle = document.querySelector('[data-portal-toggle]');
    const panel = document.querySelector('[data-portal-panel]');

    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        panel.classList.toggle('flex');
    });
};

const initEmailPreview = () => {
    const input = document.querySelector('[data-name-input]');
    const preview = document.querySelector('[data-email-preview]');

    if (!input || !preview) {
        return;
    }

    const domain = preview.dataset.domain || 'cmih.africa';

    const updatePreview = () => {
        const value = input.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '')
            .trim();
        const base = value.length ? value : 'yourname';
        preview.textContent = `${base}@${domain}`;
    };

    updatePreview();
    input.addEventListener('input', updatePreview);
};

document.addEventListener('DOMContentLoaded', () => {
    initPortalMenu();
    initEmailPreview();
});


