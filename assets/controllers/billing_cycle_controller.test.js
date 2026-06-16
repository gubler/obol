// ABOUTME: Vitest + jsdom unit test for billing_cycle_controller, driven through a real Stimulus
// ABOUTME: Application so the period pluralization is verified via the DOM, not private methods.

import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import BillingCycleController from './billing_cycle_controller.js';

let application;

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

const optionText = (value) => document.querySelector(`option[value="${value}"]`).textContent;

beforeEach(() => {
    document.body.innerHTML = `
        <div data-controller="billing-cycle">
            <input type="number"
                   value="1"
                   data-billing-cycle-target="count"
                   data-action="input->billing-cycle#update">
            <select data-billing-cycle-target="period">
                <option value="year" data-singular="Year">Year</option>
                <option value="month" data-singular="Month">Month</option>
                <option value="week" data-singular="Week">Week</option>
            </select>
        </div>
    `;
    application = Application.start();
    application.register('billing-cycle', BillingCycleController);
});

afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
});

describe('billing_cycle_controller', () => {
    it('shows singular period nouns when the count is one', async () => {
        await tick();

        expect(optionText('year')).toBe('Year');
        expect(optionText('month')).toBe('Month');
    });

    it('pluralizes the period nouns when the count is greater than one', async () => {
        await tick();
        /** @type {HTMLInputElement} */
        const count = document.querySelector('[data-billing-cycle-target="count"]');

        count.value = '3';
        count.dispatchEvent(new Event('input'));

        expect(optionText('year')).toBe('Years');
        expect(optionText('week')).toBe('Weeks');
    });

    it('returns to singular nouns when the count goes back to one', async () => {
        await tick();
        /** @type {HTMLInputElement} */
        const count = document.querySelector('[data-billing-cycle-target="count"]');

        count.value = '5';
        count.dispatchEvent(new Event('input'));
        expect(optionText('month')).toBe('Months');

        count.value = '1';
        count.dispatchEvent(new Event('input'));

        expect(optionText('month')).toBe('Month');
    });
});
