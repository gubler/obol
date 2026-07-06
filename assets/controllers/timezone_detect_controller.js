// ABOUTME: Pre-selects the onboarding timezone dropdown with the browser's detected zone (Intl).
// ABOUTME: Only adopts the detected zone when it is one of the options the server rendered; user-editable.

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['field'];

    connect() {
        if (!this.hasFieldTarget) {
            return;
        }

        const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!zone) {
            return;
        }

        const isOffered = Array.from(this.fieldTarget.options).some(
            (option) => option.value === zone,
        );
        if (isOffered) {
            this.fieldTarget.value = zone;
        }
    }
}
