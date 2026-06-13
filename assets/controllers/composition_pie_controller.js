// ABOUTME: Stimulus controller that enriches a ux-chartjs pie tooltip with the display amount and native split.
// ABOUTME: Wraps render_chart(); catches the bubbling chartjs:pre-connect to inject tooltip callbacks before draw.

import { Controller } from '@hotwired/stimulus';

/*
 * Wiring (see templates/reports/_pie.html.twig):
 *   <div data-controller="composition-pie">{{ render_chart(chart) }}</div>
 *
 * The dataset carries two custom arrays from CompositionChartFactory:
 *   displayAmounts  - formatted display-currency amount per slice (e.g. "$40.00")
 *   nativeBreakdown - formatted native lines per slice, empty unless the slice was converted
 */
export default class extends Controller {
    connect() {
        this._onPreConnect = this._onPreConnect.bind(this);
        this.element.addEventListener('chartjs:pre-connect', this._onPreConnect);
    }

    disconnect() {
        this.element.removeEventListener('chartjs:pre-connect', this._onPreConnect);
    }

    _onPreConnect(event) {
        const config = event.detail.config;
        config.options = config.options || {};
        config.options.plugins = config.options.plugins || {};
        config.options.plugins.tooltip = config.options.plugins.tooltip || {};
        config.options.plugins.tooltip.callbacks = {
            label(context) {
                const { dataset, dataIndex, label, formattedValue } = context;
                const amount = dataset.displayAmounts?.[dataIndex] ?? formattedValue;
                const total = dataset.data.reduce((sum, value) => sum + value, 0);
                const percent = total > 0 ? Math.round((dataset.data[dataIndex] / total) * 100) : 0;

                return ` ${label}: ${amount} (${percent}%)`;
            },
            afterLabel(context) {
                const native = context.dataset.nativeBreakdown?.[context.dataIndex] ?? [];

                return native.map((line) => `  ${line}`);
            },
        };
    }
}
