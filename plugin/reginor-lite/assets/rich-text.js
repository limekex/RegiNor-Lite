/* WordPress visual/HTML editors share the same textarea with normal and AJAX forms. */
(function () {
    'use strict';
    const __ = window.wp.i18n.__;
    const fields = () => [...document.querySelectorAll('textarea[data-rnl-rich-text]')];
    const editor = field => window.tinymce?.get(field.id);
    const api = window.RegiNorRichText = {
        save(form) {
            fields().filter(field => !form || field.form === form).forEach(field => {
                const instance = editor(field);
                if (instance && !instance.isHidden()) instance.save();
            });
        },
        set(field, value) {
            field.value = value;
            editor(field)?.setContent(value);
        },
        refresh(form) {
            fields().filter(field => !form || field.form === form).forEach(field => api.set(field, field.value));
        },
        focus(field) {
            const instance = editor(field);
            if (!instance || instance.isHidden()) return false;
            instance.focus(); return true;
        }
    };
    document.addEventListener('submit', event => api.save(event.target), true);
    // Explicit synchronization is also used before AJAX FormData and bulk snapshots.
    function init() {
        if (!window.wp?.editor?.initialize) return;
        fields().forEach(field => {
            window.wp.editor.initialize(field.id, {
                mediaButtons: false,
                quicktags: { buttons: 'strong,em,link,ul,ol,li,close' },
                tinymce: {
                    wpautop: true, height: 230, menubar: false,
                    toolbar1: 'bold italic | bullist numlist | link unlink | undo redo', toolbar2: '',
                    plugins: 'lists,paste,wordpress,wplink',
                    setup(instance) {
                        instance.on('init', () => {
                            const body = instance.getBody();
                            body.setAttribute('aria-label', field.labels?.[0]?.textContent || '');
                        });
                        instance.on('change input undo redo', () => {
                            instance.save();
                            field.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    }
                }
            });
            const visual = document.getElementById(field.id + '-tmce');
            const html = document.getElementById(field.id + '-html');
            if (visual) visual.textContent = __('Visuell', 'reginor-lite');
            if (html) html.textContent = __('HTML', 'reginor-lite');
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
