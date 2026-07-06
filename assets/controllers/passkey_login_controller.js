// ABOUTME: Drives the WebAuthn assertion ceremony on /login.
// ABOUTME: Calls the bundle's options + result endpoints; the magic-link form stays the no-JS fallback.

import { Controller } from '@hotwired/stimulus';
import { startAuthentication } from '@simplewebauthn/browser';

export default class extends Controller {
    static values = {
        optionsUrl: String,
        resultUrl: String,
        errorMessage: String,
    };

    static targets = ['button', 'status'];

    connect() {
        if (this.hasButtonTarget) {
            this.buttonTarget.classList.remove('hidden');
            this.buttonTarget.disabled = false;
        }

        // Conditional UI: if the browser supports passkey autofill, trigger the assertion ceremony
        // silently so the email field's autofill dropdown can offer registered credentials. Any failure
        // is swallowed - the magic-link form remains the working fallback.
        this.tryConditionalMediation();
    }

    async tryConditionalMediation() {
        try {
            const available = await PublicKeyCredential?.isConditionalMediationAvailable?.();
            if (!available) {
                return;
            }
            const options = await this.fetchOptions();
            const credential = await startAuthentication({
                optionsJSON: options,
                useBrowserAutofill: true,
            });
            await this.submitResult(credential);
        } catch (_error) {
            // Silent fallback to the magic-link form.
        }
    }

    async authenticate(event) {
        event.preventDefault();

        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = true;
        }
        this.setStatus('');

        try {
            const options = await this.fetchOptions();
            const credential = await startAuthentication({ optionsJSON: options });
            await this.submitResult(credential);
        } catch (error) {
            this.handleError(error);
            if (this.hasButtonTarget) {
                this.buttonTarget.disabled = false;
            }
        }
    }

    async fetchOptions() {
        const response = await fetch(this.optionsUrlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: '{}',
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

        const body = await response.json();
        if (body?.redirect) {
            window.location.href = body.redirect;
            return;
        }

        window.location.reload();
    }

    handleError(_error) {
        this.setStatus(
            this.errorMessageValue || 'Something went wrong. Try again or use a magic link.',
        );
    }

    setStatus(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
            this.statusTarget.hidden = !text;
        }
    }
}
