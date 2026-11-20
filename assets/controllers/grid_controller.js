import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */

/*
 * Course grid (templates/admin/course/grid.html.twig, umsetzung-admin.md C4d). Opens a small editor for a lesson
 * (cancellation, note) and talks to the JSON endpoints of App\Controller\Admin\LessonController. Since C4g the editor
 * floats below the box, so the boxes on the time axis stay where they are.
 *
 * The server decides and counts; this controller only sends what Sonja clicked and shows the answer. Whenever a
 * change moves lessons around (cancellation, deletion, double lesson), the page is reloaded instead of rebuilding
 * the grid here. Only a changed note is updated in place. All texts come translated from the template.
 *
 * Without JavaScript every lesson is a plain link to its edit page.
 *
 * Since C4h a planned lesson can be dragged within its day with mouse, touchpad or pen (not with a finger: that would
 * fight with scrolling the grid sideways). It snaps to full 5 minutes and is only dropped where nothing takes place;
 * the server checks again and answers with the day's row drawn anew, which replaces the old one (no reload). Below
 * 5 px of movement it stays a click and opens the editor.
 */
const DRAG_THRESHOLD = 5; // px of movement before a press becomes a drag
const SNAP_MINUTES = 5;
const SCROLL_EDGE = 40; // px
const SCROLL_STEP = 15; // px per pointer move

// Minutes after midnight as "15:35"
const clock = (minutes) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;

/**
 * After a drag the browser still sends a click to the box (or the element under the pointer); it must not open the
 * editor or follow the link. It comes right after pointerup, so the listener only lives until then.
 */
const swallowNextClick = () => {
    const swallow = (event) => {
        event.preventDefault();
        event.stopPropagation();
    };
    window.addEventListener('click', swallow, { capture: true, once: true });
    setTimeout(() => window.removeEventListener('click', swallow, { capture: true }), 0);
};

export default class extends Controller {
    static targets = ['editor'];
    static values = { token: String, confirmDelete: String, error: String };

    open(event) {
        const link = event.currentTarget;
        this.close();

        this.link = link;
        this.box = link.closest('.grid-item');
        this.cancelledBy = link.dataset.cancelledBy || null;
        this.editor = this.editorTarget.content.firstElementChild.cloneNode(true);
        this.editor.querySelector('[data-note-input]').value = link.dataset.note || '';
        this.editor.querySelector('[data-edit-link]').href = link.href;
        this.editor.querySelector('[data-uncancel]').hidden = null === this.cancelledBy;
        this.showPressed();
        this.place();
        this.editor.querySelector('[data-note-input]').focus();
    }

    /**
     * Into the day's axis (positioned), below the box; above it if there is no room below the table (the last
     * rows), and moved left so it does not stick out on the right.
     */
    place() {
        const track = this.box.closest('.grid-track');
        track.append(this.editor);
        const gap = 4;
        const left = Math.max(0, Math.min(this.box.offsetLeft, track.offsetWidth - this.editor.offsetWidth));
        let top = this.box.offsetTop + this.box.offsetHeight + gap;
        const table = track.closest('table').getBoundingClientRect();
        if (this.box.getBoundingClientRect().bottom + gap + this.editor.offsetHeight > table.bottom) {
            top = this.box.offsetTop - this.editor.offsetHeight - gap;
        }
        this.editor.style.left = `${left}px`;
        this.editor.style.top = `${top}px`;
    }

    close() {
        const link = this.link;
        this.editor?.remove();
        this.editor = null;
        this.link = null;
        this.box = null;

        return link;
    }

    // "Abbrechen" and Escape: back to the box, so the keyboard continues there
    cancel() {
        this.close()?.focus();
    }

    escape() {
        if (this.drag?.moving) {
            this.abortDrag();
        } else if (this.editor) {
            this.cancel();
        }
    }

    // A click outside the editor and outside any lesson closes it (a click on another lesson opens that one)
    outside(event) {
        if (this.editor && !this.editor.contains(event.target) && !event.target.closest('.grid-lesson')) {
            this.close();
        }
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
        // Only the note changed: update the box, no reload (the overlap hint in the title goes until the next load)
        this.link.dataset.note = data.lesson.note ?? '';
        this.box.querySelector('[data-note-text]')?.replaceChildren(data.lesson.note ?? '');
        this.box.title = data.lesson.stateLabel + (data.lesson.note ? ': ' + data.lesson.note : '');
        this.cancel();
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

    // Dragging (C4h)

    grab(event) {
        if ('touch' === event.pointerType || 0 !== event.button || this.drag) {
            return;
        }
        const box = event.currentTarget;
        const track = box.closest('.grid-track');
        const [axisStart, axisEnd] = track.dataset.axis.split('-').map(Number);
        this.drag = {
            box,
            track,
            axisStart,
            axisLength: axisEnd - axisStart,
            pointerId: event.pointerId,
            startX: event.clientX,
            // Where in the box it was grabbed, so the box does not jump under the pointer
            offset: event.clientX - box.getBoundingClientRect().left,
            start: Number(box.style.getPropertyValue('--start')),
            length: Number(box.style.getPropertyValue('--length')),
            at: null,
            moving: false,
            aborted: false,
            blocked: false,
        };
        this.dragListeners = {
            pointermove: (e) => this.dragMove(e),
            pointerup: (e) => this.drop(e),
            pointercancel: (e) => this.drop(e),
        };
        Object.entries(this.dragListeners).forEach(([name, listener]) => window.addEventListener(name, listener));
    }

    // Links can be dragged by the browser itself (to the address bar); here the box is dragged instead
    preventNativeDrag(event) {
        event.preventDefault();
    }

    dragMove(event) {
        const drag = this.drag;
        if (!drag || event.pointerId !== drag.pointerId || drag.aborted) {
            return;
        }
        if (!drag.moving) {
            if (Math.abs(event.clientX - drag.startX) < DRAG_THRESHOLD) {
                return;
            }
            drag.moving = true;
            this.close();
            drag.time = document.createElement('span');
            drag.time.className = 'grid-drag-time';
            drag.box.append(drag.time);
            drag.box.classList.add('grid-item--dragging');
            this.element.classList.add('grid-is-dragging');
        }
        this.autoScroll(event);

        // Minutes from the start of the axis, snapped to full 5 minutes of the clock (the axis starts on a full
        // hour, so both are the same) and kept on the axis
        const track = drag.track.getBoundingClientRect();
        const minute = (event.clientX - drag.offset - track.left) / (track.width / drag.axisLength);
        const last = Math.floor((drag.axisLength - drag.length) / SNAP_MINUTES) * SNAP_MINUTES;
        drag.at = Math.min(Math.max(Math.round(minute / SNAP_MINUTES) * SNAP_MINUTES, 0), last);
        drag.blocked = this.collides(drag);

        drag.box.style.setProperty('--start', drag.at);
        drag.box.classList.toggle('grid-item--blocked', drag.blocked);
        const from = drag.axisStart + drag.at;
        drag.time.textContent = `${clock(from)}–${clock(from + drag.length)}`;
    }

    async drop(event) {
        const drag = this.drag;
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }
        Object.entries(this.dragListeners).forEach(([name, listener]) => window.removeEventListener(name, listener));
        this.drag = null;
        if (!drag.moving) {
            return; // a click: the editor opens
        }
        swallowNextClick();
        this.element.classList.remove('grid-is-dragging');
        if (drag.aborted || 'pointercancel' === event.type || drag.blocked || drag.at === drag.start) {
            this.restore(drag);
            return;
        }

        drag.box.classList.add('grid-item--saving');
        const url = drag.box.querySelector('.stretched-link').href;
        const startsAt = `${drag.track.dataset.day}T${clock(drag.axisStart + drag.at)}`;
        const { ok, data } = await this.request('PATCH', url, { startsAt });
        if (!ok) {
            this.restore(drag);
            window.alert(data.error || this.errorValue);
            return;
        }
        this.replaceRow(drag.track, data.row?.html, drag.box.dataset.lessonId);
    }

    // Escape while dragging: back to where it was; the pointerup that follows does nothing
    abortDrag() {
        this.drag.aborted = true;
        this.element.classList.remove('grid-is-dragging');
        this.restore(this.drag);
    }

    restore(drag) {
        drag.box.style.setProperty('--start', drag.start);
        drag.box.classList.remove('grid-item--dragging', 'grid-item--blocked', 'grid-item--saving');
        drag.time?.remove();
    }

    /**
     * Same rule as the server: something that takes place (lessons in the day's row, cancelled ones are in the red
     * row below, and trial lessons) overlaps the new time. Back to back is fine.
     */
    collides(drag) {
        const start = drag.at;
        const end = drag.at + drag.length;

        return [...drag.track.querySelectorAll('.grid-lesson, .grid-trial')].some((other) => {
            if (other === drag.box) {
                return false;
            }
            const otherStart = Number(other.style.getPropertyValue('--start'));
            const otherEnd = otherStart + Number(other.style.getPropertyValue('--length'));

            return otherStart < end && start < otherEnd;
        });
    }

    /**
     * The grid scrolls sideways; near its edges it scrolls along while dragging. The left edge is where the sticky
     * week and day columns end.
     */
    autoScroll(event) {
        const wrap = this.drag.track.closest('.grid-wrap');
        const right = wrap.getBoundingClientRect().right;
        const left = wrap.querySelector('thead .grid-day').getBoundingClientRect().right;
        if (event.clientX > right - SCROLL_EDGE) {
            wrap.scrollLeft += SCROLL_STEP;
        } else if (event.clientX < left + SCROLL_EDGE) {
            wrap.scrollLeft -= SCROLL_STEP;
        }
    }

    /**
     * The day's row as the server drew it. Stimulus connects the actions in the new HTML by itself (it watches the
     * controller's element with a MutationObserver). If the axis changed (the moved lesson was the earliest or latest
     * of the course), the row would not fit the others: reload.
     */
    replaceRow(track, html, lessonId) {
        const template = document.createElement('template');
        template.innerHTML = (html || '').trim();
        const fresh = template.content.firstElementChild;
        if (!fresh || fresh.dataset.axis !== track.dataset.axis) {
            window.location.reload();
            return;
        }
        track.replaceWith(fresh);
        fresh.querySelector(`[data-lesson-id="${lessonId}"] .stretched-link`)?.focus({ preventScroll: true });
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
