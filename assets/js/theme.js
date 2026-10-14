(() => {
    const root = document.documentElement;
    const storageKey = root.dataset.themeStorageKey;
    const validTheme = (theme) => theme === 'dark' || theme === 'light';
    if (!storageKey) return;

    const readStoredTheme = () => {
        try {
            const storedTheme = window.localStorage.getItem(storageKey);
            return validTheme(storedTheme) ? storedTheme : null;
        } catch (error) {
            return null;
        }
    };

    const storedTheme = readStoredTheme();
    const systemPrefersDark = typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

    const updateToggle = (button, theme) => {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        const visibleIcon = theme === 'dark' ? 'sun' : 'moon';
        button.setAttribute('aria-label', `Switch to ${nextTheme} mode`);
        button.setAttribute('title', `Switch to ${nextTheme} mode`);
        button.setAttribute('aria-pressed', String(theme === 'dark'));
        button.querySelectorAll('[data-theme-icon]').forEach((icon) => {
            icon.hidden = icon.dataset.themeIcon !== visibleIcon;
        });
    };

    const initializeThemeToggles = () => {
        const buttons = document.querySelectorAll('[data-theme-toggle]');
        buttons.forEach((button) => {
            updateToggle(button, root.dataset.theme);
            button.addEventListener('click', () => {
                const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
                root.dataset.theme = theme;
                try {
                    window.localStorage.setItem(storageKey, theme);
                } catch (error) {
                    // The selected theme still applies for this page when storage is unavailable.
                }
                buttons.forEach((toggle) => updateToggle(toggle, theme));
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeThemeToggles, { once: true });
    } else {
        initializeThemeToggles();
    }
})();
