// ABOUTME: Stimulus controller that shows a soft warning when a renewal-date field holds a past date.
// ABOUTME: Informational only - a past date is allowed; it switches the subscription to manual generation.

import { Controller } from '@hotwired/stimulus';

/*
 * Wiring (see templates/subscription/_form.html.twig):
 *   wrapper:  data-controller="past-date-warning"
 *   input:    data-past-date-warning-target="input" data-action="input->past-date-warning#check"
 *   message:  data-past-date-warning-target="message" hidden
 *
 * Progressive enhancement - with JS off the message stays hidden and the server still accepts the date
 * (it just switches generation to manual). The comparison is on calendar days in the browser's local
 * frame, matching how the server judges "past" in the owner's zone.
 */
export default class extends Controller {
    static targets = ['input', 'message'];

    connect() {
        this.check();
    }

    check() {
        this.messageTarget.hidden = !this.isPast(this.inputTarget.value);
    }

    isPast(value) {
        const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (parts === null) {
            return false;
        }

        const picked = new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]));
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        return picked.getTime() < today.getTime();
    }
}
