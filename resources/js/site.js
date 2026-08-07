const initMenuToggle = () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const panel = document.querySelector('[data-menu-panel]');

    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isHidden = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !isHidden);
        toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    });
};

const initReveal = () => {
    const items = document.querySelectorAll('.reveal');

    if (!items.length || !('IntersectionObserver' in window)) {
        items.forEach((item) => item.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.2 }
    );

    items.forEach((item) => observer.observe(item));
};

const initLightbox = () => {
    const triggers = document.querySelectorAll('[data-lightbox]');

    if (!triggers.length) {
        return;
    }

    let overlay = document.querySelector('[data-lightbox-overlay]');
    let gallery = [];
    let currentIndex = 0;

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.dataset.lightboxOverlay = 'true';
        overlay.className =
            'fixed inset-0 z-50 hidden items-center justify-center p-6 lightbox-backdrop';
        overlay.innerHTML = `
            <div class="relative w-full max-w-4xl rounded-2xl border border-brand-white/20 bg-brand-black/90 p-4">
                <button type="button" data-lightbox-close class="absolute right-4 top-4 text-xs uppercase tracking-[0.3em] text-brand-white/70">Close</button>
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs uppercase tracking-[0.3em] text-brand-white/60">
                        <span data-lightbox-title></span>
                        <span data-lightbox-meta></span>
                    </div>
                    <div class="relative">
                        <button type="button" data-lightbox-prev class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full border border-brand-white/30 bg-brand-black/70 px-3 py-2 text-[0.6rem] uppercase tracking-[0.3em] text-brand-white/70">Prev</button>
                        <img data-lightbox-image class="w-full rounded-xl object-cover" alt="" />
                        <button type="button" data-lightbox-next class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full border border-brand-white/30 bg-brand-black/70 px-3 py-2 text-[0.6rem] uppercase tracking-[0.3em] text-brand-white/70">Next</button>
                    </div>
                    <div class="text-xs uppercase tracking-[0.3em] text-brand-white/60">
                        <span data-lightbox-count></span>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    const image = overlay.querySelector('[data-lightbox-image]');
    const title = overlay.querySelector('[data-lightbox-title]');
    const meta = overlay.querySelector('[data-lightbox-meta]');
    const count = overlay.querySelector('[data-lightbox-count]');
    const closeBtn = overlay.querySelector('[data-lightbox-close]');
    const prevBtn = overlay.querySelector('[data-lightbox-prev]');
    const nextBtn = overlay.querySelector('[data-lightbox-next]');

    const closeOverlay = () => {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    };

    closeBtn.addEventListener('click', closeOverlay);
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeOverlay();
        }
    });

    const renderImage = () => {
        if (!gallery.length) {
            return;
        }

        const src = gallery[currentIndex];
        image.setAttribute('src', src);
        image.setAttribute('alt', title.textContent || 'Portfolio image');
        count.textContent = `${currentIndex + 1} / ${gallery.length}`;
        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex === gallery.length - 1;
        prevBtn.classList.toggle('opacity-40', prevBtn.disabled);
        nextBtn.classList.toggle('opacity-40', nextBtn.disabled);
    };

    prevBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        if (currentIndex > 0) {
            currentIndex -= 1;
            renderImage();
        }
    });

    nextBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        if (currentIndex < gallery.length - 1) {
            currentIndex += 1;
            renderImage();
        }
    });

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            title.textContent = trigger.dataset.title || '';
            meta.textContent = trigger.dataset.meta || '';
            const galleryData = trigger.dataset.gallery;
            if (galleryData) {
                try {
                    gallery = JSON.parse(galleryData);
                } catch (error) {
                    gallery = [];
                }
            }

            if (!gallery.length) {
                const src = trigger.getAttribute('href');
                gallery = src ? [src] : [];
            }

            currentIndex = 0;
            renderImage();
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        });
    });
};

const initCounters = () => {
    const counters = document.querySelectorAll('[data-count-target]');

    if (!counters.length) {
        return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

    const formatValue = (value, decimals, prefix, suffix) => {
        const formatted = decimals > 0 ? value.toFixed(decimals) : Math.round(value).toString();
        return `${prefix}${formatted}${suffix}`;
    };

    const runCounter = (element) => {
        if (element.dataset.counted === 'true') {
            return;
        }

        const target = parseFloat(element.dataset.countTarget || '0');
        const decimals = parseInt(element.dataset.countDecimals || '0', 10);
        const duration = parseInt(element.dataset.countDuration || '1400', 10);
        const prefix = element.dataset.countPrefix || '';
        const suffix = element.dataset.countSuffix || '';
        const startValue = parseFloat(element.dataset.countStart || '0');

        if (prefersReducedMotion) {
            element.textContent = formatValue(target, decimals, prefix, suffix);
            element.dataset.counted = 'true';
            return;
        }

        const startTime = performance.now();

        const tick = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = easeOutCubic(progress);
            const current = startValue + (target - startValue) * eased;

            element.textContent = formatValue(current, decimals, prefix, suffix);

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                element.dataset.counted = 'true';
            }
        };

        requestAnimationFrame(tick);
    };

    if (!('IntersectionObserver' in window)) {
        counters.forEach((counter) => runCounter(counter));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    runCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.6 }
    );

    counters.forEach((counter) => observer.observe(counter));
};

document.addEventListener('DOMContentLoaded', () => {
    initMenuToggle();
    initReveal();
    initLightbox();
    initCounters();
});

