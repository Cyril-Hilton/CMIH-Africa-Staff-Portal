const generatedSelector = '[data-silent-generated], #global-loader';
const filterParameterPattern = /(^|_)(page|filter|search|sort|direction|status|department|category|type|period|date|from|to|start|end|priority|assignee|range|year|month|week)($|_)/i;
const activeRequests = new Map();

const getRoot = (scope = document) => scope.querySelector('[data-silent-root]');

const stableChildren = (element) => Array.from(element?.children || [])
    .filter((child) => !child.matches(generatedSelector));

const pathFromRoot = (element, root) => {
    if (!element || !root || !root.contains(element)) return null;

    const path = [];
    let current = element;

    while (current && current !== root) {
        const parent = current.parentElement;
        const index = stableChildren(parent).indexOf(current);
        if (!parent || index < 0) return null;
        path.unshift(index);
        current = parent;
    }

    return current === root ? path : null;
};

const elementAtPath = (root, path) => {
    let current = root;

    for (const index of path || []) {
        current = stableChildren(current)[index];
        if (!current) return null;
    }

    return current;
};

const isPaginationLink = (link) => {
    if (link.closest('[data-mega-pagination], [data-weekly-pagination]')) return false;
    if (link.closest('nav[aria-label*="pagination" i]')) return true;

    const url = new URL(link.href, window.location.origin);
    return Array.from(url.searchParams.keys()).some((key) => key === 'page' || key.endsWith('_page'));
};

const isFilterLink = (link) => {
    if (link.hasAttribute('data-silent-link')) return true;
    if (link.origin !== window.location.origin || link.pathname !== window.location.pathname) return false;

    const url = new URL(link.href);
    return Array.from(url.searchParams.keys()).some((key) => filterParameterPattern.test(key));
};

const hasResultContent = (element, allowCollectionFallback = false) => {
    if (element.querySelector('table, article, [data-result-item], [data-filter-results]')) return true;
    if (!allowCollectionFallback) return false;

    const substantiveChildren = stableChildren(element).filter((child) => {
        if (child.matches('nav, p, script, style')) return false;
        if (child.matches('[aria-label*="pagination" i]')) return false;
        return !child.querySelector('nav[aria-label*="pagination" i]') || child.children.length > 1;
    });

    return substantiveChildren.length > 1;
};

const findRegion = (source) => {
    const root = getRoot();
    if (!root) return null;

    const sourceElement = source instanceof Element ? source : root;
    const explicitRegion = sourceElement.closest('[data-silent-region]');
    if (explicitRegion && root.contains(explicitRegion)) return explicitRegion;

    const pagination = sourceElement.closest('nav[aria-label*="pagination" i]');
    let current = pagination?.parentElement
        || sourceElement.closest('form')?.parentElement
        || sourceElement.parentElement;

    while (current && current !== root) {
        if (hasResultContent(current, Boolean(pagination))) return current;
        current = current.parentElement;
    }

    return root;
};

const describeRegion = (region, root = getRoot()) => {
    if (!region || !root) return null;

    return {
        id: region.id || null,
        key: region.dataset.silentRegion || null,
        path: pathFromRoot(region, root),
    };
};

const locateRegion = (scope, descriptor) => {
    const root = getRoot(scope);
    if (!root || !descriptor) return null;

    if (descriptor.key) {
        const keyedRegion = Array.from(scope.querySelectorAll('[data-silent-region]'))
            .find((element) => element.dataset.silentRegion === descriptor.key);
        if (keyedRegion) return keyedRegion;
    }

    if (descriptor.id) {
        const idRegion = scope.getElementById?.(descriptor.id);
        if (idRegion && root.contains(idRegion)) return idRegion;
    }

    return elementAtPath(root, descriptor.path);
};

const showRegionError = (region) => {
    const notice = document.createElement('div');
    notice.dataset.silentGenerated = 'true';
    notice.className = 'cmih-silent-error';
    notice.setAttribute('role', 'alert');
    notice.textContent = 'This view could not be refreshed. Please try again.';
    region.prepend(notice);
    window.setTimeout(() => notice.remove(), 5000);
};

const setBusy = (region, busy) => {
    region.classList.toggle('cmih-silent-loading', busy);
    if (busy) {
        region.setAttribute('aria-busy', 'true');
    } else {
        region.removeAttribute('aria-busy');
    }
};

const refreshEnhancements = (region) => {
    window.enhanceTables?.(region);
    window.initWysiwygEditors?.(region);

    document.dispatchEvent(new CustomEvent('cmih:silent-content-updated', {
        detail: { region },
    }));
};

const navigate = async (target, options = {}) => {
    const targetUrl = new URL(target, window.location.origin);
    if (targetUrl.origin !== window.location.origin) return false;

    const currentRoot = getRoot();
    const currentRegion = options.descriptor
        ? locateRegion(document, options.descriptor)
        : findRegion(options.source);
    const descriptor = options.descriptor || describeRegion(currentRegion, currentRoot);

    if (!currentRegion || !descriptor) return false;

    const requestKey = descriptor.key || descriptor.id || JSON.stringify(descriptor.path);
    activeRequests.get(requestKey)?.abort();
    const controller = new AbortController();
    activeRequests.set(requestKey, controller);

    const previousScrollX = window.scrollX;
    const previousScrollY = window.scrollY;
    setBusy(currentRegion, true);

    try {
        const response = await fetch(targetUrl.toString(), {
            method: 'GET',
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) throw new Error(`Request failed with status ${response.status}`);

        const html = await response.text();
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const freshRegion = locateRegion(parsed, descriptor);
        if (!freshRegion) throw new Error('The refreshed result region was not found.');

        const importedRegion = document.importNode(freshRegion, true);
        currentRegion.replaceWith(importedRegion);

        if (options.updateHistory !== false) {
            const state = {
                ...(window.history.state || {}),
                cmihSilent: descriptor,
            };

            if (!window.history.state?.cmihSilent) {
                window.history.replaceState(state, '', window.location.href);
            }

            window.history.pushState(state, '', targetUrl.toString());
        }

        if (parsed.title) document.title = parsed.title;
        refreshEnhancements(importedRegion);
        window.scrollTo(previousScrollX, previousScrollY);

        return true;
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Silent table refresh failed:', error);
            setBusy(currentRegion, false);
            showRegionError(currentRegion);
        }

        return false;
    } finally {
        if (activeRequests.get(requestKey) === controller) {
            activeRequests.delete(requestKey);
        }
        document.querySelectorAll('.cmih-silent-loading').forEach((region) => setBusy(region, false));
    }
};

const formUrl = (form, submitter = null) => {
    const url = new URL(form.action || window.location.href, window.location.origin);
    const data = submitter && typeof FormData === 'function'
        ? new FormData(form, submitter)
        : new FormData(form);

    Array.from(url.searchParams.keys()).forEach((key) => {
        if (key === 'page' || key.endsWith('_page')) url.searchParams.delete(key);
    });

    const replacedKeys = new Set();
    for (const [key, value] of data.entries()) {
        if (!replacedKeys.has(key)) {
            url.searchParams.delete(key);
            replacedKeys.add(key);
        }
        if (value instanceof File) continue;
        if (String(value).trim() !== '') url.searchParams.append(key, value);
    }

    return url;
};

const isSilentGetForm = (form) => {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.dataset.noSilent !== undefined || form.target === '_blank') return false;

    return (form.getAttribute('method') || 'GET').toUpperCase() === 'GET'
        && Boolean(form.closest('[data-silent-root]'));
};

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!isSilentGetForm(form)) return;

    event.preventDefault();
    event.stopPropagation();
    navigate(formUrl(form, event.submitter), {
        source: form,
        updateHistory: true,
    });
}, true);

document.addEventListener('change', (event) => {
    const control = event.target;
    const form = control instanceof Element ? control.closest('form') : null;
    const inlineHandler = control instanceof Element ? control.getAttribute('onchange') || '' : '';

    if (!isSilentGetForm(form) || !/\.submit\s*\(/.test(inlineHandler)) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    navigate(formUrl(form), {
        source: form,
        updateHistory: true,
    });
}, true);

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

    const target = event.target instanceof Element ? event.target : event.target?.parentElement;
    const link = target?.closest('a');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
    if (link.closest('[data-mega-pagination], [data-weekly-pagination], [data-weekly-department-tab]')) return;
    if (!isPaginationLink(link) && !isFilterLink(link)) return;

    event.preventDefault();
    event.stopPropagation();
    navigate(link.href, {
        source: link,
        updateHistory: true,
    });
}, true);

window.addEventListener('popstate', (event) => {
    if (!event.state?.cmihSilent) return;

    navigate(window.location.href, {
        descriptor: describeRegion(getRoot()),
        updateHistory: false,
    });
});

window.CMIHSilentNavigation = {
    navigate,
};
