// ABOUTME: Stimulus controller for the inline "Every N period" cycle control on the subscription form.
// ABOUTME: Pluralizes the period dropdown's nouns against the count (Every 1 Month / Every 3 Months).

import { Controller } from '@hotwired/stimulus';

/*
 * Wiring (see templates/subscription/_form.html.twig):
 *   data-controller="billing-cycle"
 *   count:  <input> data-billing-cycle-target="count" data-action="input->billing-cycle#update"
 *   period: <select> data-billing-cycle-target="period" with <option data-singular="Year">
 *
 * Progressive enhancement - with JS off the options keep their singular nouns.
 */
export default class extends Controller {
    static targets = ['count', 'period'];

    connect() {
        this.update();
    }

    update() {
        const plural = Number.parseInt(this.countTarget.value, 10) !== 1;

        for (const option of this.periodTarget.options) {
            const singular = option.dataset.singular;
            if (singular) {
                option.textContent = plural ? `${singular}s` : singular;
            }
        }
    }
}
