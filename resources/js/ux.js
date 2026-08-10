
const initLoader = () => {
    // Check if we should show the loader immediately on page load
    const shouldShowOnLoad = sessionStorage.getItem('show-loader') === 'true';

    const loader = document.createElement('div');
    loader.id = 'global-loader';
    
    if (shouldShowOnLoad) {
        // Create it as visible and transition-ready
        loader.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-brand-black/50 backdrop-blur-sm transition-opacity duration-300';
    } else {
        // Create it as hidden
        loader.className = 'fixed inset-0 z-[100] hidden flex items-center justify-center bg-brand-black/50 backdrop-blur-sm transition-opacity duration-300 opacity-0';
    }
    
    loader.innerHTML = `
        <div class="relative flex flex-col items-center gap-4">
            <div class="h-12 w-12 animate-spin rounded-full border-4 border-brand-white/10 border-t-brand-red"></div>
            <p class="animate-pulse text-xs uppercase tracking-[0.3em] text-brand-white/80">${document.querySelector('meta[name="site-theme"]')?.content || 'Making It Happen...'}</p>
        </div>
    `;
    document.body.appendChild(loader);

    const showLoader = () => {
        sessionStorage.setItem('show-loader', 'true');
        loader.classList.remove('hidden');
        // Force reflow
        void loader.offsetWidth;
        loader.classList.remove('opacity-0');
    };

    const hideLoader = () => {
        sessionStorage.removeItem('show-loader');
        loader.classList.add('opacity-0');
        setTimeout(() => {
            loader.classList.add('hidden');
        }, 300);
    };

    // If we showed the loader on load, hide it once everything (CSS, images, JS) is fully loaded
    if (shouldShowOnLoad) {
        window.addEventListener('load', () => {
            // Add a small delay for ultra-smooth rendering transition
            setTimeout(hideLoader, 150);
        });
        
        // Safety timeout to prevent loader from hanging if window.load takes too long (e.g. slow third-party resource)
        setTimeout(hideLoader, 3000);
    }

    // Show on form submit
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;

        if (!e.target.hasAttribute('data-no-loader')) {
            showLoader();
        }
    });

    // Show on internal link clicks (not hash links, not target blank, not download)
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented) return;

        const link = e.target.closest('a');
        if (link) {
            const href = link.getAttribute('href');
            if (
                href &&
                link.href &&
                link.href.startsWith(window.location.origin) &&
                link.target !== '_blank' &&
                !link.hasAttribute('download') &&
                !href.startsWith('#') &&
                !href.startsWith('javascript:') &&
                !e.ctrlKey &&
                !e.metaKey
            ) {
                showLoader();
            }
        }
    });

    // Hide loader when page is restored from bfcache (back button)
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            hideLoader();
        }
    });
};

const initPrefetch = () => {
    // Simple link prefetching on hover
    const links = document.querySelectorAll('a');
    if (!('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const link = entry.target;
                if (
                    link.href &&
                    link.href.startsWith(window.location.origin) &&
                    !link.getAttribute('data-prefetched')
                ) {
                    link.addEventListener('mouseenter', () => {
                        const existingLink = document.head.querySelector(`link[href="${link.href}"]`);
                        if (!existingLink) {
                            const prefetchLink = document.createElement('link');
                            prefetchLink.rel = 'prefetch';
                            prefetchLink.href = link.href;
                            document.head.appendChild(prefetchLink);
                            link.setAttribute('data-prefetched', 'true');
                        }
                    }, { once: true });
                    observer.unobserve(link);
                }
            }
        });
    });

    links.forEach(link => observer.observe(link));
};

const initImageFallbacks = () => {
    const fallbackUrl = `${window.location.origin}/images/logo/icon-192.png`;

    const applyFallback = (target) => {
        if (!(target instanceof HTMLImageElement)) return;

        const src = target.getAttribute('src') || '';
        if (!src || target.dataset.imageFallbackApplied === 'true') return;

        target.dataset.imageFallbackApplied = 'true';
        target.src = target.dataset.fallbackSrc || fallbackUrl;
        if (!target.alt) {
            target.alt = 'CMIH Africa';
        }
    };

    document.addEventListener('error', (event) => applyFallback(event.target), true);

    document.querySelectorAll('img').forEach((image) => {
        if (image.complete && image.naturalWidth === 0) {
            applyFallback(image);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initLoader();
    initPrefetch();
    initImageFallbacks();
});
