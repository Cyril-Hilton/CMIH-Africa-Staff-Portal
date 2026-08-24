<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.CMIHAssetAsyncBound) return;
        window.CMIHAssetAsyncBound = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function showAssetToast(message, type = 'success') {
            let container = document.getElementById('asset-manager-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'asset-manager-toast-container';
                container.className = 'fixed bottom-5 right-5 z-50 flex max-w-sm flex-col gap-2 pointer-events-none';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `pointer-events-auto rounded-2xl border px-4 py-3 text-xs font-semibold shadow-2xl backdrop-blur-xl transition duration-200 ${
                type === 'error'
                    ? 'border-red-500/30 bg-red-950/90 text-red-200'
                    : 'border-emerald-500/30 bg-emerald-950/90 text-emerald-200'
            }`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        }

        function sameAssetListPath(link, region) {
            const url = new URL(link.href, window.location.origin);
            return url.pathname === region.dataset.assetListPath;
        }

        function actionBelongsToRegion(form, region) {
            const action = new URL(form.action || window.location.href, window.location.origin);
            return action.pathname.startsWith(region.dataset.assetActionPrefix || region.dataset.assetListPath || '');
        }

        function currentAssetRegionForLocation() {
            return Array.from(document.querySelectorAll('[data-asset-async-region]'))
                .find((region) => window.location.pathname === region.dataset.assetListPath);
        }

        async function replaceAssetRegion(region, targetUrl, options = {}) {
            const { pushState = false, preserveScroll = true } = options;
            const previousScrollY = window.scrollY;
            const scrollPositions = new Map();

            region.querySelectorAll('.overflow-x-auto, [data-preserve-scroll]').forEach((container, index) => {
                scrollPositions.set(container.dataset.scrollKey || index, {
                    left: container.scrollLeft,
                    top: container.scrollTop,
                });
            });

            region.classList.add('opacity-70', 'pointer-events-none');

            try {
                const response = await fetch(targetUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    showAssetToast('The asset manager could not refresh. Please try again.', 'error');
                    return;
                }

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const freshRegion = doc.getElementById(region.id);

                if (!freshRegion) {
                    showAssetToast('The asset manager response was incomplete. Please refresh once.', 'error');
                    return;
                }

                region.replaceWith(freshRegion);

                freshRegion.querySelectorAll('.overflow-x-auto, [data-preserve-scroll]').forEach((container, index) => {
                    const saved = scrollPositions.get(container.dataset.scrollKey || index);
                    if (saved) {
                        container.scrollLeft = saved.left;
                        container.scrollTop = saved.top;
                    }
                });

                if (window.Alpine?.initTree) {
                    window.Alpine.initTree(freshRegion);
                }
                if (typeof window.enhanceTables === 'function') {
                    window.enhanceTables(freshRegion);
                }
                if (typeof window.initWysiwygEditors === 'function') {
                    window.initWysiwygEditors(freshRegion);
                }

                if (pushState) {
                    window.history.pushState({ assetManagerAsync: true, regionId: freshRegion.id }, '', targetUrl);
                }
                if (preserveScroll) {
                    window.scrollTo(window.scrollX, previousScrollY);
                }
            } catch (error) {
                console.debug('Asset manager async refresh failed:', error);
                showAssetToast('Network error while updating the asset manager.', 'error');
            } finally {
                document.getElementById(region.id)?.classList.remove('opacity-70', 'pointer-events-none');
            }
        }

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : event.target?.parentElement;
            const link = target?.closest('a');
            const region = link?.closest('[data-asset-async-region]');
            if (!link || !region) return;
            if (link.target || link.hasAttribute('download') || link.dataset.noAssetAsync !== undefined) return;
            if (!sameAssetListPath(link, region)) return;

            event.preventDefault();
            replaceAssetRegion(region, link.href, { pushState: true, preserveScroll: true });
        });

        document.addEventListener('submit', async (event) => {
            const form = event.target;
            const region = form instanceof HTMLFormElement ? form.closest('[data-asset-async-region]') : null;
            if (!form || !region || form.dataset.noAssetAsync !== undefined || event.defaultPrevented) return;
            if (!actionBelongsToRegion(form, region)) return;

            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            event.preventDefault();

            const submitter = event.submitter || form.querySelector('button[type="submit"]');
            const originalContent = submitter?.innerHTML;
            if (submitter) {
                submitter.disabled = true;
                submitter.classList.add('opacity-60');
            }

            const action = form.action || window.location.href;
            const method = (form.querySelector('input[name="_method"]')?.value || form.method || 'POST').toUpperCase();
            const isGet = method === 'GET';
            const body = isGet ? null : new FormData(form);
            const requestUrl = isGet ? `${action}?${new URLSearchParams(new FormData(form)).toString()}` : action;

            try {
                const response = await fetch(requestUrl, {
                    method: isGet ? 'GET' : 'POST',
                    body,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json, text/html',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    let errorMsg = 'Please check the form entries and try again.';
                    try {
                        const data = await response.json();
                        if (data.message) {
                            errorMsg = data.message;
                        } else if (data.errors) {
                            const firstError = Object.values(data.errors).flat().find(Boolean);
                            if (firstError) errorMsg = firstError;
                        }
                    } catch (error) {}
                    showAssetToast(errorMsg, 'error');
                    return;
                }

                if (!isGet) {
                    showAssetToast('Asset manager updated.');
                }

                const refreshUrl = isGet ? requestUrl : (document.getElementById(region.id)?.dataset.refreshUrl || region.dataset.refreshUrl || window.location.href);
                await replaceAssetRegion(document.getElementById(region.id) || region, refreshUrl, {
                    pushState: isGet,
                    preserveScroll: true,
                });
            } catch (error) {
                console.debug('Asset manager async submit failed:', error);
                showAssetToast('Network error while saving asset data.', 'error');
            } finally {
                if (submitter) {
                    submitter.disabled = false;
                    submitter.classList.remove('opacity-60');
                    if (originalContent !== undefined) {
                        submitter.innerHTML = originalContent;
                    }
                }
            }
        });

        const initialRegion = currentAssetRegionForLocation();
        if (initialRegion && !window.history.state?.assetManagerAsync) {
            window.history.replaceState({ assetManagerAsync: true, regionId: initialRegion.id }, '', window.location.href);
        }

        window.addEventListener('popstate', (event) => {
            const region = event.state?.regionId
                ? document.getElementById(event.state.regionId)
                : currentAssetRegionForLocation();
            if (region) {
                replaceAssetRegion(region, window.location.href, { pushState: false, preserveScroll: true });
            }
        });
    });
</script>
