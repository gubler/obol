// ABOUTME: Starts the driver.js product tour on demand (the footer link) or automatically on the tour page.
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
    { key: 'tile', selector: '[data-tour="tile"]' },
    { key: 'categories', selector: '[data-tour="categories"]' },
    { key: 'payment_sources', selector: '[data-tour="payment_sources"]' },
    { key: 'reports', selector: '[data-tour="reports"]' },
    { key: 'account', selector: '[data-tour="account"]' },
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
        // The dedicated tour page sets these: autostart drives the walkthrough on load, and returnUrl is
        // where the user lands once the tour ends (leaving the non-persisted sample behind).
        autostart: Boolean,
        returnUrl: String,
    };

    connect() {
        if (this.autostartValue) {
            this.start();
        }
    }

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
            // Autostarted onto a page with nothing to highlight: don't strand the user - send them home.
            if (this.returnUrlValue) {
                this.navigateHome(this.returnUrlValue);
            }
            return;
        }

        const config = { showProgress: true, steps };
        if (this.returnUrlValue) {
            // driver.js fires onDestroyed when the tour finishes or is dismissed; either way, leave.
            config.onDestroyed = () => this.navigateHome(this.returnUrlValue);
        }

        driver(config).drive();
    }

    navigateHome(url) {
        window.location.assign(url);
    }
}
