// Associate existing visible labels with controls, including dynamically opened forms.
export function associateFormLabels(root = document) {
    const doc = root.ownerDocument || root;
    const ensureId = field => {
        if (!field.id) {
            let counter = 1;
            while (doc.getElementById(`portal-field-${counter}`)) counter++;
            field.id = `portal-field-${counter}`;
        }
    };
    root.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]),select,textarea').forEach(field => {
        if (!field.id && !field.getAttribute?.('name')) ensureId(field);
        if (field.labels?.length || field.hasAttribute('aria-label') || field.hasAttribute('aria-labelledby')) return;
        let label = field.previousElementSibling;
        if (label?.tagName !== 'LABEL') {
            const container = field.parentElement;
            const labels = container?.querySelectorAll(':scope > label');
            label = labels?.length === 1 && container.querySelectorAll('input:not([type="hidden"]),select,textarea').length === 1 ? labels[0] : null;
        }
        if (!label || label.htmlFor || !label.textContent.trim()) return;
        ensureId(field);
        label.htmlFor = field.id;
    });
}
if (typeof document !== 'undefined') {
    const start = () => {
        associateFormLabels();
        new MutationObserver(records => {
            for (const record of records) for (const node of record.addedNodes) {
                if (node.nodeType === 1) associateFormLabels(node.parentElement || node);
            }
        }).observe(document.body, {childList: true, subtree: true});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
}
