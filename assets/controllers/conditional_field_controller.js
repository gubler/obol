// ABOUTME: Stimulus controller that reveals a dependent field only while a trigger checkbox is checked.
// ABOUTME: Progressive enhancement - with JS off the field stays visible (used for restart-payments).

import { Controller } from '@hotwired/stimulus';

/*
 * Wiring (see templates/payment/new.html.twig):
 *   data-controller="conditional-field"
 *   checkbox: data-conditional-field-target="trigger" data-action="conditional-field#toggle"
 *   field:    data-conditional-field-target="field"
 */
export default class extends Controller {
    static targets = ['trigger', 'field'];

    connect() {
        this.update();
    }

    toggle() {
        this.update();
    }

    update() {
        this.fieldTarget.hidden = !this.triggerTarget.checked;
    }
}
