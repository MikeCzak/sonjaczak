import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */

/*
 * Asks before a break is saved that would delete lessons (umsetzung-admin.md C4, question 6). On submit it asks the
 * server which lessons lie in the period (urlValue, with von/bis from the date fields if there are any); if there
 * are some, a confirm() popup lists them. On "OK" the hidden field confirmed is set to 1 and the form is sent; the
 * server deletes only with confirmed=1 (CourseController::newBreak(), importBreaks()).
 */
export default class extends Controller {
    static targets = ['confirmed', 'firstDay', 'lastDay'];
    static values = { url: String };

    async check(event) {
        if (this.passing) {
            this.passing = false;
            return; // second round, after the question: let the form go
        }
        event.preventDefault();

        const url = new URL(this.urlValue, window.location.href);
        if (this.hasFirstDayTarget) {
            url.searchParams.set('von', this.firstDayTarget.value);
            url.searchParams.set('bis', this.hasLastDayTarget ? this.lastDayTarget.value : '');
        }

        let question = null;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            question = (await response.json()).confirm;
        } catch {
            // No answer: send without confirmation; the server then refuses to delete and lists the lessons
        }

        if (question && !window.confirm(question)) {
            return;
        }
        this.confirmedTarget.value = question ? '1' : '';
        // requestSubmit() fires submit again (and runs the browser's own validation of required fields)
        this.passing = true;
        this.element.requestSubmit();
    }
}
