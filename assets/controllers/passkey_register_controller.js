// ABOUTME: Drives the WebAuthn registration ceremony at /account/passkeys/new.
// ABOUTME: Calls the bundle's options + result endpoints; relays the browser's response back for verification.

import { Controller } from '@hotwired/stimulus';
import { startRegistration } from '@simplewebauthn/browser';

export default class extends Controller {
    static values = {
        optionsUrl: String,
        resultUrl: String,
        successUrl: String,
        excludeCredentials: String,
        unsupportedMessage: String,
        cancelledMessage: String,
        genericErrorMessage: String,
    };

    static targets = ['button', 'status'];

    connect() {
        if (this.hasButtonTarget) {
            // The button starts hidden in the no-JS baseline; reveal it now that JS + Stimulus are alive.
            this.buttonTarget.classList.remove('hidden');
            this.buttonTarget.disabled = false;
        }
    }

    async register(event) {
        event.preventDefault();

        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = true;
        }
        this.setStatus('');

        try {
            const options = await this.fetchOptions();
            const credential = await startRegistration({ optionsJSON: options });
            await this.submitResult(credential);
            window.location.href = this.successUrlValue;
        } catch (error) {
            this.handleError(error);
            if (this.hasButtonTarget) {
                this.buttonTarget.disabled = false;
            }
        }
    }

    async fetchOptions() {
        const exclude = this.excludeCredentialsValue
            ? JSON.parse(this.excludeCredentialsValue)
            : [];

        const response = await fetch(this.optionsUrlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ excludeCredentials: exclude }),
        });

        if (!response.ok) {
            throw new Error(`options-request-failed:${response.status}`);
        }

        return response.json();
    }

    async submitResult(credential) {
        const response = await fetch(this.resultUrlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(credential),
        });

        if (!response.ok) {
            throw new Error(`result-rejected:${response.status}`);
        }
    }

    handleError(error) {
        const name = error?.name ?? '';

        if (name === 'NotAllowedError' || name === 'AbortError') {
            this.setStatus(this.cancelledMessageValue || 'Cancelled.');
            return;
        }

        if (typeof error?.message === 'string' && error.message.includes('not supported')) {
            this.setStatus(
                this.unsupportedMessageValue || 'Your browser does not support passkeys.',
            );
            return;
        }

        this.setStatus(this.genericErrorMessageValue || 'Something went wrong. Try again.');
    }

    setStatus(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
            this.statusTarget.hidden = !text;
        }
    }
}
