(function () {
    'use strict';
    function init(root, navigator, translate) {
        const __ = translate || ((value) => value);
        root.querySelectorAll('[data-rnl-tools]').forEach((panel) => {
            if (panel.dataset.rnlToolsReady) return;
            panel.dataset.rnlToolsReady = '1';
            const status = panel.querySelector('[data-rnl-tools-status]');
            const say = (message) => { if (status) status.textContent = message; };
            panel.querySelectorAll('[data-rnl-copy]').forEach((button) => {
                button.hidden = false;
                button.addEventListener('click', async () => {
                    const input = button.closest('.rnl-copy-field').querySelector('input');
                    say('');
                    try {
                        if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
                        await navigator.clipboard.writeText(input.value);
                        say(__('Lenken er kopiert.', 'reginor-lite'));
                    } catch (_) {
                        input.focus(); input.select();
                        say(__('Kopier den markerte lenken med kopieringsfunksjonen på enheten din.', 'reginor-lite'));
                    }
                });
            });
            const share = panel.querySelector('[data-rnl-native-share]');
            if (share && typeof navigator.share === 'function') {
                share.hidden = false;
                share.addEventListener('click', async () => {
                    say('');
                    try {
                        await navigator.share({title: panel.dataset.rnlShareTitle, url: panel.dataset.rnlShareUrl});
                    } catch (error) {
                        if (error.name !== 'AbortError') say(__('Delingsmenyen kunne ikke åpnes. Bruk Kopier lenke eller en av delingsknappene.', 'reginor-lite'));
                    }
                    share.focus();
                });
            }
        });
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = {init};
    if (typeof document !== 'undefined') init(document, window.navigator, window.wp?.i18n?.__);
}());
