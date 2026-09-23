/* Loaded only on a same-origin, nonce-protected administrator palette preview. */
(function () {
    'use strict';
    const colors = {};
    const read = () => {
        [document.documentElement, document.body].forEach((element) => {
            const style = getComputedStyle(element);
            Array.from(style).filter((name) => /^--awb-color[1-9][0-9]{0,3}$/.test(name)).forEach((name) => {
                const swatch = document.createElement('span'); swatch.style.color = 'var(' + name + ')'; element.appendChild(swatch);
                colors['avada:' + name.slice(6)] = getComputedStyle(swatch).color; swatch.remove();
            });
        });
        if (window.parent !== window) window.parent.postMessage({ type: 'rnl-theme-palette', colors }, window.location.origin);
    };
    window.addEventListener('message', (event) => { if (event.origin === window.location.origin && event.source === window.parent && event.data?.type === 'rnl-read-palette') read(); });
    if (document.readyState === 'complete') read(); else window.addEventListener('load', read, { once: true });
}());
