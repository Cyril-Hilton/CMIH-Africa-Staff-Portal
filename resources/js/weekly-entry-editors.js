// Initialise rich editing only when an individual report is opened.
document.addEventListener('toggle', event => {
    const panel = event.target;
    if (!(panel instanceof HTMLDetailsElement) || !panel.matches('[data-weekly-entry-editor]') || !panel.open) return;
    panel.querySelectorAll('textarea').forEach(field => {
        field.classList.remove('weekly-editor-pending');
        field.classList.add('wysiwyg-editor');
    });
    window.initWysiwygEditors?.(panel);
}, true);

// Source-report editors also appear in Creative Studio dialogs outside the dashboard.
document.addEventListener('click', event => {
    const action = event.target.closest('[data-weekly-support-add], [data-weekly-support-remove]');
    if (!action?.closest('[data-weekly-entry-actions]')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (action.hasAttribute('data-weekly-support-remove')) {
        action.closest('.weekly-support-row')?.remove();
        return;
    }
    const form = action.closest('form');
    const list = document.getElementById(action.dataset.weeklySupportAdd);
    const lead = form?.querySelector('[name="lead_staff_id"]');
    if (!list || !lead) return;
    const row = document.createElement('div');
    row.className = 'grid gap-2 md:grid-cols-[1fr_1fr_auto] weekly-support-row';
    const staff = lead.cloneNode(true);
    staff.name = 'supporting_staff_ids[]';
    staff.value = '';
    staff.setAttribute('aria-label', 'Supporting staff');
    if (staff.options.length) staff.options[0].text = 'Supporting staff...';
    const role = document.createElement('input');
    role.name = 'supporting_roles[]';
    role.type = 'text';
    role.placeholder = 'Role on this weekly action';
    role.setAttribute('aria-label', 'Supporting role');
    role.className = 'rounded-xl border border-brand-white/10 bg-brand-black/70 px-3 py-2 text-xs text-brand-white';
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.dataset.weeklySupportRemove = '';
    remove.textContent = 'Remove';
    remove.className = 'rounded-xl border border-brand-red/30 px-3 py-2 text-xs text-brand-red';
    row.append(staff, role, remove);
    list.append(row);
});
