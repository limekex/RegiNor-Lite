(function () {
    'use strict';
    const choose = document.getElementById('rnl-calendar-image-choose');
    if (!choose || !window.wp || !wp.media) return;
    const input = document.getElementById('rnl-calendar-image');
    const preview = document.getElementById('rnl-calendar-image-preview');
    const remove = document.getElementById('rnl-calendar-image-remove');
    const status = document.getElementById('rnl-calendar-image-status');
    const text = window.rnlCalendarSettings;
    let frame;
    choose.addEventListener('click', function () {
        if (!frame) {
            frame = wp.media({title: text.title, button: {text: text.select}, library: {type: 'image'}, multiple: false});
            frame.on('open', function () {
                const selection = frame.state().get('selection');
                selection.reset();
                if (Number(input.value)) selection.add(wp.media.attachment(Number(input.value)));
            });
            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                input.value = attachment.id;
                const image = document.createElement('img');
                image.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                image.alt = attachment.alt || '';
                image.style.maxWidth = '100%';
                image.style.height = 'auto';
                preview.replaceChildren(image);
                remove.hidden = false;
                status.textContent = text.selected;
            });
        }
        frame.open();
    });
    remove.addEventListener('click', function () {
        input.value = '0';
        preview.replaceChildren();
        remove.hidden = true;
        status.textContent = text.removed;
        choose.focus();
    });
}());
