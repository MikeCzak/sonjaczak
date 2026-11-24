import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */

/*
 * Editing page texts where they are shown (templates/_partials/page_editor.html.twig, docs/planung/cms.md K4). Only
 * loaded for logged-in admins; visitors get neither this script nor the marked elements.
 *
 * The switch in the bar turns edit mode on: every element with data-page-text gets a small button. A click loads the
 * raw text (placeholders like %price% not replaced) from App\Controller\Admin\PageTextController and opens an editor
 * below the element. Saving or resetting sends JSON with the session token and reloads the page, so it shows exactly
 * what visitors see; rendering the changed text here would mean repeating the templates (paragraphs, lists,
 * placeholders) in JavaScript.
 *
 * The mode is kept in sessionStorage (only in an admin's browser, ends with the tab), so it survives that reload.
 */
const STORAGE_KEY = 'sc-page-editor';

export default class extends Controller {
    static targets = ['switch', 'panel'];
    static values = { baseUrl: String, token: String, error: String, confirmReset: String, editLabel: String };

    connect() {
        const on = '1' === sessionStorage.getItem(STORAGE_KEY);
        this.switchTarget.checked = on;
        if (on) {
            this.enable();
        }
    }

    disconnect() {
        this.disable();
    }

    toggle() {
        if (this.switchTarget.checked) {
            sessionStorage.setItem(STORAGE_KEY, '1');
            this.enable();
        } else {
            sessionStorage.removeItem(STORAGE_KEY);
            this.disable();
        }
    }

    enable() {
        document.body.classList.add('sc-editing');
        document.querySelectorAll('[data-page-text]').forEach((element) => {
            if (element.querySelector(':scope > .sc-page-editor__button')) {
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sc-page-editor__button btn btn-sm btn-warning';
            button.textContent = '✎';
            button.title = this.editLabelValue;
            button.setAttribute('aria-label', `${this.editLabelValue}: ${element.dataset.pageText}`);
            button.addEventListener('click', (event) => {
                // The marked element may be a link or the accordion button: only open the editor
                event.preventDefault();
                event.stopPropagation();
                this.open(element);
            });
            element.classList.add('sc-page-editor__target');
            element.append(button);
        });
    }

    disable() {
        this.close();
        document.body.classList.remove('sc-editing');
        document.querySelectorAll('.sc-page-editor__button').forEach((button) => button.remove());
        document.querySelectorAll('.sc-page-editor__target').forEach((element) => element.classList.remove('sc-page-editor__target'));
    }

    async open(element) {
        this.close();
        const key = element.dataset.pageText;
        const response = await this.request(`${this.url(key)}/daten`, 'GET');
        if (!response.ok) {
            window.alert(response.data.error || this.errorValue);
            return;
        }

        const data = response.data;
        const panel = this.panelTarget.content.firstElementChild.cloneNode(true);
        panel.dataset.key = key;
        panel.querySelector('[data-label]').textContent = data.label;
        panel.querySelector('[data-text]').value = data.text;
        // What a line break does here (list item, paragraph, line, nothing); a single line gets a small field
        panel.querySelector('[data-hint]').textContent = data.hint;
        panel.querySelector('[data-text]').rows = 'line' === data.kind ? 2 : 6;
        panel.querySelector('[data-default]').textContent = data.default;
        if (data.changed) {
            panel.querySelector('[data-changed]').hidden = false;
            panel.querySelector('[data-changed] .badge').textContent = data.changed;
            panel.querySelector('[data-reset]').hidden = false;
        }
        if (data.placeholders.length > 0) {
            const hint = panel.querySelector('[data-placeholders]');
            hint.hidden = false;
            hint.textContent = data.placeholders.join(', ');
        }

        // The panel sits after the marked element, outside this controller's element, where Stimulus actions
        // (data-action) do not reach: the events are attached here
        panel.addEventListener('submit', (event) => this.save(event));
        panel.querySelector('[data-cancel]').addEventListener('click', () => this.close());
        panel.querySelector('[data-reset]').addEventListener('click', () => this.reset());
        element.after(panel);
        this.panel = panel;
        panel.querySelector('[data-text]').focus();
    }

    close() {
        this.panel?.remove();
        this.panel = null;
    }

    async save(event) {
        event.preventDefault();
        const panel = this.panel;
        const response = await this.request(`${this.url(panel.dataset.key)}/speichern`, 'POST', {
            text: panel.querySelector('[data-text]').value,
        });
        this.finish(response);
    }

    async reset() {
        if (!window.confirm(this.confirmResetValue)) {
            return;
        }
        const response = await this.request(`${this.url(this.panel.dataset.key)}/zuruecksetzen.json`, 'POST', {});
        this.finish(response);
    }

    finish(response) {
        if (response.ok) {
            window.location.reload();
            return;
        }
        const errors = this.panel.querySelector('[data-errors]');
        errors.textContent = (response.data.errors || [this.errorValue]).join(' ');
        this.panel.querySelector('[data-text]').classList.add('is-invalid');
    }

    url(key) {
        return `${this.baseUrlValue}/${encodeURIComponent(key)}`;
    }

    async request(url, method, body = null) {
        const buttons = this.panel ? this.panel.querySelectorAll('button') : [];
        buttons.forEach((button) => { button.disabled = true; });
        try {
            const response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': this.tokenValue },
                body: null === body ? null : JSON.stringify(body),
            });
            const isJson = (response.headers.get('Content-Type') || '').includes('application/json');
            const data = isJson ? await response.json() : {};

            return { ok: response.ok && isJson, status: response.status, data };
        } catch {
            return { ok: false, status: 0, data: {} };
        } finally {
            buttons.forEach((button) => { button.disabled = false; });
        }
    }
}
