// ABOUTME: Vitest + jsdom unit test for theme_controller, exercised through a real Stimulus
// ABOUTME: Application so the toggle is verified via the <html> class and localStorage, not private methods.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ThemeController, { resolveTheme } from './theme_controller.js';

let application;

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

// jsdom here does not provide localStorage; give the controller a minimal in-memory store.
if (typeof globalThis.localStorage === 'undefined') {
    let store = {};
    globalThis.localStorage = {
        getItem: (key) => (key in store ? store[key] : null),
        setItem: (key, value) => {
            store[key] = String(value);
        },
        removeItem: (key) => {
            delete store[key];
        },
        clear: () => {
            store = {};
        },
        key: () => null,
        length: 0,
    };
}

beforeEach(() => {
    document.documentElement.classList.remove('dark');
    localStorage.clear();
    document.body.innerHTML = `
        <button data-controller="theme" data-action="theme#toggle" aria-pressed="false"></button>
    `;
    application = Application.start();
    application.register('theme', ThemeController);
});

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    document.documentElement.classList.remove('dark');
    localStorage.clear();
});

describe('resolveTheme', () => {
    it('honors an explicit stored choice over the system preference', () => {
        expect(resolveTheme({ theme: 'light' }, false)).toBe('light');
        expect(resolveTheme({ theme: 'dark' }, true)).toBe('dark');
    });

    it('follows the system preference when nothing is stored', () => {
        expect(resolveTheme({}, true)).toBe('light');
    });

    it('defaults to dark when nothing is stored and the system does not prefer light', () => {
        expect(resolveTheme({}, false)).toBe('dark');
    });
});

describe('theme_controller', () => {
    it('reflects the current scheme in aria-pressed on connect', async () => {
        document.documentElement.classList.add('dark');
        document.body.innerHTML =
            '<button data-controller="theme" data-action="theme#toggle"></button>';
        await tick();
        const button = document.querySelector('button');

        expect(button.getAttribute('aria-pressed')).toBe('true');
    });

    it('toggles to dark: adds the class, persists the choice, updates aria-pressed', async () => {
        await tick();
        const button = document.querySelector('button');

        button.click();
        await tick();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('theme')).toBe('dark');
        expect(button.getAttribute('aria-pressed')).toBe('true');
    });

    it('toggles back to light: removes the class and persists light', async () => {
        document.documentElement.classList.add('dark');
        await tick();
        const button = document.querySelector('button');

        button.click();
        await tick();

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('theme')).toBe('light');
        expect(button.getAttribute('aria-pressed')).toBe('false');
    });
});
