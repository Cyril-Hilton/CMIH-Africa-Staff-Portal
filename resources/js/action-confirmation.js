import { actionFor } from './action-confirmation-policy';
// One confirmation boundary for links, forms and delegated action buttons.
let activeDialog = null;
const approved = new WeakSet();
const ask = (action, element) => new Promise(resolve => {
    if (activeDialog) return resolve(false);
    const dialog = document.createElement('dialog');
    dialog.className = 'cmih-confirmation';
    dialog.setAttribute('aria-labelledby', 'cmih-confirmation-title');
    dialog.setAttribute('aria-describedby', 'cmih-confirmation-description');
    const title = document.createElement('h2'); title.id = 'cmih-confirmation-title'; title.textContent = `${action === 'Download' ? 'Download File' : `${action} Confirmation`}`;
    const description = document.createElement('p'); description.id = 'cmih-confirmation-description';
    description.textContent = element.dataset.confirm || element.form?.dataset.confirm || (action === 'Download' ? 'Would you like to download this file?' : action === 'Sign Out' ? 'Are you sure you want to sign out of your account?' : `Are you sure you want to ${action.toLowerCase()} this item?`);
    const controls = document.createElement('div'); controls.className = 'cmih-confirmation-actions';
    const cancel = document.createElement('button'); cancel.type = 'button'; cancel.textContent = 'Cancel';
    const confirm = document.createElement('button'); confirm.type = 'button'; confirm.className = 'cmih-confirmation-primary'; confirm.textContent = action === 'Download' ? 'Download' : action;
    controls.append(cancel, confirm); dialog.append(title, description, controls); document.body.append(dialog); document.body.classList.add('cmih-confirmation-open'); activeDialog = dialog;
    let finished = false;
    const finish = (result) => { if (finished) return; finished = true; dialog.close(); dialog.remove(); document.body.classList.remove('cmih-confirmation-open'); activeDialog = null; element.focus?.(); resolve(result); };
    cancel.addEventListener('click', () => finish(false)); confirm.addEventListener('click', () => finish(true));
    dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
    dialog.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) finish(false); } });
    dialog.showModal(); cancel.focus();
});
// Script-driven actions use the same accessible modal as form actions.
window.cmihConfirm = (message, action = 'Continue', trigger = document.activeElement) => ask(action, { dataset: { confirm: message }, focus: () => trigger?.focus?.() });
// Existing inline confirmations remain valid and are not duplicated.
const hasExistingConfirmation = (element, form) => !!element?.closest('[data-confirm-dialog]') || !!form?.closest('[data-existing-confirmation]') || !!(form?.hasAttribute('data-confirm') && document.querySelector('[data-confirm-dialog]')) || /confirm\s*\(/.test((element?.getAttribute('onclick') || '') + (form?.getAttribute('onsubmit') || ''));
document.addEventListener('click', async event => {
    const element = event.target.closest('a, button, input[type="submit"], input[type="button"]');
    if (!element || element.disabled || element.closest('.cmih-confirmation, [data-existing-confirmation]') || element.getAttribute('@click')?.includes('open-confirm-modal') || approved.has(element)) return;
    const form = element.form;
    // Form submissions are handled below, after native validation, preserving the chosen submitter.
    if (form && element.tagName !== 'A' && (element.type === 'submit' || element.type === 'image') && !element.getAttribute('onclick')) return;
    const action = actionFor(element, form);
    if (!action || hasExistingConfirmation(element, form)) return;
    // Menu/disclosure buttons only open options; the final action gets confirmed.
    if (!element.hasAttribute('href') && (element.hasAttribute('aria-expanded') || element.hasAttribute('aria-haspopup'))) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (await ask(action, element)) {
        if (action === 'Download' && element.tagName === 'A' && element.href && new URL(element.href, location.href).origin === location.origin) { location.assign(element.href); return; }
        approved.add(element); if (form) approved.add(form); try { element.click(); } finally { approved.delete(element); if (form) approved.delete(form); } }
}, true);
document.addEventListener('submit', async event => {
    const form = event.target; const submitter = event.submitter;
    if (approved.has(form) || hasExistingConfirmation(submitter, form)) return;
    const action = actionFor(submitter, form);
    if (!action) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (await ask(action, submitter || form)) { approved.add(form); try { form.requestSubmit(submitter || undefined); } finally { approved.delete(form); } }
}, true);
