// ABOUTME: Vitest + jsdom test for timezone_detect_controller, exercised through a real Stimulus Application.
// ABOUTME: Stubs Intl to assert the detected browser zone pre-selects the matching option, and only then.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TimezoneDetectController from './timezone_detect_controller.js';

let application;

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

const stubDetectedZone = (zone) => {
    vi.spyOn(Intl, 'DateTimeFormat').mockReturnValue(
        /** @type {Intl.DateTimeFormat} */ ({ resolvedOptions: () => ({ timeZone: zone }) }),
    );
};

const mount = () => {
    document.body.innerHTML = `
        <form data-controller="timezone-detect">
            <select data-timezone-detect-target="field">
                <option value="America/New_York" selected>America/New_York</option>
                <option value="Europe/London">Europe/London</option>
            </select>
        </form>
    `;
    application = Application.start();
    application.register('timezone-detect', TimezoneDetectController);
};

beforeEach(() => {
    vi.restoreAllMocks();
});

afterEach(() => {
    if (application) {
        application.stop();
    }
    document.body.innerHTML = '';
});

describe('timezone_detect_controller', () => {
    it('pre-selects the option matching the detected browser zone', async () => {
        stubDetectedZone('Europe/London');
        mount();
        await tick();

        expect(document.querySelector('select').value).toBe('Europe/London');
    });

    it('leaves the server default when the detected zone is not an offered option', async () => {
        stubDetectedZone('Antarctica/Troll');
        mount();
        await tick();

        expect(document.querySelector('select').value).toBe('America/New_York');
    });
});
