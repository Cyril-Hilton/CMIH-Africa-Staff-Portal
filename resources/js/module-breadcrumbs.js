// Keep the page trail aligned with tabs that switch without a page reload.
export function updateModuleBreadcrumb(department, label, doc = document, address = location.href) {
    if (!label) return;
    const url = new URL(address);
    const paths = {creatives: '/portal/creative', brands_marketing: '/portal/brands'};
    if (url.pathname !== paths[department] && !(url.pathname === '/dashboard' && url.searchParams.get('weekly_department') === department)) return;
    const current = doc.querySelector('nav[aria-label="Breadcrumbs"] [aria-current="page"]');
    if (current) current.textContent = label;
}

if (typeof window !== 'undefined') window.updateModuleBreadcrumb = updateModuleBreadcrumb;
