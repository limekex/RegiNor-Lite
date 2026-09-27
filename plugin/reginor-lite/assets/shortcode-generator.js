(function () {
    'use strict';
    const button = document.getElementById('rnl-copy-shortcode');
    const field = document.getElementById('rnl-generated-shortcode');
    const status = document.getElementById('rnl-copy-status');
    if (!button || !field || !status) return;
    button.hidden = false;
    document.querySelector('.rnl-admin form')?.addEventListener('input', function () { button.disabled = true; status.textContent = status.dataset.dirty; });
    button.addEventListener('click', async function () {
        try { await navigator.clipboard.writeText(field.value); status.textContent = status.dataset.success; }
        catch (_) { field.focus(); field.select(); status.textContent = status.dataset.failure; }
    });
})();
