// Paging updates a collection on this page. A different route must navigate normally.
export function isPaginationDestination(href, currentHref, insidePagination = false) {
    const current = new URL(currentHref);
    const destination = new URL(href, current);
    if (destination.origin !== current.origin || destination.pathname !== current.pathname) return false;
    return insidePagination || [...destination.searchParams.keys()].some(key => key === 'page' || key.endsWith('_page'));
}
