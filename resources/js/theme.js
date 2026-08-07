const storageKey = 'cmih-theme';

const getStoredTheme = () => {
    const stored = window.localStorage.getItem(storageKey);
    return stored === 'light' || stored === 'dark' ? stored : null;
};

const updateToggles = (theme) => {
    const label = theme === 'dark' ? 'Dark' : 'Light';
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        const target = button.querySelector('[data-theme-label]');
        if (target) {
            target.textContent = label;
        }
    });
};

const updateThemeImages = (theme) => {
    document.querySelectorAll('[data-theme-src-light][data-theme-src-dark]').forEach((img) => {
        const light = img.getAttribute('data-theme-src-light');
        const dark = img.getAttribute('data-theme-src-dark');
        const next = theme === 'dark' ? dark : light;

        if (next && img.getAttribute('src') !== next) {
            img.setAttribute('src', next);
        }
    });
};

const applyTheme = (theme, persist = true) => {
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
    updateToggles(theme);
    updateThemeImages(theme);
    if (persist) {
        window.localStorage.setItem(storageKey, theme);
    }
};

const initTheme = () => {
    const stored = getStoredTheme();
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const initial = stored ?? (media.matches ? 'dark' : 'light');
    applyTheme(initial, Boolean(stored));

    const handleToggle = () => {
        const current = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    };

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', handleToggle);
    });

    const handleMediaChange = (event) => {
        if (getStoredTheme()) {
            return;
        }
        applyTheme(event.matches ? 'dark' : 'light', false);
    };

    if (media.addEventListener) {
        media.addEventListener('change', handleMediaChange);
    } else if (media.addListener) {
        media.addListener(handleMediaChange);
    }
};

document.addEventListener('DOMContentLoaded', initTheme);
