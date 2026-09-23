(function () {
    'use strict';
    function ink(hex) {
        const rgb = hex.slice(1).match(/../g).map((part) => { const v = parseInt(part, 16) / 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
        return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2] > 0.179 ? '#000000' : '#ffffff';
    }
    function hex(value) {
        if (/^#[a-f0-9]{6}$/i.test(value)) return value.toLowerCase();
        if (/^#[a-f0-9]{3}$/i.test(value)) return '#' + value.slice(1).toLowerCase().split('').map((c) => c + c).join('');
        const rgb = value.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d.]+)?\s*\)$/);
        return rgb && rgb.slice(1, 4).every((v) => Number(v) <= 255) ? '#' + rgb.slice(1, 4).map((v) => Number(v).toString(16).padStart(2, '0')).join('') : null;
    }
    function paint(color, source, alpha) {
        const base = source.startsWith('wp:') ? 'var(--wp--preset--color--' + source.slice(3) + ',' + color + ')' : source.startsWith('avada:') ? 'var(--awb-' + source.slice(6) + ',' + color + ')' : color;
        return Number(alpha) === 100 ? base : 'color-mix(in srgb,' + base + ' ' + Number(alpha) + '%,transparent)';
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { ink, hex, paint };
    if (typeof document === 'undefined') return;
    document.querySelectorAll('[data-rnl-appearance-root]').forEach((root) => {
        const form = root.querySelector('[data-rnl-appearance]'); const preview = form?.querySelector('[data-rnl-preview]');
        const swatches = root.querySelectorAll('[data-rnl-swatch]');
        const refreshDays = () => {
            if (!preview) return;
            const scope = form.querySelector('[data-rnl-day-color-views]')?.value || 'week';
            preview.classList.toggle('rnl-day-colors-off', scope === 'list');
            const custom = form.querySelector('[name="appearance[days][1][enabled]"][type="checkbox"]')?.checked;
            const day = preview.querySelector('.rnl-day');
            for (const suffix of ['bg', 'ink']) {
                for (const key of ['day', 'day-heading']) {
                    if (custom) day?.style.setProperty('--rnl-' + key + '-' + suffix, 'var(--rnl-day1-' + suffix + ')');
                    else day?.style.removeProperty('--rnl-' + key + '-' + suffix);
                }
                const card = preview.querySelector('[data-rnl-day-card-preview]');
                if (scope !== 'week') card?.style.setProperty('--rnl-course-' + suffix, 'var(--rnl-' + (custom ? 'day1' : 'day') + '-' + suffix + ')');
                else card?.style.removeProperty('--rnl-course-' + suffix);
            }
        };
        form?.addEventListener('change', refreshDays);
        const refresh = (swatch, fromSource = false) => {
            const input = swatch.querySelector('[data-rnl-color]'); const source = swatch.querySelector('[data-rnl-source]');
            if (fromSource) { const value = hex(source.selectedOptions[0]?.dataset.color || ''); if (value) input.value = value; }
            const alpha = swatch.querySelector('[data-rnl-alpha]'); const tone = swatch.querySelector('[data-rnl-tone]').value;
            swatch.querySelector('[data-rnl-alpha-slider]').value = alpha.value;
            swatch.querySelectorAll('[data-rnl-palette-choice]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.rnlPaletteChoice === source.value)));
            if (!form || !preview || !form.contains(swatch)) return;
            const key = input.dataset.rnlColor.replaceAll('_', '-');
            preview.style.setProperty('--rnl-' + key + '-bg', paint(input.value, source.value, alpha.value));
            preview.style.setProperty('--rnl-' + key + '-ink', tone === 'auto' ? ink(input.value) : tone === 'light' ? '#ffffff' : '#000000');
            refreshDays();
        };
        const samples = (swatch) => {
            const select = swatch.querySelector('[data-rnl-source]'); const holder = swatch.querySelector('[data-rnl-palette-samples]');
            holder.replaceChildren();
            Array.from(select.options).forEach((option) => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'rnl-palette-sample';
                button.dataset.rnlPaletteChoice = option.value; button.setAttribute('aria-label', option.textContent); button.setAttribute('aria-pressed', String(option.selected));
                const color = hex(option.dataset.color || '') || '#cccccc';
                const chip = document.createElement('span'); chip.className = 'rnl-palette-chip'; chip.style.background = option.value ? paint(color, option.value, 100) : 'conic-gradient(red,yellow,lime,aqua,blue,magenta,red)'; chip.setAttribute('aria-hidden', 'true');
                const label = document.createElement('span'); label.className = 'rnl-swatch-label'; label.textContent = option.textContent;
                button.append(chip, label); button.addEventListener('click', () => { select.value = option.value; refresh(swatch, true); }); holder.appendChild(button);
            });
            select.hidden = true;
        };
        swatches.forEach((swatch) => {
            swatch.querySelector('[data-rnl-source]').addEventListener('change', () => refresh(swatch, true));
            swatch.querySelector('[data-rnl-alpha-slider]').addEventListener('input', (event) => { swatch.querySelector('[data-rnl-alpha]').value = event.target.value; refresh(swatch); });
            ['[data-rnl-color]', '[data-rnl-alpha]', '[data-rnl-tone]'].forEach((selector) => swatch.querySelector(selector).addEventListener('input', () => refresh(swatch)));
            samples(swatch); refresh(swatch, true);
        });
        const reset = form?.querySelector('[data-rnl-reset]'); if (reset) reset.hidden = false;
        reset?.addEventListener('click', () => {
            form.querySelectorAll('[name$="[enabled]"][type="checkbox"]').forEach((input) => { input.checked = false; });
            form.querySelectorAll('[data-rnl-swatch]').forEach((swatch) => {
                const input = swatch.querySelector('[data-rnl-color]'); input.value = input.dataset.default;
                swatch.querySelector('[data-rnl-source]').value = ''; swatch.querySelector('[data-rnl-alpha]').value = '100'; swatch.querySelector('[data-rnl-tone]').value = 'auto'; refresh(swatch);
            });
        });
        const frame = root.querySelector('[data-rnl-palette-frame]');
        if (frame) window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.source !== frame.contentWindow || event.data?.type !== 'rnl-theme-palette' || !event.data.colors || typeof event.data.colors !== 'object') return;
            Object.entries(event.data.colors).forEach(([source, color]) => {
                if (!/^avada:color[1-9][0-9]{0,3}$/.test(source) || typeof color !== 'string' || !hex(color)) return;
                root.style.setProperty('--awb-' + source.slice(6), color);
                swatches.forEach((swatch) => {
                    const select = swatch.querySelector('[data-rnl-source]');
                    let option = Array.from(select.options).find((o) => o.value === source);
                    if (!option) { option = document.createElement('option'); option.value = source; option.textContent = 'Avada · ' + source.slice(6); select.appendChild(option); }
                    option.dataset.color = color; samples(swatch); refresh(swatch, true);
                });
            });
        });
        if (frame) {
            const requestPalette = () => frame.contentWindow?.postMessage({ type: 'rnl-read-palette' }, window.location.origin);
            frame.addEventListener('load', requestPalette); requestPalette();
        }
    });
}());
