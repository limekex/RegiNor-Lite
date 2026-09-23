/* Import review stays in the page. Only the final confirmation creates WordPress objects. */
(function () {
    'use strict';
    const __ = window.wp.i18n.__;
    window.RegiNorImport = function (api) {
        const { workspace, editor, mapping, importPreview, request, announce, select } = api;
        const panel = workspace.querySelector('[data-import-batch]');
        const mode = editor.elements.description_mode;
        const chosen = new Map();
        const staged = new Map();
        let source = null;
        let finished = false;
        let reviewing = false;
        editor.hidden = true;
        const field = name => editor.elements.namedItem(name);
        const make = (tag, text, className) => { const el = document.createElement(tag); if (text) el.textContent = text; if (className) el.className = className; return el; };
        function button(text, handler) {
            const el = make('button', text, 'rnl-button rnl-button-secondary'); el.type = 'button'; el.addEventListener('click', handler); return el;
        }
        function descriptionMode() {
            if (!mode) return;
            const isNew = mode.value === 'new';
            for (const [selector, enabled] of [['[data-new-description]', isNew], ['[data-existing-description]', !isNew]]) {
                const box = editor.querySelector(selector); box.hidden = !enabled;
                box.querySelectorAll('input, textarea, select').forEach(input => { input.disabled = !enabled; });
            }
        }
        mode?.addEventListener('change', descriptionMode); descriptionMode();
        // Category choices belong to the separate LetsReg form, but are required for import.
        const categoryError = make('p', '', 'rnl-field-error');
        categoryError.id = editor.id + '-category-error'; categoryError.hidden = true;
        mapping.querySelector('[data-categories]').after(categoryError);
        editor.rnlExtraValidation = (show) => {
            const roles = [...mapping.querySelectorAll('select[name$="[role]"]')].filter(input => !input.disabled);
            const missing = !mapping.hidden && roles.length && !roles.some(input => input.value);
            const message = __('Ingen priskategori er valgt. Velg Fører, Følger eller Uten rollefordeling under Rolle for minst én kategori som gjelder kurset. «Ikke med på dette kurset» utelater kategorien fra koblingen.', 'reginor-lite');
            categoryError.textContent = missing && show ? message : ''; categoryError.hidden = !missing || !show;
            roles.forEach(input => {
                input.setAttribute('aria-invalid', missing && show ? 'true' : 'false');
                const ids = new Set((input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
                ids.add(categoryError.id); input.setAttribute('aria-describedby', [...ids].join(' '));
            });
            return missing ? [{ input: roles[0], label: __('Priskategorier hos LetsReg', 'reginor-lite'), message }] : [];
        };
        mapping.addEventListener('change', () => editor.rnlValidation?.validate());
        function explainOpenCourse() {
            if (editor.rnlValidation?.validate().length) {
                editor.rnlValidation.focus(); return;
            }
            if (!importPreview.hidden) {
                const stage = importPreview.querySelector('[data-stage-import]');
                if (stage) {
                    announce(__('Kurset er forhåndsvist, men er ikke lagt i importlisten. Trykk «Legg i importlisten» under forhåndsvisningen før du går videre til et annet kurs. Ingenting er lagret ennå.', 'reginor-lite'));
                    stage.focus(); return;
                }
                announce(__('Kurset kan ikke legges i importlisten ennå. Se feilene i forhåndsvisningen, rett oppsettet og trykk «Forhåndsvis kursutkastet» igjen.', 'reginor-lite'), true);
                importPreview.focus(); return;
            }
            announce(__('Det åpne kurset mangler en oppdatert forhåndsvisning. Trykk «Forhåndsvis kursutkastet» for å kontrollere datoer og kursfrie dager. Deretter kan du legge kurset i importlisten.', 'reginor-lite'));
            editor.querySelector('button[type="submit"], button:not([type])')?.focus();
        }
        function snapshot(form) {
            window.RegiNorRichText?.save(form);
            return [...form.elements].filter(el => el.name).map(el => ({ name: el.name, type: el.type, value: el.value, checked: el.checked }));
        }
        function restore(form, values) {
            const controls = [...form.elements];
            for (const saved of values) {
                const el = controls.find(input => input.name === saved.name && input.type === saved.type && (!['checkbox', 'radio'].includes(input.type) || input.value === saved.value));
                if (!el) continue;
                if (['checkbox', 'radio'].includes(el.type)) el.checked = saved.checked;
                else if (window.RegiNorRichText) window.RegiNorRichText.set(el, saved.value); else el.value = saved.value;
            }
        }
        async function next() {
            if (reviewing) { explainOpenCourse(); return; }
            const id = chosen.keys().next().value;
            if (!id) return;
            const ok = await select(id);
            if (ok) { reviewing = true; render(); }
        }
        function render() {
            if (!panel) return;
            workspace.querySelectorAll('[data-bulk-event]').forEach(input => {
                input.checked = staged.has(input.dataset.bulkEvent) || chosen.has(input.dataset.bulkEvent);
                input.disabled = staged.has(input.dataset.bulkEvent);
            });
            panel.replaceChildren(); panel.hidden = !staged.size && !chosen.size;
            panel.append(make('h3', __('Importlisten', 'reginor-lite')));
            panel.append(make('p', __('Klare kurs: ', 'reginor-lite') + staged.size + ' · ' + __('Gjenstår å kontrollere: ', 'reginor-lite') + chosen.size));
            panel.append(make('p', __('Utkastene og eventuelle nye beskrivelser opprettes samlet. Ved feil lagres ingen av dem. Kontroller listen innen 15 minutter etter henting.', 'reginor-lite'), 'rnl-help'));
            for (const [id, item] of staged) {
                const row = make('details', '', 'rnl-import-item'); row.append(make('summary', item.title));
                const review = make('div', '', 'rnl-import-review'); review.innerHTML = item.html;
                review.querySelectorAll('button').forEach(el => el.remove()); row.append(review);
                row.append(button(__('Rediger: ', 'reginor-lite') + item.title, async () => {
                    if (reviewing) { explainOpenCourse(); return; }
                    // Refresh expired source receipts while retaining the user's reviewed local fields.
                    if (!await select(id)) return;
                    const breaks = editor.querySelector('[data-break-rows]'); if (breaks) breaks.innerHTML = item.breaks;
                    restore(editor, item.fields); restore(mapping, item.categories); descriptionMode();
                    window.RegiNorCategorySuggestions?.(mapping.querySelector('[data-category-suggestions]'));
                    mode?.dispatchEvent(new Event('change', { bubbles: true }));
                    staged.delete(id); chosen.set(id, item.title); reviewing = true; render(); editor.scrollIntoView?.({ block: 'start' });
                }));
                row.append(button(__('Fjern: ', 'reginor-lite') + item.title, () => { staged.delete(id); render(); }));
                panel.append(row);
            }
            if (chosen.size) {
                panel.append(button(__('Kontroller neste valgte kurs', 'reginor-lite'), next));
                const waiting = make('details'); waiting.append(make('summary', __('Valgte arrangementer som gjenstår', 'reginor-lite')));
                for (const [id, name] of chosen) waiting.append(button(__('Ta ut av utvalget: ', 'reginor-lite') + name, () => { chosen.delete(id); render(); }));
                panel.append(waiting);
            }
            const confirm = button(__('Opprett alle kursutkast', 'reginor-lite'), async () => {
                if (!staged.size || chosen.size || reviewing) return;
                const body = new FormData();
                for (const item of staged.values()) body.append('proposals[]', item.proposal);
                const data = await request('confirm_import_batch', body);
                if (!data) return;
                finished = true; api.completed();
                const destination = new URL(data.redirect, window.location.href);
                if (destination.origin === window.location.origin) window.location.assign(destination.href);
                else announce(__('Kursutkastene er opprettet. Åpne kursperioden for å se dem.', 'reginor-lite'));
            });
            confirm.className = 'rnl-button';
            confirm.disabled = !staged.size || !!chosen.size || reviewing;
            panel.append(confirm);
        }
        return {
            cancelled: () => { reviewing = false; editor.hidden = true; render(); },
            receipt: () => source?.receipt || '',
            hasPending: () => !finished && (staged.size > 0 || chosen.size > 0),
            renderChoices(container, events) {
                const details = make('details'); details.append(make('summary', __('Velg flere kurs til samlet import', 'reginor-lite')));
                details.append(make('p', __('Kryss av for opptil 20 arrangementer. Du kontrollerer beskrivelse, sal, datoer og priskategorier for hvert kurs før samlet opprettelse.', 'reginor-lite'), 'rnl-help'));
                for (const event of events) {
                    if (!event.active || event.isCancelled) continue;
                    const label = make('label', '', 'rnl-bulk-choice');
                    const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = chosen.has(String(event.id)) || staged.has(String(event.id));
                    checkbox.disabled = staged.has(String(event.id)); checkbox.dataset.bulkEvent = String(event.id);
                    checkbox.addEventListener('change', () => {
                        const id = String(event.id);
                        if (checkbox.checked && chosen.size + staged.size >= 20 && !chosen.has(id)) {
                            checkbox.checked = false; announce(__('Velg opptil 20 kurs per import.', 'reginor-lite'), true); return;
                        }
                        if (checkbox.checked) chosen.set(id, event.name); else chosen.delete(id);
                        render();
                    });
                    label.append(checkbox, document.createTextNode(' ' + event.name + ' · ' + (event.start_label || __('Oppstart ikke oppgitt', 'reginor-lite'))));
                    details.append(label);
                }
                details.append(button(__('Kontroller valgte kurs', 'reginor-lite'), next)); container.append(details);
            },
            selected(data) {
                const changedEvent = source && source.event_id !== data.event_id;
                const fillDescription = !source || changedEvent;
                if (changedEvent) {
                    // Reset previous course-specific choices. API suggestions arrive immediately after this hook.
                    editor.reset(); window.RegiNorRichText?.refresh(editor); editor.querySelector('[data-break-rows]')?.replaceChildren();
                    field('data[registration_url]').value = data.event_url || '';
                }
                source = data; editor.hidden = false; reviewing = true;
                editor.rnlValidation?.reset(); categoryError.hidden = true;
                if (mode) {
                    field('new_course[title]').value = data.event_name || '';
                    const parsed = data.description_template;
                    const values = parsed?.mode === 'structured' ? parsed.fields : { description: data.description || '' };
                    for (const key of ['description', 'dance_style', 'level_description', 'partner_info']) {
                        const input = field('new_course[' + key + ']');
                        if (input && fillDescription) {
                            if (window.RegiNorRichText) window.RegiNorRichText.set(input, values[key] || ''); else input.value = values[key] || '';
                        }
                    }
                    let note = editor.querySelector('[data-template-message]');
                    if (!note) {
                        note = make('p', '', 'rnl-notice'); note.dataset.templateMessage = ''; note.setAttribute('role', 'status');
                        editor.querySelector('[data-new-description]').prepend(note);
                    }
                    note.textContent = data.description_template_message || ''; note.hidden = !note.textContent;
                    descriptionMode();
                }
                render();
            },
            preview(data) {
                const confirm = importPreview.querySelector('[data-confirm-import]');
                if (!confirm) return; // Invalid dates cannot enter the batch.
                if (staged.size || chosen.size) confirm.hidden = true;
                const stage = button(__('Legg i importlisten', 'reginor-lite'), () => {
                    if (importPreview.hidden || !source?.receipt) return;
                    const id = String(source.event_id);
                    if (!staged.has(id) && staged.size >= 20) { announce(__('Velg opptil 20 kurs per import.', 'reginor-lite'), true); return; }
                    staged.set(id, { proposal: data.proposal, html: data.html, source, title: field('data[title]').value,
                        fields: snapshot(editor), categories: snapshot(mapping), breaks: editor.querySelector('[data-break-rows]')?.innerHTML || '' });
                    chosen.delete(id); reviewing = false; api.invalidate(); api.completed();
                    editor.hidden = true; mapping.hidden = true; render(); panel?.focus();
                    announce(__('Kurset er lagt i importlisten. Ingen kurs er lagret ennå.', 'reginor-lite'));
                });
                stage.dataset.stageImport = ''; importPreview.append(stage);
            }
        };
    };
}());
