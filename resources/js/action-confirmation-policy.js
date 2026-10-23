export const actionFor = (element, form = null) => {
    const text = [form?.dataset.confirm, element?.dataset.confirm, element?.getAttribute('aria-label'), element?.getAttribute('title'), element?.textContent, element?.value].filter(Boolean).join(' ').trim();
    const route = element?.getAttribute('href') || form?.getAttribute('action') || '';
    if (element?.getAttribute('href') && /\b(directory|history|view|list|queue|reports)\b/i.test(text) && !/\/(?:export|download|delete|destroy|suspend|archive|remove|revoke)(?:\/|\?|$)/i.test(route) && !element?.hasAttribute('download') && !element?.hasAttribute('data-confirm')) return null;
    if (!form && element?.getAttribute('href') && /^reset(?: filters?)?$/i.test(text) && !/password|reset-token|reset-password/i.test(route) && !element?.hasAttribute('data-confirm')) return null;
    const method = form?.querySelector('[name="_method"]')?.value?.toLowerCase();
    if (/\b(?:log|sign)\s*out\b/i.test(text)) return 'Sign Out';
    if (form && /\breturn\b/i.test(text)) return 'Return';
    if (/\barchive\b/i.test(text)) return 'Archive';
    if (method === 'delete' || /\b(delete|destroy|purge)\b/i.test(text) || /\/(delete|destroy|purge)(\/|\?|$)/i.test(route)) return 'Delete';
    for (const action of ['Suspend', 'Reactivate', 'Activate', 'Archive', 'Deactivate', 'Revoke', 'Remove', 'Reject', 'Approve', 'Restore', 'Publish', 'Reset', 'Confirm', 'Close', 'Demote', 'Unlock', 'Lock', 'Transfer']) {
        if (new RegExp(`\\b${action}\\b`, 'i').test(text) || new RegExp(`/${action}(?:/|\\?|$)`, 'i').test(route)) return action;
    }
    if (element?.hasAttribute('download') || /\b(download|export|print)\b/i.test(text) || /\/(?:export|download)(?:[-/?.]|$)|\.(?:pdf|xlsx?|zip)(?:\?|$)/i.test(route)) return 'Download';
    return element?.hasAttribute('data-confirm') || form?.hasAttribute('data-confirm') ? 'Continue' : null;
};
