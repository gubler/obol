// ABOUTME: Stimulus controller that formats the obligation-trend line chart's y-axis and tooltip as money.
// ABOUTME: Wraps render_chart(); catches the bubbling chartjs:pre-connect to set axis/tooltip callbacks before draw.

import { Controller } from '@hotwired/stimulus';

/*
 * Wiring (see templates/reports/_line.html.twig):
 *   <div data-controller="obligation-trend">{{ render_chart(trendChart) }}</div>
 *
 * The dataset (from ObligationTrendChartFactory) carries:
 *   data            - obligation per bucket in the display currency's minor units
 *   displayAmounts  - the same values pre-formatted server-side (e.g. "$80.00"), used verbatim in the tooltip
 *   currencySymbol  - display-currency symbol, for formatting the y-axis ticks
 *   fractionDigits  - display-currency fraction digits
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
        const dataset = config.data.datasets[0];
        const symbol = dataset.currencySymbol ?? '';
        const digits = dataset.fractionDigits ?? 2;
        const formatMoney = (minor) =>
            `${symbol}${(minor / 10 ** digits).toLocaleString(undefined, {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits,
            })}`;

        config.options = config.options || {};
        config.options.scales = config.options.scales || {};
        config.options.scales.y = config.options.scales.y || {};
        config.options.scales.y.ticks = config.options.scales.y.ticks || {};
        config.options.scales.y.ticks.callback = (value) => formatMoney(value);

        config.options.plugins = config.options.plugins || {};
        config.options.plugins.tooltip = config.options.plugins.tooltip || {};
        config.options.plugins.tooltip.callbacks = {
            label(context) {
                return ` ${context.dataset.displayAmounts?.[context.dataIndex] ?? formatMoney(context.parsed.y)}`;
            },
        };
    }
}
