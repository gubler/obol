// ABOUTME: Vitest + jsdom unit test for past_date_warning_controller, driven through a real Stimulus
// ABOUTME: Application so the show/hide of the message is verified via the DOM, not private methods.

import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it } from 'vitest';
import PastDateWarningController from './past_date_warning_controller.js';

let application;

// Stimulus connects controllers asynchronously via a MutationObserver, so give the queue a tick.
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

// Dates far in the past/future so the outcome does not depend on the day the suite runs.
const FAR_PAST = '2000-01-01';
const FAR_FUTURE = '2100-01-01';

const setup = (value) => {
    document.body.innerHTML = `
        <div data-controller="past-date-warning">
            <input type="date" value="${value}"
                   data-past-date-warning-target="input"
                   data-action="input->past-date-warning#check">
            <p data-past-date-warning-target="message" hidden>past date warning</p>
        </div>
    `;
    application = Application.start();
    application.register('past-date-warning', PastDateWarningController);
};

/** @returns {HTMLElement} */
const message = () => document.querySelector('[data-past-date-warning-target="message"]');
/** @returns {HTMLInputElement} */
const input = () => document.querySelector('[data-past-date-warning-target="input"]');

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
});

describe('past_date_warning_controller', () => {
    it('shows the warning on connect when the date is already in the past', async () => {
        setup(FAR_PAST);
        await tick();

        expect(message().hidden).toBe(false);
    });

    it('keeps the warning hidden for a future date', async () => {
        setup(FAR_FUTURE);
        await tick();

        expect(message().hidden).toBe(true);
    });

    it('reveals the warning when the field is edited to a past date', async () => {
        setup(FAR_FUTURE);
        await tick();
        expect(message().hidden).toBe(true);

        input().value = FAR_PAST;
        input().dispatchEvent(new Event('input'));

        expect(message().hidden).toBe(false);
    });

    it('hides the warning again when edited back to a future date', async () => {
        setup(FAR_PAST);
        await tick();
        expect(message().hidden).toBe(false);

        input().value = FAR_FUTURE;
        input().dispatchEvent(new Event('input'));

        expect(message().hidden).toBe(true);
    });

    it('does not warn on an empty or malformed value', async () => {
        setup('');
        await tick();

        expect(message().hidden).toBe(true);
    });
});
