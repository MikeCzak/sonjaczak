/*
 * Lessons inquiry: show the guardian field only while the age is below the adult age (App\Form\LessonsInquiryType).
 * Without JavaScript the field stays visible with a hint; the server checks it either way.
 */
for (const age of document.querySelectorAll('[data-guardian-age]')) {
    const field = age.form?.querySelector('[data-guardian-field]');
    if (!field) {
        continue;
    }

    const adultAge = Number(age.dataset.guardianAge);
    const update = () => {
        const value = Number.parseInt(age.value, 10);
        // Empty or adult: hidden (NaN < adultAge is false)
        field.hidden = !(value < adultAge);
    };

    age.addEventListener('input', update);
    update();
}
