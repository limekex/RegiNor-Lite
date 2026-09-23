(function () {
    'use strict';
    const __ = wp.i18n.__;
    document.querySelectorAll('[data-rnl-sharing]').forEach(function (form) {
        const preview = form.querySelector('[data-rnl-image-preview]');
        const input = form.elements.image_id;
        const status = form.querySelector('[data-rnl-image-status]');
        const choose = form.querySelector('[data-rnl-image-choose]');
        let frame;
        if (choose && wp.media) choose.addEventListener('click', function () {
            if (!frame) {
                frame = wp.media({title: __('Velg delingsbilde', 'reginor-lite'), button: {text: __('Bruk dette bildet', 'reginor-lite')}, library: {type: 'image'}, multiple: false});
                frame.on('select', function () {
                    const item = frame.state().get('selection').first().toJSON();
                    const image = document.createElement('img');
                    image.src = item.sizes?.medium?.url || item.url; image.alt = item.alt || ''; image.className = 'rnl-image-preview';
                    preview.replaceChildren(image); input.value = item.id;
                    status.textContent = __('Bildet er valgt. Lagre delingsvalgene for å bruke det.', 'reginor-lite');
                });
            }
            frame.open();
        });
        form.querySelector('[data-rnl-image-remove]').addEventListener('click', function () {
            input.value = '0'; preview.replaceChildren(); status.textContent = __('Standardbildet brukes etter at du har lagret.', 'reginor-lite');
        });
        form.addEventListener('input', function () {
            const title = form.querySelector('[data-rnl-share-title]');
            if (title) { title.textContent = form.elements.title.value || title.dataset.default; form.querySelector('[data-rnl-share-description]').textContent = form.elements.description.value; }
        });
    });
}());
