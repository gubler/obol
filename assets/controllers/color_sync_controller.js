// ABOUTME: Stimulus controller that syncs the new-subscription color swatch to the chosen category's
// ABOUTME: color, until the user picks a swatch themselves. New form only; pure progressive enhancement.

import { Controller } from '@hotwired/stimulus';

/*
 * Wiring (see CreateSubscriptionFormType + templates/form/_subscription_form_theme.html.twig):
 *   form:     data-controller="color-sync"
 *   category: <select> data-color-sync-target="category" data-action="color-sync#categoryChanged"
 *             each <option> carries data-color="<TileColor value>" (the placeholder has none)
 *   swatches: <input type="radio"> data-color-sync-target="swatch"
 *             data-action="change->color-sync#userPicked"
 *
 * With JS off the form keeps its random default color and no syncing happens.
 */
export default class extends Controller {
    static targets = ['category', 'swatch'];

    detached = false;

    categoryChanged() {
        // Once the user has taken control of the color, stay out of their way.
        if (this.detached) {
            return;
        }

        const option = this.categoryTarget.selectedOptions[0];
        const color = option?.dataset.color;

        // Uncategorized (the placeholder) carries no color: leave the current swatch untouched.
        if (!color) {
            return;
        }

        // Checking one radio unchecks the rest of the group; the swatches restyle off :checked.
        for (const swatch of this.swatchTargets) {
            if (swatch.value === color) {
                swatch.checked = true;
            }
        }
    }

    userPicked() {
        this.detached = true;
    }
}
