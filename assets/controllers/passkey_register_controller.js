import { Controller } from '@hotwired/stimulus';
import { startRegistration, browserSupportsWebAuthn, WebAuthnError } from '@simplewebauthn/browser';

/* stimulusFetch: 'lazy' */

/*
 * "Passkey hinzufügen" in "Mein Konto" (umsetzung-admin.md B5b). Three steps, the first and last at the WebAuthn
 * bundle's endpoints (config/packages/webauthn.yaml, controllers.creation.account):
 * 1. ask the server for the options (challenge, account handle, passkeys that already exist),
 * 2. let the device create the key pair (fingerprint or PIN; @simplewebauthn/browser converts to and from JSON),
 * 3. send the result with the chosen name; the server checks it and saves the passkey
 *    (App\Security\PasskeyCreatedHandler sets the name). Then the page reloads with the new passkey in the list.
 */
export default class extends Controller {
    static targets = ['name', 'error', 'button'];
    static values = { optionsUrl: String, resultUrl: String, messages: Object };

    connect() {
        if (!browserSupportsWebAuthn()) {
            this.buttonTarget.disabled = true;
            this.showError(this.messagesValue.unsupported);
        }
    }

    async register(event) {
        event.preventDefault();
        if (!this.element.reportValidity()) {
            return;
        }
        this.buttonTarget.disabled = true;
        this.showError('');

        try {
            const options = await this.post(this.optionsUrlValue, {});
            const credential = await startRegistration({ optionsJSON: options });
            await this.post(this.resultUrlValue, { ...credential, passkeyName: this.nameTarget.value });
            window.location.reload();
        } catch (error) {
            // NotAllowedError: cancelled or timed out in the device dialog; InvalidStateError: this device has one
            const key = error instanceof WebAuthnError && error.code === 'ERROR_AUTHENTICATOR_PREVIOUSLY_REGISTERED' ? 'exists'
                : error?.name === 'NotAllowedError' ? 'cancelled' : 'failed';
            this.showError(this.messagesValue[key]);
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
