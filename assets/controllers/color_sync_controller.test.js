// ABOUTME: Vitest + jsdom unit test for color_sync_controller, driven through a real Stimulus
// ABOUTME: Application so the category->color sync is verified via the DOM, not private methods.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ColorSyncController from './color_sync_controller.js';

let application;

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

const checkedColor = () => {
    /** @type {HTMLInputElement | null} */
    const checked = document.querySelector('input[name="color"]:checked');
    return checked?.value;
};

const selectCategory = (value) => {
    /** @type {HTMLSelectElement} */
    const select = document.querySelector('[data-color-sync-target="category"]');
    select.value = value;
    select.dispatchEvent(new Event('change'));
};

const userPicksSwatch = (value) => {
    /** @type {HTMLInputElement} */
    const swatch = document.querySelector(`input[name="color"][value="${value}"]`);
    swatch.checked = true;
    swatch.dispatchEvent(new Event('change'));
};

beforeEach(() => {
    document.body.innerHTML = `
        <form data-controller="color-sync">
            <select data-color-sync-target="category" data-action="color-sync#categoryChanged">
                <option value="">Uncategorized</option>
                <option value="cat-apple" data-color="blue">Apple</option>
                <option value="cat-spotify" data-color="green">Spotify</option>
            </select>
            <input type="radio" name="color" value="red" checked
                   data-color-sync-target="swatch" data-action="change->color-sync#userPicked">
            <input type="radio" name="color" value="blue"
                   data-color-sync-target="swatch" data-action="change->color-sync#userPicked">
            <input type="radio" name="color" value="green"
                   data-color-sync-target="swatch" data-action="change->color-sync#userPicked">
        </form>
    `;
    application = Application.start();
    application.register('color-sync', ColorSyncController);
});

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
});

describe('color_sync_controller', () => {
    it('leaves the random default selected until a category is chosen', async () => {
        await tick();

        expect(checkedColor()).toBe('red');
    });

    it('sets the color to the chosen category color', async () => {
        await tick();

        selectCategory('cat-apple');

        expect(checkedColor()).toBe('blue');
    });

    it('updates the color again when the category changes', async () => {
        await tick();

        selectCategory('cat-apple');
        expect(checkedColor()).toBe('blue');

        selectCategory('cat-spotify');
        expect(checkedColor()).toBe('green');
    });

    it('leaves the color untouched when Uncategorized is selected', async () => {
        await tick();

        selectCategory('cat-apple');
        expect(checkedColor()).toBe('blue');

        selectCategory('');

        expect(checkedColor()).toBe('blue');
    });

    it('stops syncing once the user picks a swatch themselves', async () => {
        await tick();

        userPicksSwatch('green');
        expect(checkedColor()).toBe('green');

        // After the user has taken control, category changes no longer move the color.
        selectCategory('cat-apple');

        expect(checkedColor()).toBe('green');
    });
});
