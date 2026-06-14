// ABOUTME: Vitest + jsdom unit test for obligation_trend_controller, driven through a real Stimulus
// ABOUTME: Application by dispatching a synthetic chartjs:pre-connect and asserting the axis/tooltip callbacks.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import ObligationTrendController from './obligation_trend_controller.js';

let application;

// Stimulus connects controllers asynchronously via a MutationObserver, so give the
// microtask/animation-frame queue a tick to flush before firing the event.
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

// ux-chartjs dispatches `chartjs:pre-connect` carrying the Chart.js config; the controller listens
// on its root element and mutates that config in place. Simulate it (with a dataset, which this
// controller reads) and hand back the mutated config so tests can invoke the installed callbacks.
// Money formatting goes through Intl (`toLocaleString`), so these assertions assume an en-US runtime
// locale - the same default Node/Vitest and the CI container run under.
/** @param {any} dataset */
const preConnect = (dataset = {}) => {
    const config = { data: { datasets: [dataset] } };
    const element = document.querySelector('[data-controller="obligation-trend"]');
    element.dispatchEvent(new CustomEvent('chartjs:pre-connect', { detail: { config } }));

    return config;
};

beforeEach(() => {
    document.body.innerHTML = '<div data-controller="obligation-trend"></div>';
    application = Application.start();
    application.register('obligation-trend', ObligationTrendController);
});

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
});

describe('obligation_trend_controller', () => {
    it('installs a y-axis tick formatter and a tooltip label callback on pre-connect', async () => {
        await tick();
        const { options } = preConnect({ currencySymbol: '$', fractionDigits: 2 });

        expect(typeof options.scales.y.ticks.callback).toBe('function');
        expect(typeof options.plugins.tooltip.callbacks.label).toBe('function');
    });

    it('formats y-axis ticks as money using the dataset symbol and fraction digits', async () => {
        await tick();
        const { callback } = preConnect({ currencySymbol: '$', fractionDigits: 2 }).options.scales.y
            .ticks;

        // Values are in the display currency's minor units (cents).
        expect(callback(8000)).toBe('$80.00');
        expect(callback(0)).toBe('$0.00');
    });

    it('uses the server-formatted displayAmounts for the tooltip when present', async () => {
        await tick();
        const { label } = preConnect({ displayAmounts: ['$80.00'] }).options.plugins.tooltip
            .callbacks;

        expect(
            label({ dataset: { displayAmounts: ['$80.00'] }, dataIndex: 0, parsed: { y: 8000 } }),
        ).toBe(' $80.00');
    });

    it('falls back to formatting parsed.y when the bucket has no displayAmounts', async () => {
        await tick();
        const { label } = preConnect({ currencySymbol: '$', fractionDigits: 2 }).options.plugins
            .tooltip.callbacks;

        expect(label({ dataset: {}, dataIndex: 0, parsed: { y: 8000 } })).toBe(' $80.00');
    });

    it('defaults to no symbol and two fraction digits when the dataset omits them', async () => {
        await tick();
        const { callback } = preConnect().options.scales.y.ticks;

        expect(callback(1234)).toBe('12.34');
    });
});
