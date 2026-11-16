import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */

/*
 * Course grid (templates/admin/course/grid.html.twig, umsetzung-admin.md C4d). Opens a small editor in the cell of a
 * lesson (cancellation, note) and talks to the JSON endpoints of App\Controller\Admin\LessonController.
 *
 * The server decides and counts; this controller only sends what Sonja clicked and shows the answer. Whenever a
 * change moves lessons around (cancellation, deletion, double lesson), the page is reloaded instead of rebuilding
 * the grid here. Only a changed note is updated in place. All texts come translated from the template.
 *
 * Without JavaScript every lesson is a plain link to its edit page.
 */
export default class extends Controller {
    static targets = ['editor'];
    static values = { token: String, confirmDelete: String, error: String };

    open(event) {
        const link = event.currentTarget;
        this.close();

        this.link = link;
        this.cancelledBy = link.dataset.cancelledBy || null;
        this.editor = this.editorTarget.content.firstElementChild.cloneNode(true);
        this.editor.querySelector('[data-note-input]').value = link.dataset.note || '';
        this.editor.querySelector('[data-edit-link]').href = link.href;
        this.editor.querySelector('[data-uncancel]').hidden = null === this.cancelledBy;
        this.showPressed();
        link.after(this.editor);
        this.editor.querySelector('[data-note-input]').focus();
    }

    close() {
        this.editor?.remove();
        this.editor = null;
        this.link = null;
    }

    // Toggle: a second click on the pressed button withdraws the choice (Mike, question 3)
    toggleCancel(event) {
        const value = event.currentTarget.dataset.cancel;
        this.cancelledBy = this.cancelledBy === value ? null : value;
        this.showPressed();
    }

    async save() {
        const note = this.editor.querySelector('[data-note-input]').value;
        const before = this.link.dataset.cancelledBy || null;
        const data = await this.patch({ cancelledBy: this.cancelledBy, note });
        if (!data) {
            return;
        }
        if (before !== this.cancelledBy || data.deleted.length > 0 || data.added > 0) {
            window.location.reload();
            return;
        }
        // Only the note changed: update the cell, no reload
        this.link.dataset.note = data.lesson.note ?? '';
        this.link.querySelector('[data-note-text]').textContent = data.lesson.note ?? '';
        this.link.title = data.lesson.stateLabel + (data.lesson.note ? ': ' + data.lesson.note : '');
        this.close();
    }

    // "Absage aufheben" in the red row: back into the slot, whatever was put there in the meantime goes after a popup
    async uncancel() {
        if (await this.patch({ cancelledBy: null })) {
            window.location.reload();
        }
    }

    async remove() {
        if (!window.confirm(this.confirmDeleteValue)) {
            return;
        }
        const { ok, data } = await this.request('DELETE', this.link.href);
        if (ok) {
            window.location.reload();
        } else {
            this.showError(data.error);
        }
    }

    async double({ params: { url, position } }) {
        const { ok, data } = await this.request('POST', url, { position });
        if (!ok) {
            window.alert(data.error || this.errorValue);
            return;
        }
        if (data.message) {
            window.alert(data.message);
        }
        window.location.reload();
    }

    /**
     * PATCH with confirmation: a 409 carries a question; if Sonja agrees, the same request goes again with
     * deleteOverlapping. Returns the answer, or null if nothing was saved.
     */
    async patch(body) {
        let { ok, status, data } = await this.request('PATCH', this.link.href, body);
        if (409 === status && data.confirm) {
            if (!window.confirm(data.confirm)) {
                return null;
            }
            ({ ok, data } = await this.request('PATCH', this.link.href, { ...body, deleteOverlapping: true }));
        }
        if (!ok) {
            this.showError(data.error);
            return null;
        }

        return data;
    }

    /**
     * One request to the endpoints. The editor's buttons are locked meanwhile. An answer that is not JSON (e.g. the
     * login page after the session expired, fetch follows the redirect) counts as an error.
     */
    async request(method, url, body = null) {
        const buttons = this.editor ? [...this.editor.querySelectorAll('button')] : [];
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

    showPressed() {
        this.editor.querySelectorAll('[data-cancel]').forEach((button) => {
            const pressed = button.dataset.cancel === this.cancelledBy;
            button.setAttribute('aria-pressed', String(pressed));
            button.classList.toggle('active', pressed);
        });
    }

    showError(message) {
        if (!this.editor) {
            window.alert(message || this.errorValue);
            return;
        }
        const error = this.editor.querySelector('[data-error]');
        error.textContent = message || this.errorValue;
        error.hidden = false;
    }
}
