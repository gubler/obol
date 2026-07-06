// ABOUTME: Vitest + jsdom test for tour_controller, exercised through a real Stimulus Application.
// ABOUTME: driver.js is mocked so we assert the steps we build and that the tour is driven, not driver's UI.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { driver, drive } = vi.hoisted(() => {
    const drive = vi.fn();
    return { drive, driver: vi.fn(() => ({ drive })) };
});

vi.mock('driver.js', () => ({ driver }));
vi.mock('driver.js/dist/driver.css', () => ({}));

import TourController, { buildSteps } from './tour_controller.js';

let application;

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    if (application) {
        application.stop();
    }
    document.body.innerHTML = '';
});

describe('buildSteps', () => {
    it('maps selectors to driver steps using the translated labels, skipping keys with no label', () => {
        const labels = {
            subscriptions: { title: 'Your subscriptions', body: 'Everything you pay for.' },
            add: { title: 'Add one', body: 'Track a new subscription.' },
        };

        const steps = buildSteps(labels);

        // Only the two keys present in labels are included, in canonical order.
        expect(steps).toHaveLength(2);
        expect(steps[0]).toEqual({
            element: '[data-tour="subscriptions"]',
            popover: { title: 'Your subscriptions', description: 'Everything you pay for.' },
        });
        expect(steps[1].element).toBe('[data-tour="add"]');
    });
});

describe('tour_controller', () => {
    const mount = (labels) => {
        document.body.innerHTML = `
            <div data-controller="tour" data-tour-labels-value='${JSON.stringify(labels)}'>
                <a data-tour="subscriptions">Subscriptions</a>
                <button data-action="tour#start">Take the tour</button>
            </div>
        `;
        application = Application.start();
        application.register('tour', TourController);
    };

    it('drives the tour over the steps whose target is present when started', async () => {
        mount({ subscriptions: { title: 'Subs', body: 'body' } });
        await tick();

        document.querySelector('button').click();
        await tick();

        // Only the step whose target is on the page is passed to driver, and the tour is driven.
        expect(driver).toHaveBeenCalledWith(
            expect.objectContaining({
                steps: [expect.objectContaining({ element: '[data-tour="subscriptions"]' })],
            }),
        );
        expect(drive).toHaveBeenCalledOnce();
    });

    it('does not start the tour when none of the step targets are on the page', async () => {
        // A label whose element is absent from this page must not produce a stranded step.
        mount({ reports: { title: 'Reports', body: 'body' } });
        await tick();

        document.querySelector('button').click();
        await tick();

        expect(driver).not.toHaveBeenCalled();
    });
});
