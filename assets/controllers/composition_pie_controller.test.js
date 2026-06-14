// ABOUTME: Vitest + jsdom unit test for composition_pie_controller, driven through a real Stimulus
// ABOUTME: Application by dispatching a synthetic chartjs:pre-connect and asserting the tooltip callbacks.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import CompositionPieController from './composition_pie_controller.js';

let application;

// Stimulus connects controllers asynchronously via a MutationObserver, so give the
// microtask/animation-frame queue a tick to flush before firing the event.
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

// ux-chartjs dispatches `chartjs:pre-connect` carrying the Chart.js config; the controller
// listens on its root element and mutates that config in place. Simulate it and hand back the
// (now-mutated) config so tests can assert on, and invoke, the installed callbacks.
/** @param {any} config */
const preConnect = (config = {}) => {
    const element = document.querySelector('[data-controller="composition-pie"]');
    element.dispatchEvent(new CustomEvent('chartjs:pre-connect', { detail: { config } }));

    return config;
};

beforeEach(() => {
    document.body.innerHTML = '<div data-controller="composition-pie"></div>';
    application = Application.start();
    application.register('composition-pie', CompositionPieController);
});

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
});

describe('composition_pie_controller', () => {
    it('installs tooltip label and afterLabel callbacks on pre-connect', async () => {
        await tick();
        const { callbacks } = preConnect().options.plugins.tooltip;

        expect(typeof callbacks.label).toBe('function');
        expect(typeof callbacks.afterLabel).toBe('function');
    });

    it('labels a slice with its display amount and rounded percent of the total', async () => {
        await tick();
        const { label } = preConnect().options.plugins.tooltip.callbacks;

        const text = label({
            dataset: { displayAmounts: ['$40.00', '$15.00'], data: [40, 15] },
            dataIndex: 0,
            label: 'Streaming',
            formattedValue: '40',
        });

        expect(text).toBe(' Streaming: $40.00 (73%)');
    });

    it('falls back to the raw formattedValue when the slice has no display amount', async () => {
        await tick();
        const { label } = preConnect().options.plugins.tooltip.callbacks;

        const text = label({
            dataset: { data: [40, 15] },
            dataIndex: 1,
            label: 'Music',
            formattedValue: '15',
        });

        expect(text).toBe(' Music: 15 (27%)');
    });

    it('reports 0% rather than dividing by zero when the total is zero', async () => {
        await tick();
        const { label } = preConnect().options.plugins.tooltip.callbacks;

        const text = label({
            dataset: { displayAmounts: ['$0.00', '$0.00'], data: [0, 0] },
            dataIndex: 0,
            label: 'Empty',
            formattedValue: '0',
        });

        expect(text).toBe(' Empty: $0.00 (0%)');
    });

    it('renders the native-currency breakdown as indented afterLabel lines', async () => {
        await tick();
        const { afterLabel } = preConnect().options.plugins.tooltip.callbacks;

        const lines = afterLabel({
            dataset: { nativeBreakdown: [['€10.00', '£5.00'], []] },
            dataIndex: 0,
        });

        expect(lines).toEqual(['  €10.00', '  £5.00']);
    });

    it('returns no afterLabel lines when the slice has no native breakdown', async () => {
        await tick();
        const { afterLabel } = preConnect().options.plugins.tooltip.callbacks;

        expect(afterLabel({ dataset: {}, dataIndex: 0 })).toEqual([]);
        expect(afterLabel({ dataset: { nativeBreakdown: [[]] }, dataIndex: 0 })).toEqual([]);
    });
});
