export function initialiseProjectRouting(root = document) {
    root.querySelectorAll('[data-project-routing]').forEach(section => {
        section.querySelectorAll('[data-project-requirement]').forEach(select => {
            const fields = section.querySelector(`[data-project-team="${select.dataset.projectRequirement}"]`);
            if (!fields) return;
            fields.hidden = select.value !== '1';
            fields.disabled = select.value !== '1';
        });
    });
}

document.addEventListener('change', event => {
    if (event.target.matches('[data-project-requirement]')) initialiseProjectRouting(event.target.closest('form'));
});
document.addEventListener('DOMContentLoaded', () => initialiseProjectRouting());
document.addEventListener('cmih:silent-content-updated', () => initialiseProjectRouting());

document.addEventListener('toggle', async event => {
    const details = event.target;
    if (!details.matches('details[data-connected-project]') || !details.open || details.dataset.loading === 'true') return;
    const content = details.querySelector('[data-connected-project-content]');
    if (!content) return;
    details.dataset.loading = 'true';
    content.textContent = 'Loading connected teams and timelines…';
    try {
        const response = await fetch(details.dataset.connectedProject, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) throw new Error('Unable to load');
        const data = await response.json();
        content.innerHTML = data.html;
    } catch {
        content.textContent = 'Connected project details could not load. Close and reopen this section to retry, or check your project access.';
    } finally {
        details.dataset.loading = 'false';
    }
}, true);

document.addEventListener('click', async event => {
    const button = event.target.closest('[data-connected-history-url]');
    if (!button) return;
    const section = button.closest('[aria-label="Connected project accountability"]');
    if (!section) return;
    button.disabled = true;
    try {
        const response = await fetch(button.dataset.connectedHistoryUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) throw new Error('Unable to load');
        const data = await response.json();
        section.outerHTML = data.html;
    } catch {
        button.textContent = 'Could not load history · retry';
        button.disabled = false;
    }
});
