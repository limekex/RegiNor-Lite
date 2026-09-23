(function (blocks, element, editor, components, i18n) {
    'use strict';
    const el = element.createElement;
    const __ = i18n.__;
    blocks.registerBlockType('reginor-lite/courses', {
        apiVersion: 3, title: __('RegiNor kursoversikt', 'reginor-lite'), icon: 'calendar-alt', category: 'widgets',
        attributes: { default_view: { type: 'string', default: 'site' } },
        edit: function (props) {
            return el('div', editor.useBlockProps(),
                el('strong', null, __('RegiNor kursoversikt', 'reginor-lite')),
                el('p', null, __('Viser tilgjengelige kurs automatisk. Velg siden for kursoversikten under RegiNor Lite → Nettsidevisning.', 'reginor-lite')),
                el(components.SelectControl, { label: __('Vis først', 'reginor-lite'), value: props.attributes.default_view,
                    options: [{ label: __('Bruk felles utseendevalg', 'reginor-lite'), value: 'site' }, { label: __('Bruk kursperiodens valg', 'reginor-lite'), value: 'period' }, { label: __('Kursliste – anbefalt for nye deltakere', 'reginor-lite'), value: 'list' }, { label: __('Ukeskalender', 'reginor-lite'), value: 'week' }],
                    onChange: function (value) { props.setAttributes({ default_view: value }); } }));
        },
        save: function () { return null; }
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
