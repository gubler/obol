// ABOUTME: Starts the driver.js product tour on demand (the footer link and the post-onboarding offer).
// ABOUTME: Step selectors and order live here; the copy arrives translated via the labels value.

import { Controller } from '@hotwired/stimulus';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

// The tour's shape: which element each step highlights, in order. The copy for each step is supplied
// per-locale through the `labels` value, keyed by these same keys.
const STEP_SELECTORS = [
    { key: 'subscriptions', selector: '[data-tour="subscriptions"]' },
    { key: 'add', selector: '[data-tour="add"]' },
    { key: 'totals', selector: '[data-tour="totals"]' },
    { key: 'categories', selector: '[data-tour="categories"]' },
    { key: 'reports', selector: '[data-tour="reports"]' },
    { key: 'theme', selector: '[data-tour="theme"]' },
];

export function buildSteps(labels) {
    return STEP_SELECTORS.filter((step) => labels[step.key]).map((step) => ({
        element: step.selector,
        popover: {
            title: labels[step.key].title,
            description: labels[step.key].body,
        },
    }));
}

export default class extends Controller {
    static values = {
        labels: Object,
    };

    start(event) {
        if (event) {
            event.preventDefault();
        }

        // Only highlight steps whose target is actually on this page, so the tour degrades gracefully
        // when launched from a page that lacks some elements (e.g. the footer link off the dashboard).
        const steps = buildSteps(this.labelsValue).filter(
            (step) => document.querySelector(step.element) !== null,
        );

        if (steps.length === 0) {
            return;
        }

        driver({ showProgress: true, steps }).drive();
    }
}
