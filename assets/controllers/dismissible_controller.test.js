// ABOUTME: Vitest + jsdom unit test for dismissible_controller, driven through a real Stimulus
// ABOUTME: Application so the element removal is verified via the DOM, not by calling private methods.

import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it } from 'vitest';
import DismissibleController from './dismissible_controller.js';

let application;

// Stimulus connects controllers asynchronously via a MutationObserver, so give the queue a tick.
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

const setup = () => {
    document.body.innerHTML = `
        <div data-controller="dismissible" data-test="flash">
            <span>Saved</span>
            <button type="button" data-action="dismissible#dismiss" data-test="dismiss">x</button>
        </div>
    `;
    application = Application.start();
    application.register('dismissible', DismissibleController);
};

/** @returns {HTMLElement | null} */
const flash = () => document.querySelector('[data-test="flash"]');
/** @returns {HTMLButtonElement} */
const button = () => document.querySelector('[data-test="dismiss"]');

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
});

describe('dismissible_controller', () => {
    it('removes its element when the dismiss control is clicked', async () => {
        setup();
        await tick();
        expect(flash()).not.toBeNull();

        button().click();

        expect(flash()).toBeNull();
    });

    it('leaves the element in place until the control is activated', async () => {
        setup();
        await tick();

        expect(flash()).not.toBeNull();
    });
});
