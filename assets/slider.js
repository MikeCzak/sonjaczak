/*
 * Photo slider on the home page (templates/page/home.html.twig), based on Bootstrap's carousel.
 *
 * Bootstrap handles swiping and the arrow keys. Our own buttons instead of data-bs-slide, because Bootstrap's
 * data-bs-slide click handler restarts autoplay after every click, even after the visitor paused it.
 * Pause button: WCAG 2.2.2 requires a way to stop content that moves automatically for more than five seconds.
 * Visitors who prefer reduced motion get no autoplay.
 */
import { Carousel } from 'bootstrap';

const INTERVAL = 8000;

for (const element of document.querySelectorAll('[data-slider]')) {
    const controls = element.querySelector('[data-slider-controls]');
    if (!controls) {
        continue;
    }

    // ride: false, we start autoplay ourselves; pause: false, otherwise leaving the slider with the mouse resumes it
    const carousel = Carousel.getOrCreateInstance(element, { interval: INTERVAL, ride: false, pause: false });
    const pauseButton = controls.querySelector('[data-slider-pause]');
    const pauseIcon = pauseButton.querySelector('[data-slider-pause-icon]');
    const pauseLabel = pauseButton.querySelector('[data-slider-pause-label]');

    let playing = false;
    const setPlaying = (value) => {
        playing = value;
        if (playing) {
            carousel.cycle();
        } else {
            carousel.pause();
        }
        // U+FE0E: show ▶ as a text symbol, not as an emoji
        pauseIcon.textContent = playing ? '❚❚' : '\u25B6\uFE0E';
        pauseLabel.textContent = playing ? pauseButton.dataset.labelPause : pauseButton.dataset.labelPlay;
    };

    // Bootstrap keeps autoplay running after prev/next only if it was running before (Carousel._slide())
    controls.querySelector('[data-slider-prev]').addEventListener('click', () => carousel.prev());
    controls.querySelector('[data-slider-next]').addEventListener('click', () => carousel.next());
    pauseButton.addEventListener('click', () => setPlaying(!playing));

    controls.hidden = false;
    setPlaying(!window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}
