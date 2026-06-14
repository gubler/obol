// ABOUTME: Vitest + jsdom unit test for conditional_field_controller, exercised through a real
// ABOUTME: Stimulus Application so the show/hide is verified via the DOM, not private methods.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ConditionalFieldController from './conditional_field_controller.js';

let application;

// Stimulus connects controllers asynchronously via a MutationObserver, so give the
// microtask/animation-frame queue a tick to flush after mutating the DOM.
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

beforeEach(() => {
    document.body.innerHTML = `
        <div data-controller="conditional-field">
            <input type="checkbox"
                   data-conditional-field-target="trigger"
                   data-action="conditional-field#toggle">
            <div data-conditional-field-target="field">Dependent field</div>
        </div>
    `;
    application = Application.start();
    application.register('conditional-field', ConditionalFieldController);
});

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
});

describe('conditional_field_controller', () => {
    it('hides the dependent field on connect while the trigger is unchecked', async () => {
        await tick();
        /** @type {HTMLElement} */
        const field = document.querySelector('[data-conditional-field-target="field"]');

        expect(field.hidden).toBe(true);
    });

    it('reveals the field when the trigger is checked', async () => {
        await tick();
        /** @type {HTMLInputElement} */
        const trigger = document.querySelector('[data-conditional-field-target="trigger"]');
        /** @type {HTMLElement} */
        const field = document.querySelector('[data-conditional-field-target="field"]');

        trigger.click();

        expect(trigger.checked).toBe(true);
        expect(field.hidden).toBe(false);
    });

    it('hides the field again when the trigger is unchecked', async () => {
        await tick();
        /** @type {HTMLInputElement} */
        const trigger = document.querySelector('[data-conditional-field-target="trigger"]');
        /** @type {HTMLElement} */
        const field = document.querySelector('[data-conditional-field-target="field"]');

        trigger.click();
        expect(field.hidden).toBe(false);

        trigger.click();

        expect(trigger.checked).toBe(false);
        expect(field.hidden).toBe(true);
    });
});
