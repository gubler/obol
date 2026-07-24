// ABOUTME: Stimulus controller that removes its element when a dismiss control is activated.
// ABOUTME: Wired from the shared flash template (templates/_flashes.html.twig) so a flash can be closed.

import { Controller } from '@hotwired/stimulus';

/*
 * Wiring (see templates/_flashes.html.twig):
 *   wrapper:  data-controller="dismissible"
 *   button:   data-action="dismissible#dismiss"
 *
 * Progressive enhancement - with JS off the button does nothing and the flash simply stays visible;
 * it carries no critical action, so leaving it on screen is a fine fallback.
 */
export default class extends Controller {
    dismiss() {
        this.element.remove();
    }
}
