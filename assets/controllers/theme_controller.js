// ABOUTME: Stimulus controller for the light/dark theme toggle in the nav.
// ABOUTME: Flips the `dark` class on <html>, persists the choice to localStorage, and reflects state in aria-pressed.

import { Controller } from '@hotwired/stimulus';

/*
 * Resolve the active theme. An explicit stored choice wins; otherwise follow the OS,
 * defaulting to dark unless the OS explicitly prefers light. This mirrors the inline
 * no-flash script in base.html.twig; keep the two in sync.
 *
 * Wiring (see templates/base.html.twig):
 *   button: data-controller="theme" data-action="theme#toggle"
 */
export function resolveTheme(storage, prefersLight) {
    const stored = storage.theme;
    if (stored === 'dark' || stored === 'light') {
        return stored;
    }

    return prefersLight ? 'light' : 'dark';
}

export default class extends Controller {
    connect() {
        this.render();
    }

    toggle() {
        const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        localStorage.setItem('theme', next);
        document.documentElement.classList.toggle('dark', next === 'dark');
        this.render();
    }

    render() {
        const isDark = document.documentElement.classList.contains('dark');
        this.element.setAttribute('aria-pressed', String(isDark));
    }
}
