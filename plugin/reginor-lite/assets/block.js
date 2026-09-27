(function (blocks, element, editor, components, i18n) {
    'use strict';
    const el = element.createElement;
    const __ = i18n.__;
    const filters = [
        ['styles', __('dansestiler', 'reginor-lite')], ['days', __('ukedager', 'reginor-lite')],
        ['rooms', __('saler', 'reginor-lite')], ['venues', __('steder', 'reginor-lite')],
        ['instructors', __('instruktører', 'reginor-lite')], ['courses', __('kurs', 'reginor-lite')],
        ['periods', __('kursperioder', 'reginor-lite')], ['statuses', __('påmeldingsstatuser', 'reginor-lite')], ['price_bases', __('prisgrunnlag (person/pair)', 'reginor-lite')]
    ];
    const bounds = [['price_min', __('Minste pris (kr)', 'reginor-lite')], ['price_max', __('Høyeste pris (kr)', 'reginor-lite')], ['time_from', __('Tidligste oppstart (HH:MM)', 'reginor-lite')], ['time_until', __('Seneste oppstart (HH:MM)', 'reginor-lite')], ['sessions_min', __('Minst antall kurskvelder', 'reginor-lite')], ['sessions_max', __('Maksimalt antall kurskvelder', 'reginor-lite')]];
    blocks.registerBlockType('reginor-lite/courses', {
        apiVersion: 3, title: __('RegiNor kursoversikt', 'reginor-lite'), icon: 'calendar-alt', category: 'widgets',
        attributes: {
            ...Object.fromEntries(filters.flatMap(([key]) => [[key, { type: 'string', default: '' }], ['exclude_' + key, { type: 'string', default: '' }]])),
            ...Object.fromEntries(bounds.map(([key]) => [key, { type: 'string', default: '' }])),
            dropin: { type: 'string', default: 'all' },
            default_view: { type: 'string', default: 'site' }, allowed_views: { type: 'array', default: ['list', 'week'] },
            levels: { type: 'string', default: '' }, exclude_levels: { type: 'string', default: '' },
            featured: { type: 'string', default: 'all' }, show_header: { type: 'boolean', default: true },
            show_filters: { type: 'boolean', default: true }, show_view_switch: { type: 'boolean', default: true }
        },
        edit: function (props) {
            return el('div', editor.useBlockProps(),
                el('strong', null, __('RegiNor kursoversikt', 'reginor-lite')),
                el('p', null, __('Viser tilgjengelige kurs automatisk. Velg siden for kursoversikten under RegiNor Lite → Nettsidevisning.', 'reginor-lite')),
                el(components.SelectControl, { label: __('Vis først', 'reginor-lite'), value: props.attributes.default_view,
                    options: [{ label: __('Bruk felles utseendevalg', 'reginor-lite'), value: 'site' }, { label: __('Bruk kursperiodens valg', 'reginor-lite'), value: 'period' }, { label: __('Kursliste – anbefalt for nye deltakere', 'reginor-lite'), value: 'list' }, { label: __('Ukeskalender', 'reginor-lite'), value: 'week' }],
                    onChange: function (value) { props.setAttributes({ default_view: value }); } }),
                el(components.SelectControl, { label: __('Tillatte visninger', 'reginor-lite'), value: props.attributes.allowed_views.join(','),
                    options: [{ label: __('Kursliste og ukeskalender', 'reginor-lite'), value: 'list,week' }, { label: __('Bare kursliste', 'reginor-lite'), value: 'list' }, { label: __('Bare ukeskalender', 'reginor-lite'), value: 'week' }],
                    onChange: function (value) { props.setAttributes({ allowed_views: value.split(',') }); } }),
                el(components.TextControl, { label: __('Vis bare disse nivåene', 'reginor-lite'), value: props.attributes.levels,
                    help: __('Skriv nivånavn eller nivå-ID med komma mellom. Tomt felt viser alle nivåer.', 'reginor-lite'),
                    onChange: function (value) { props.setAttributes({ levels: value }); } }),
                el(components.TextControl, { label: __('Utelat disse nivåene', 'reginor-lite'), value: props.attributes.exclude_levels,
                    help: __('For eksempel Intro. Utelatte nivåer skjules også fra nivåfilteret.', 'reginor-lite'),
                    onChange: function (value) { props.setAttributes({ exclude_levels: value }); } }),
                ...bounds.map(([key, label]) => el(components.TextControl, { key, label, value: props.attributes[key], onChange: value => props.setAttributes({ [key]: value }) })),
                ...filters.flatMap(function ([key, label]) {
                    return [false, true].map(function (exclude) {
                        const attribute = (exclude ? 'exclude_' : '') + key;
                        return el(components.TextControl, { key: attribute, label: (exclude ? __('Utelat:', 'reginor-lite') : __('Vis bare:', 'reginor-lite')) + ' ' + label,
                            value: props.attributes[attribute], help: __('Bruk komma mellom verdier. Navn fra hovedspråket eller ID; statuser bruker statuskode. Tomt felt begrenser ikke utvalget.', 'reginor-lite'),
                            onChange: function (value) { props.setAttributes({ [attribute]: value }); } });
                    });
                }),
                el(components.SelectControl, { label: __('Drop-in', 'reginor-lite'), value: props.attributes.dropin,
                    options: [{ label: __('Alle kurs', 'reginor-lite'), value: 'all' }, { label: __('Bare kurs med drop-in', 'reginor-lite'), value: 'only' }, { label: __('Utelat kurs med drop-in', 'reginor-lite'), value: 'exclude' }],
                    onChange: function (value) { props.setAttributes({ dropin: value }); } }),
                el(components.SelectControl, { label: __('Fremhevede kurs', 'reginor-lite'), value: props.attributes.featured,
                    options: [{ label: __('Alle kurs', 'reginor-lite'), value: 'all' }, { label: __('Bare fremhevede', 'reginor-lite'), value: 'only' }, { label: __('Utelat fremhevede', 'reginor-lite'), value: 'exclude' }],
                    onChange: function (value) { props.setAttributes({ featured: value }); } }),
                ...[['show_header', __('Vis overskrift og periodeoverskrift', 'reginor-lite')], ['show_filters', __('Vis filtre', 'reginor-lite')], ['show_view_switch', __('La besøkende velge visning', 'reginor-lite')]].map(function ([key, label]) {
                    return el(components.ToggleControl, { key, label, checked: props.attributes[key], onChange: function (value) { props.setAttributes({ [key]: value }); } });
                }));
        },
        save: function () { return null; }
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
