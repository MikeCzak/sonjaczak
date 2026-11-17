import { Controller } from '@hotwired/stimulus';
import { startAuthentication, browserSupportsWebAuthn } from '@simplewebauthn/browser';

/* stimulusFetch: 'lazy' */

/*
 * "Mit Passkey anmelden" on the login page (umsetzung-admin.md B5c). Like adding a passkey, in three steps at the
 * endpoints of the admin firewall (config/packages/security.yaml, webauthn):
 * 1. ask for the options (a fresh challenge; no e-mail address, the device offers the passkeys it holds),
 * 2. let the device sign the challenge (fingerprint or PIN),
 * 3. send the signature; the server checks it, logs in and answers with the page to go to
 *    (App\Security\PasskeyLoginSuccessHandler).
 */
export default class extends Controller {
    static targets = ['error', 'button'];
    static values = { optionsUrl: String, resultUrl: String, messages: Object };

    connect() {
        if (!browserSupportsWebAuthn()) {
            this.buttonTarget.disabled = true;
            this.showError(this.messagesValue.unsupported);
        }
    }

    async login() {
        this.buttonTarget.disabled = true;
        this.showError('');

        try {
            const options = await this.post(this.optionsUrlValue, {});
            const credential = await startAuthentication({ optionsJSON: options });
            const result = await this.post(this.resultUrlValue, credential);
            window.location.assign(result.redirect);
        } catch (error) {
            this.showError(this.messagesValue[error?.name === 'NotAllowedError' ? 'cancelled' : 'failed']);
            this.buttonTarget.disabled = false;
        }
    }

    async post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = '' === message;
    }
}
