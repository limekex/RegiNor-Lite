/* Progressive enhancement: server validation remains authoritative. */
(function () {
    'use strict';
    const __ = typeof wp !== 'undefined' && wp.i18n ? wp.i18n.__ : (text) => text;
    const format = (text, ...values) => text.replace(/%([1-9]\d*)\$s/g, (_, index) => String(values[+index - 1]));
    function validDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        const date = new Date(value + 'T12:00:00Z');
        return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
    }
    function check(field, context) {
        const value = field.value.trim();
        if (field.badInput) return __('Skriv en gyldig dato eller et gyldig tall.', 'reginor-lite');
        if (!value) {
            if (!field.required) return '';
            if (field.key === 'dance_style') return __('Dansestil mangler. LetsReg oppgir ikke denne som et eget felt. Skriv for eksempel Salsa eller Bachata.', 'reginor-lite');
            if (field.key === 'title') return __('Navn mangler. Skriv et navn som gjør kurset lett å kjenne igjen, for eksempel Salsa nybegynner.', 'reginor-lite');
            return __('Feltet er påkrevd, men er tomt. Fyll det ut ved hjelp av forklaringen under feltet.', 'reginor-lite');
        }
        if (field.type === 'date') {
            if (!validDate(value)) return __('Velg en gyldig dato i kalenderen.', 'reginor-lite');
            if (field.key === 'first_date' && context.periodStart && value < context.periodStart && !context.allowEarlyStart) return format(
                /* translators: 1: selected first date; 2: normal period start. */
                __('Første kursdato %1$s er før kursperiodens start %2$s. Kryss av «Tillat kursstart før kursperioden» under Flere valg hvis dette er et bevisst unntak, eller velg en dato fra periodens start.', 'reginor-lite'), value, context.periodStart);
            if (context.min && value < context.min) return format(
                /* translators: 1: valgt dato, 2: tidligste tillatte dato (ÅÅÅÅ-MM-DD). */
                __('Datoen %1$s er før tidligste tillatte dato %2$s. Velg denne datoen eller en senere dato innenfor tillatt tidsrom.', 'reginor-lite'), value, context.min);
            if (context.max && value > context.max) return format(
                /* translators: 1: valgt dato, 2: siste tillatte dato (ÅÅÅÅ-MM-DD). */
                __('Datoen %1$s er etter siste tillatte dato %2$s. Velg en tidligere dato, eller endre sluttdatoen i kursoppsettet eller kursperioden først.', 'reginor-lite'), value, context.max);
            if (field.key === 'first_date' && context.weekday && (new Date(value + 'T12:00:00Z').getUTCDay() || 7) !== +context.weekday) return format(
                /* translators: 1: valgt dato, 2: valgt ukedag. */
                __('Første kursdato %1$s har en annen ukedag enn valgt undervisningsdag (%2$s). Endre datoen eller ukedagen slik at de samsvarer.', 'reginor-lite'), value, context.weekdayLabel || context.weekday);
            if (context.after && value < context.after) return format(
                /* translators: 1: valgt sluttdato, 2: startdato. */
                __('Sluttdatoen %1$s er før startdatoen %2$s. Velg samme dato eller en senere sluttdato.', 'reginor-lite'), value, context.after);
        }
        if (field.key === 'timezone') {
            try { new Intl.DateTimeFormat('nb', { timeZone: value }); } catch (_) { return __('Bruk en gyldig tidssone, for eksempel Europe/Oslo.', 'reginor-lite'); }
        }
        if (field.type === 'time' && !/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value)) return __('Skriv klokkeslett, for eksempel 18:00.', 'reginor-lite');
        if (field.key === 'end_time' && context.startTime && value <= context.startTime) return format(
                /* translators: 1: sluttklokkeslett, 2: startklokkeslett. */
                __('Slutt %1$s er ikke etter start %2$s. Velg et senere sluttklokkeslett samme dag, for eksempel 19:00 ved start 18:00.', 'reginor-lite'), value, context.startTime);
        if (field.type === 'datetime-local' && context.after && value <= context.after) return __('Slutten av tidsvinduet må være etter starten.', 'reginor-lite');
        if (field.type === 'number' && (!/^\d+$/.test(value) || +value < 1 || +value > 104)) return __('Velg et helt antall fra 1 til 104, for eksempel 6.', 'reginor-lite');
        if (field.key.endsWith('price_minor') && (!/^\d{1,7}([.,]\d{1,2})?$/.test(value) || +value.replace(',', '.') > 1000000)) return __('Skriv kroner uten tusenskille, for eksempel 1200 eller 1200,50 (maks 1 000 000).', 'reginor-lite');
        if (field.key === 'course_id' && value === '0') return __('Velg en kursbeskrivelse, for eksempel Salsa nybegynner.', 'reginor-lite');
        if (field.key === 'room_id' && value === '0' && context.importing) return __('Sal mangler. LetsReg bestemmer ikke lokal sal. Velg salen der kurset skal holdes under Undervisning.', 'reginor-lite');
        if (field.key === 'registration_url') {
            try {
                const url = new URL(value);
                if (url.protocol !== 'https:' || url.username || url.password || /\s|\\/.test(value)) throw new Error('url');
            } catch (_) { return __('Bruk en full HTTPS-lenke, for eksempel https://www.letsreg.com/event/ditt-kurs.', 'reginor-lite'); }
        }
        return '';
    }
    function registrationNote(value, status, hosts) {
        if (status === 'dropin') return '';
        if (status === 'external') return !value.trim() ? __('Legg inn en full HTTPS-lenke til påmelding før publisering.', 'reginor-lite') : '';
        if (!value.trim()) return ['automatic', 'available', 'waiting'].includes(status) ? __('LetsReg-lenke mangler. Kladden kan lagres, men lenken må fylles ut før publisering med påmelding eller venteliste.', 'reginor-lite') : '';
        try {
            const url = new URL(value);
            if (!hosts.includes(url.hostname.toLowerCase()) || (url.port && url.port !== '443')) return __('Før publisering: bruk et godkjent LetsReg-domene og vanlig HTTPS-port. Eksempel: https://www.letsreg.com/event/ditt-kurs.', 'reginor-lite');
        } catch (_) { /* The field error explains malformed URLs. */ }
        return '';
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { validDate, check, registrationNote };
    if (typeof document === 'undefined') return;
    document.querySelectorAll('.rnl-edit-form').forEach((form) => {
        form.noValidate = true;
        const scope = form.closest('[data-rnl-course-editor]') || form;
        const get = (key) => scope.querySelector('[data-field="' + key + '"]');
        const value = (key) => get(key)?.value || '';
        const isPeriod = scope.querySelector('[name="kind"]')?.value === 'period';
        const touched = new WeakSet();
        const hosts = JSON.parse(form.dataset.registrationHosts || '[]');
        const summary = document.createElement('div');
        summary.className = 'rnl-validation-summary'; summary.hidden = true;
        summary.tabIndex = -1; summary.setAttribute('role', 'alert'); form.prepend(summary);
        let attempted = false;
        function focusField(input) {
            for (let parent = input.parentElement; parent; parent = parent.parentElement) { if (parent.tagName === 'DETAILS') parent.open = true; }
            if (!window.RegiNorRichText?.focus(input)) input.focus();
        }
        function label(input) {
            const section = input.closest('.rnl-field-section');
            const heading = section?.querySelector('h2,h3,legend')?.textContent;
            const name = input.labels?.[0]?.textContent.trim() || input.name;
            return heading ? heading + ' – ' + name : name;
        }
        function showSummary(errors) {
            summary.replaceChildren(); summary.hidden = !errors.length;
            if (!errors.length) return;
            const heading = document.createElement('h3'); heading.textContent = __('Dette må rettes før du fortsetter', 'reginor-lite'); summary.append(heading);
            const list = document.createElement('ul');
            errors.forEach(({ input, message, label: name }) => {
                const item = document.createElement('li'); const link = document.createElement('button');
                link.type = 'button'; link.className = 'rnl-validation-link'; link.textContent = name + ': ' + message;
                link.addEventListener('click', () => focusField(input)); item.append(link); list.append(item);
            });
            summary.append(list);
        }
        const bounds = () => {
            let min = isPeriod ? value('start_date') : form.dataset.periodStart;
            const approved = scope.querySelector('[type="checkbox"][name="data[allow_early_start]"]')?.checked;
            const first = get('first_date') ? (approved ? value('first_date') : '') : form.dataset.courseStart;
            if (!isPeriod && validDate(first || '') && first < min) min = first;
            return { min, max: isPeriod ? value('end_date') : form.dataset.periodEnd };
        };
        function context(input) {
            const key = input.dataset.field;
            const result = bounds();
            result.importing = !!form.closest('[data-rnl-import-workspace]');
            if (isPeriod && ['start_date', 'end_date'].includes(key)) { result.min = ''; result.max = ''; }
            if (key === 'end_date') result.after = value('start_date');
            if (key === 'latest_date') result.after = value('first_date') || value('start_date') || form.dataset.periodStart;
            if (key === 'first_date') {
                result.min = '';
                result.periodStart = form.dataset.periodStart;
                result.allowEarlyStart = !!scope.querySelector('[type="checkbox"][name="data[allow_early_start]"]')?.checked;
                result.weekday = value('weekday'); result.weekdayLabel = get('weekday')?.selectedOptions?.[0]?.textContent;
                if (value('latest_date')) result.max = result.max ? [result.max, value('latest_date')].sort()[0] : value('latest_date');
            }
            if (key === 'until') result.after = input.closest('[data-break-row]').querySelector('[data-field="from"]').value;
            if (key === 'end_time') result.startTime = value('start_time');
            if (['visible_until', 'sales_until', 'registration_until'].includes(key)) result.after = value(key.replace('until', 'from'));
            return result;
        }
        function validate(all = false) {
            window.RegiNorRichText?.save(form);
            const dropin = scope.querySelector('[type="checkbox"][name="data[dropin_enabled]"]');
            if (get('dropin_price_minor')) get('dropin_price_minor').required = !!dropin?.checked || value('registration_status') === 'dropin';
            scope.querySelectorAll('[data-break-row]').forEach((row) => {
                const removed = row.querySelector('[type="checkbox"]').checked;
                row.classList.toggle('is-removed', removed);
                const fields = row.querySelectorAll('[data-field]');
                const used = [...fields].some((input) => input.value.trim());
                fields.forEach((input) => { input.disabled = removed; input.required = used && ['from', 'reason'].includes(input.dataset.field); });
            });
            const errors = [];
            scope.querySelectorAll('[data-field]').forEach((input) => {
                if (input.form !== form) return;
                const ctx = context(input);
                if (input.type === 'date') { input.min = ctx.min || ''; input.max = ctx.max || ''; }
                const message = input.disabled ? '' : check({ value: input.hasAttribute('data-rnl-rich-text') ? input.value.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() : input.value, key: input.dataset.field, type: input.type, required: input.required, badInput: input.validity.badInput }, ctx);
                input.setCustomValidity(message);
                if (message) errors.push({ input, label: label(input), message });
                const error = document.getElementById(input.id + '-error');
                const note = input.dataset.field === 'registration_url' ? registrationNote(input.value, value('registration_status'), hosts) : '';
                const display = all || attempted || touched.has(input) || (input.value !== '' && ['date', 'time', 'datetime-local'].includes(input.type));
                if (error) {
                    error.textContent = display && message ? message : note;
                    error.classList.toggle('rnl-field-note', !message && !!note);
                }
                input.setAttribute('aria-invalid', display && !!message ? 'true' : 'false');
            });
            errors.push(...(form.rnlExtraValidation?.(all || attempted) || []));
            if (all) attempted = true;
            if (attempted) showSummary(errors);
            return errors;
        }
        form.rnlValidation = { validate: () => validate(true), focus: () => summary.hidden || focusField(summary), reset: () => { attempted = false; summary.hidden = true; } };
        ['input', 'change', 'focusout'].forEach((eventName) => scope.addEventListener(eventName, (event) => {
            if (event.target.form !== form) return;
            if (event.target.matches('[data-field]')) touched.add(event.target);
            validate();
        }));
        form.addEventListener('submit', (event) => {
            const errors = validate(true);
            if (!errors.length) return;
            event.preventDefault();
            focusField(errors[0].input);
        });
        scope.querySelectorAll('.rnl-breaks').forEach((section) => {
            const fallback = section.querySelector('[data-break-fallback]');
            const sample = fallback.querySelector('[data-break-row]').cloneNode(true);
            const rows = section.querySelector('[data-break-rows]');
            const button = section.querySelector('[data-add-break]');
            const status = section.querySelector('[data-break-status]');
            let serial = rows.children.length;
            fallback.remove(); button.hidden = false;
            button.addEventListener('click', () => {
                if (rows.querySelectorAll('[data-break-row]:not(.is-removed)').length >= 100) {
                    status.textContent = __('Du kan ha opptil 100 opphold. Fjern et opphold før du legger til flere.', 'reginor-lite'); return;
                }
                const row = sample.cloneNode(true);
                // Unique names, label targets and description IDs even after removing a row.
                row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/\[breaks\]\[\d+\]/, '[breaks][' + serial + ']'); });
                row.querySelectorAll('[id], [for], [aria-describedby]').forEach((node) => {
                    ['id', 'for', 'aria-describedby'].forEach((attr) => { if (node.hasAttribute(attr)) node.setAttribute(attr, node.getAttribute(attr).replace(/rnl-field-\d+/g, (id) => id + '-break-' + serial)); });
                });
                serial++; rows.append(row); validate(); row.querySelector('[data-field="from"]').focus();
                status.textContent = __('Nytt opphold er klart. Fyll ut fra-dato og forklaring; til-dato er valgfri.', 'reginor-lite');
            });
        });
        validate();
    });
}());

/* Reapply explicit name suggestions without replacing categories that already have a role. */
(function () {
    if (typeof document === 'undefined') return;
    const __ = window.wp.i18n.__;
    window.RegiNorCategorySuggestions = function (root) {
        if (!root) return;
        if (root.rnlRefreshSuggestions) { root.rnlRefreshSuggestions(); return; }
        const button = root.querySelector('[data-fill-category-suggestions]');
        const status = root.querySelector('[data-category-suggestion-status]');
        const remaining = () => [...root.querySelectorAll('[data-category-role]')].filter(row => {
            const role = row.querySelector('select[name$="[role]"]');
            const registration = row.querySelector('select[name$="[registration]"]');
            return role && registration && !role.disabled && !registration.disabled && role.value === '';
        });
        const refresh = () => { button.hidden = remaining().length === 0; };
        root.rnlRefreshSuggestions = refresh;
        root.addEventListener('change', refresh);
        button.addEventListener('click', () => {
            const rows = remaining();
            rows.forEach(row => {
                const role = row.querySelector('select[name$="[role]"]');
                const registration = row.querySelector('select[name$="[registration]"]');
                role.value = row.dataset.categoryRole; registration.value = row.dataset.categoryRegistration;
                role.dispatchEvent(new Event('change', { bubbles: true }));
                registration.dispatchEvent(new Event('change', { bubbles: true }));
            });
            /* translators: %d: number of categories filled from name suggestions. */
            status.textContent = __('Forslag er fylt inn for %d kategorier. Kategorier som allerede hadde en rolle, er beholdt. Kontroller valgene før lagring.', 'reginor-lite').replace('%d', rows.length);
            status.tabIndex = -1; status.focus(); refresh();
        });
        refresh();
    };
    document.querySelectorAll('[data-category-suggestions]').forEach(window.RegiNorCategorySuggestions);
}());

/* Descriptions stay visible on hover/focus, are dismissible, and do not clip in tables. */
(function () {
    if (typeof document === 'undefined' || typeof HTMLElement.prototype.showPopover !== 'function') return;
    document.querySelectorAll('.rnl-action-wrap').forEach((wrap) => {
        const trigger = wrap.querySelector('a,button'); const tip = wrap.querySelector('[role="tooltip"]');
        if (!trigger || !tip) return;
        wrap.dataset.rnlTooltip = '1'; tip.setAttribute('popover', 'manual'); let timer;
        const hide = () => { clearTimeout(timer); if (tip.matches(':popover-open')) tip.hidePopover(); };
        const show = () => {
            clearTimeout(timer); if (!tip.matches(':popover-open')) tip.showPopover();
            const rect = trigger.getBoundingClientRect(); const box = tip.getBoundingClientRect();
            tip.style.left = Math.max(12, Math.min(rect.right - box.width, window.innerWidth - box.width - 12)) + 'px';
            tip.style.top = (rect.bottom + box.height + 8 < window.innerHeight ? rect.bottom + 6 : Math.max(8, rect.top - box.height - 6)) + 'px';
        };
        wrap.addEventListener('mouseenter', show); wrap.addEventListener('mouseleave', () => { timer = setTimeout(() => { if (!wrap.contains(document.activeElement)) hide(); }, 150); });
        trigger.addEventListener('focus', show); trigger.addEventListener('blur', hide);
        if (trigger.tagName === 'BUTTON') trigger.addEventListener('click', show);
        wrap.addEventListener('keydown', (event) => { if (event.key === 'Escape') { hide(); event.stopPropagation(); } });
        window.addEventListener('scroll', hide, true); window.addEventListener('resize', hide);
    });
}());
