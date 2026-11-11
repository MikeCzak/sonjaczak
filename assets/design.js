/*
 * Style guide (/_design): shows the WCAG contrast ratio of each color swatch.
 * Formula: https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio
 */

function relativeLuminance(rgb) {
    const [r, g, b] = rgb.map((value) => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function parseRgb(color) {
    return color.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number);
}

document.querySelectorAll('[data-contrast]').forEach((swatch) => {
    const style = getComputedStyle(swatch);
    const luminances = [parseRgb(style.color), parseRgb(style.backgroundColor)].map(relativeLuminance);
    const ratio = (Math.max(...luminances) + 0.05) / (Math.min(...luminances) + 0.05);
    const result = swatch.querySelector('[data-contrast-result]');

    // 4.5:1 for body text, 3:1 for large text (WCAG 2.1 AA)
    const rating = ratio >= 4.5 ? 'AA' : ratio >= 3 ? 'AA large' : '–';
    result.textContent = `${ratio.toFixed(2)}:1 (${rating})`;
});
