const escapeText = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));

document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-copy-week]');
    if (!trigger || document.querySelector('.dashboard-copy-dialog[open]')) return;

    const dialog = document.createElement('dialog');
    dialog.className = 'dashboard-copy-dialog';
    dialog.setAttribute('aria-labelledby', 'copy-week-title');
    dialog.innerHTML = `
        <form method="post" data-copy-week-form data-no-ajax data-no-silent>
            <div class="copy-week-header">
                <h2 id="copy-week-title">Copy Entire Week</h2>
                <p>${trigger.dataset.department === 'brands_marketing' ? 'Copy saved Weekly Work Stream rows from every page.' : 'Copy saved weekly activities from every page.'} Each copy is a draft for the destination week.</p>
            </div>
            <div class="copy-week-body">
                <div class="copy-week-dates">
                    <label>Source week<input name="source_week" type="date" value="${escapeText(trigger.dataset.sourceWeek)}" required></label>
                    <label>Destination week<input name="target_week" type="date" value="${escapeText(trigger.dataset.targetWeek)}" required max="${escapeText(trigger.dataset.targetWeek)}"></label>
                </div>
                <button type="button" data-copy-preview>Preview Activities</button>
                <p role="status" data-copy-status></p>
                <div class="copy-week-selection" data-copy-selection hidden>
                    <button type="button" data-copy-select-all>Select All</button>
                    <button type="button" data-copy-clear>Clear Selection</button>
                </div>
                <div data-copy-items></div>
                <p>Targets and responsibilities carry forward. Enter new progress and results in the copied drafts. Earlier reports and existing destination entries stay unchanged.</p>
            </div>
            <div class="copy-week-actions">
                <button type="button" data-copy-cancel>Cancel</button>
                <button type="submit" data-copy-submit disabled>Copy Activities</button>
            </div>
        </form>`;
    document.body.append(dialog);
    dialog.showModal();
    dialog.addEventListener('close', () => {
        dialog.remove();
        if (trigger.isConnected) trigger.focus();
    });

    const form = dialog.querySelector('form');
    const status = dialog.querySelector('[data-copy-status]');
    const list = dialog.querySelector('[data-copy-items]');
    const selection = dialog.querySelector('[data-copy-selection]');
    const submit = dialog.querySelector('[data-copy-submit]');
    const previewButton = dialog.querySelector('[data-copy-preview]');
    const cancel = dialog.querySelector('[data-copy-cancel]');
    let previewVersion = 0;
    let hasPreview = false;
    let saving = false;

    const dates = () => ({
        department: trigger.dataset.department,
        source_week: form.elements.source_week.value,
        target_week: form.elements.target_week.value,
    });
    const choices = () => [...list.querySelectorAll('input:not(:disabled)')];
    const updateSelection = () => {
        const available = choices();
        const selected = available.filter(input => input.checked).length;
        selection.hidden = !available.length;
        submit.disabled = !hasPreview || !selected || saving;
        submit.textContent = selected ? `Copy ${selected} ${selected === 1 ? 'Activity' : 'Activities'}` : 'Copy Activities';
    };
    const api = async (url, options) => {
        const response = await fetch(url, {
            ...options,
            headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''},
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data) {
            throw Error(Object.values(data?.errors ?? {}).flat().join(' ') || data?.message || 'Could not load the copy request. Refresh the page and try again.');
        }
        return data;
    };

    const clearPreview = () => {
        hasPreview = false;
        list.replaceChildren();
        updateSelection();
    };
    const preview = async () => {
        if (saving || !form.reportValidity()) return;
        const version = ++previewVersion;
        clearPreview();
        status.textContent = 'Loading source activities…';
        try {
            const data = await api(trigger.dataset.previewUrl + '?' + new URLSearchParams(dates()));
            if (version !== previewVersion || !dialog.open) return;
            form.elements.source_week.value = data.source_week;
            form.elements.target_week.value = data.target_week;
            list.innerHTML = data.items.map(item => `
                <label class="copy-week-item">
                    <input name="items[]" type="checkbox" value="${Number(item.id)}" ${item.existing ? 'disabled' : item.selected ? 'checked' : ''}>
                    <span><strong>${escapeText(item.title)}</strong><small>${[item.client, item.owner, item.status, item.existing ? 'Already in destination week' : ''].filter(Boolean).map(escapeText).join(' · ')}</small></span>
                </label>`).join('');
            const existing = data.items.filter(item => item.existing).length;
            status.textContent = !data.total ? 'No saved rows for this source week. Choose another week.'
                : `${data.total} saved ${data.total === 1 ? 'row' : 'rows'} found.${existing ? ` ${existing} already in the destination week.` : ''}${data.items.some(item => !item.selected && !item.existing) ? ' Completed and deferred rows are optional.' : ''}`;
            hasPreview = true;
            updateSelection();
        } catch (error) {
            if (version === previewVersion && dialog.open) status.textContent = error.message;
        }
    };

    previewButton.addEventListener('click', preview);
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => {
        if (saving) event.preventDefault();
    });
    selection.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button || saving) return;
        choices().forEach(input => { input.checked = button.hasAttribute('data-copy-select-all'); });
        updateSelection();
    });
    form.addEventListener('change', event => {
        if (event.target.type === 'date') {
            ++previewVersion;
            clearPreview();
            status.textContent = 'Dates changed. Preview the activities again.';
        } else {
            updateSelection();
        }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submit.disabled) return;
        const body = new FormData(form);
        body.set('department', trigger.dataset.department);
        if (trigger.dataset.copyOrigin) body.set('origin', trigger.dataset.copyOrigin);
        saving = true;
        updateSelection();
        const controls = [previewButton, cancel, form.elements.source_week, form.elements.target_week, ...choices(), ...selection.querySelectorAll('button')];
        controls.forEach(control => { control.disabled = true; });
        status.textContent = 'Creating weekly drafts…';
        try {
            const data = await api(trigger.dataset.storeUrl, {method: 'POST', body});
            status.textContent = data.message;
            location.assign(data.url);
        } catch (error) {
            status.textContent = error.message;
            saving = false;
            controls.forEach(control => { control.disabled = false; });
            updateSelection();
        }
    });

    preview();
});
document.addEventListener('change',event=>{
    if(!event.target.matches('[data-week-picker]')||!event.target.value)return;
    const url=new URL(location.href);url.searchParams.set('report_period','week');url.searchParams.set('report_date',event.target.value);url.searchParams.set('weekly_from',event.target.value);url.searchParams.set('weekly_to',event.target.selectedOptions[0].dataset.end);url.searchParams.delete('weekly_auto');
    for(const key of [...url.searchParams.keys()])if(key.endsWith('_page')||['brands_search','department_entry_search','search_weekly'].includes(key))url.searchParams.delete(key);
    url.hash='staff-weekly-performance';location.assign(url);
});
document.addEventListener('click',event=>{
    if(!event.target.closest('[data-dashboard-present]'))return;
    document.body.classList.toggle('dashboard-presentation');
    const button=event.target.closest('[data-dashboard-present]');
    const active=document.body.classList.contains('dashboard-presentation');button.setAttribute('aria-pressed',String(active));button.textContent=active?'Exit Presentation Mode':'Presentation Mode';
});

document.addEventListener('keydown',event=>{if(event.key==='Escape'&&document.body.classList.contains('dashboard-presentation')){document.body.classList.remove('dashboard-presentation');const b=document.querySelector('[data-dashboard-present]');if(b){b.setAttribute('aria-pressed','false');b.textContent='Presentation Mode';}}});
