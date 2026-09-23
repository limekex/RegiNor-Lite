/* Course linking stays on the editor page; unrelated form values are never replaced. */
(function () {
    'use strict';
    const __ = window.wp.i18n.__;
    function mount(root) {
        const search = root.querySelector('[data-search-form]');
        const mapping = root.querySelector('[data-mapping-form]');
        const results = root.querySelector('[data-results]');
        const message = root.querySelector('[data-message]');
        const importing = root.dataset.mode === 'import';
        const workspace = importing ? root.closest('[data-rnl-import-workspace]') : null;
        const editor = importing ? document.getElementById('rnl-course-editor') : null;
        const importPreview = workspace?.querySelector('[data-import-preview]');
        let importProposal = null;
        function invalidateImport() { importProposal = null; if (importPreview) importPreview.hidden = true; }
        let busy = false;
        let pending = false;
        let originalUrl = null;
        let suggestedUrl = null;
        let batch = null;
        const urlField = [...document.querySelectorAll('form')].find((form) => form.querySelector('input[name="id"]')?.value === root.dataset.id && form.querySelector('[name="data[registration_url]"]'))?.querySelector('[name="data[registration_url]"]');
        function restoreUrl() {
            if (urlField && originalUrl !== null && urlField.value === suggestedUrl) {
                urlField.value = originalUrl;
                urlField.dispatchEvent(new Event('input', { bubbles: true }));
            }
            originalUrl = null; suggestedUrl = null;
        }
        function announce(text, error = false) {
            message.textContent = text;
            message.setAttribute('role', error ? 'alert' : 'status');
            if (error) message.focus();
        }
        async function request(operation, form, extra = {}) {
            if (busy) return null;
            busy = true;
            const controls = [...(workspace || root).querySelectorAll('button, input, select, textarea')].filter((el) => !el.disabled);
            const body = form instanceof FormData ? form : (form ? new FormData(form) : new FormData());
            for (const [key, value] of Object.entries({ action: 'rnl_letsreg_course', nonce: root.dataset.nonce, id: root.dataset.id, version: root.dataset.version, mode: importing ? 'import' : 'course', operation, ...extra })) body.set(key, value);
            controls.forEach((el) => { el.disabled = true; });
            root.setAttribute('aria-busy', 'true');
            announce(operation === 'search' ? __('Søker hos LetsReg …', 'reginor-lite') : operation === 'select' ? __('Henter kategorier …', 'reginor-lite') : operation === 'preview_import' ? __('Kontrollerer kursutkastet …', 'reginor-lite') : operation.startsWith('confirm_import') ? __('Oppretter kursutkast …', 'reginor-lite') : __('Lagrer koblingen …', 'reginor-lite'));
            try {
                const response = await fetch(root.dataset.url, { method: 'POST', credentials: 'same-origin', body });
                const json = await response.json();
                if (!response.ok || !json.success) throw new Error(json.data?.message || __('Handlingen kunne ikke fullføres. Valgene dine er beholdt.', 'reginor-lite'));
                return json.data;
            } catch (error) {
                announce(error.message || __('Vi fikk ikke kontakt. Valgene dine er beholdt; prøv igjen.', 'reginor-lite'), true);
                return null;
            } finally {
                controls.forEach((el) => { el.disabled = false; });
                root.removeAttribute('aria-busy');
                busy = false;
            }
        }
        function button(label, onClick) {
            const el = document.createElement('button');
            el.type = 'button'; el.className = 'rnl-button rnl-button-secondary'; el.textContent = label;
            el.addEventListener('click', onClick);
            return el;
        }
        let searchPage = null;
        let found = [];
        let searchQuery = '';
        const periodOnly = search.elements.period_only;
        function renderResults() {
            if (!searchPage) return;
            const selectedId = results.querySelector('select')?.value || '';
            results.replaceChildren();
            const period = searchPage.period;
            const visible = found.filter(event => !periodOnly?.checked || (period && event.start_on
                && event.start_on >= period.start && (!period.end || event.start_on <= period.end)));
            const label = document.createElement('label'); label.htmlFor = 'rnl-letsreg-result';
            label.textContent = __('Velg arrangement', 'reginor-lite');
            const selectBox = document.createElement('select'); selectBox.id = label.htmlFor;
            selectBox.className = 'rnl-letsreg-select';
            selectBox.setAttribute('aria-describedby', 'rnl-letsreg-result-help');
            const placeholder = document.createElement('option'); placeholder.value = '';
            placeholder.textContent = visible.length ? __('Velg et treff …', 'reginor-lite') : __('Ingen treff å velge', 'reginor-lite');
            selectBox.append(placeholder);
            for (const event of visible) {
                const option = document.createElement('option'); option.value = String(event.id);
                option.textContent = (event.name || __('Arrangement uten navn', 'reginor-lite')) + ' · '
                    + (event.start_label ? __('Oppstart: ', 'reginor-lite') + event.start_label : __('Oppstart ikke oppgitt', 'reginor-lite')) + ' · #' + event.id;
                if (!event.active || event.isCancelled) {
                    option.disabled = true;
                    option.textContent += ' · ' + (event.isCancelled ? __('Avlyst', 'reginor-lite') : __('Inaktivt', 'reginor-lite'));
                }
                selectBox.append(option);
            }
            selectBox.disabled = !visible.length;
            if (visible.some(event => String(event.id) === selectedId)) selectBox.value = selectedId;
            // Native select closes on selection and provides keyboard/screen-reader behavior without a custom listbox.
            selectBox.addEventListener('change', async () => {
                if (!selectBox.value) return;
                const ok = await select(selectBox.value);
                if (!ok) selectBox.value = '';
            });
            const help = document.createElement('p'); help.id = 'rnl-letsreg-result-help'; help.className = 'rnl-help';
            help.textContent = visible.length
                ? __('Velg arrangementet som hører til kurset. Oppstart vises i kursperiodens tidssone.', 'reginor-lite')
                : (searchPage.has_more ? __('Ingen passende treff blant de hentede arrangementene. Hent flere treff eller slå av periodefilteret.', 'reginor-lite') : __('Ingen passende treff. Endre søket eller slå av periodefilteret.', 'reginor-lite'));
            results.append(label, selectBox, help);
            batch?.renderChoices(results, visible);
            if (searchPage.has_more && searchPage.offset < 10000) {
                results.append(button(__('Hent flere treff', 'reginor-lite'), () => find(searchQuery, searchPage.offset + 20)));
            }
            announce(help.textContent);
        }
        async function find(query, offset = 0) {
            const data = await request('search', null, { query, offset });
            if (!data) return;
            if (offset === 0) { found = []; results.replaceChildren(); }
            const seen = new Set(found.map(event => event.id));
            found.push(...data.events.filter(event => !seen.has(event.id)));
            searchPage = { ...data, offset }; searchQuery = query;
            renderResults();
            if (!results.querySelector('select').disabled) results.querySelector('select').focus();
        }
        periodOnly?.addEventListener('change', renderResults);
        document.getElementById('rnl-course-editor')?.addEventListener('change', event => {
            if (event.target.name !== 'data[level_id]') return;
            const choice = mapping.querySelector('[data-suggestion="level_id"]');
            if (choice) choice.checked = false; // Preserve a manual choice made after suggestions were fetched.
        });
        function suggestions(data) {
            const container = mapping.querySelector('[data-course-suggestions]');
            const editor = document.getElementById('rnl-course-editor');
            if (!container || !editor || !data) return;
            container.replaceChildren(); container.hidden = false;
            const details = document.createElement('details');
            const summary = document.createElement('summary'); summary.textContent = __('Fyll ut kursoppsettet fra LetsReg', 'reginor-lite');
            const info = document.createElement('p'); info.className = 'rnl-help';
            info.textContent = __('Velg forslagene du vil bruke. De erstatter de valgte feltene i skjemaet og lagres med kursoppsettet. Sal og øvrige felt beholdes. Tidspunkt uten tidssone tolkes i kursets tidssone: ', 'reginor-lite') + data.timezone;
            const list = document.createElement('div'); list.className = 'rnl-field-grid';
            const choices = new Map();
            const labels = {
                price_terms: __('Prisvilkår og tillegg', 'reginor-lite'), title: __('Navn', 'reginor-lite'), level_id: __('Kursnivå', 'reginor-lite'), weekday: __('Ukedag', 'reginor-lite'), start_time: __('Start', 'reginor-lite'), end_time: __('Slutt', 'reginor-lite'),
                first_date: __('Første kursdato', 'reginor-lite'), session_count: __('Antall undervisningskvelder', 'reginor-lite'),
                registration_from: __('Påmelding åpner', 'reginor-lite'), registration_until: __('Påmelding stenger', 'reginor-lite'), registration_status: __('Påmeldingsstatus', 'reginor-lite')
            };
            const field = key => editor.elements.namedItem('data[' + key + ']');
            function add(key, value) {
                if (!field(key) || !labels[key]) return;
                const target = field(key);
                if (key === 'level_id' && ![...target.options].some(option => option.value === String(value))) return;
                const row = document.createElement('p'); row.className = 'rnl-field-card';
                const label = document.createElement('label'); const checkbox = document.createElement('input');
                checkbox.type = 'checkbox'; checkbox.checked = true; checkbox.dataset.suggestion = key;
                if (['registration_from', 'registration_until'].includes(key) && data.fields?.registration_status === 'automatic') checkbox.checked = false;
                if (key === 'registration_status' && !['unknown', 'automatic', ''].includes(target.value)) checkbox.checked = false;
                if (key === 'level_id') checkbox.checked = !target.value || target.value === '0';
                if (key === 'price_terms') checkbox.checked = !target.value;
                const readable = target.tagName === 'SELECT' ? [...target.options].find(option => option.value === String(value))?.textContent || value : String(value).replace('T', ' ');
                label.append(checkbox, document.createTextNode(' ' + labels[key] + ': ' + readable)); row.append(label); list.append(row);
                choices.set(key, { checkbox, value: String(value) });
            }
            Object.entries(data.fields || {}).forEach(([key, value]) => add(key, value));
            if (data.fields?.registration_status === 'automatic') {
                const note = document.createElement('p'); note.className = 'rnl-help';
                note.textContent = __('Automatisk status følger oppdaterte salgsdatoer og kapasitet hos LetsReg. Datoforslagene er ikke krysset av: bruk dem bare hvis du ønsker faste lokale begrensninger. En allerede valgt manuell status beholdes.', 'reginor-lite');
                list.append(note);
            }
            if (field('level_id')) {
                const note = document.createElement('p'); note.className = 'rnl-help';
                note.textContent = choices.has('level_id')
                    ? __('Nivåforslaget kommer fra arrangementsnavnet og nivåutvalget i Kursinnhold og ressurser. Et nivå du allerede har valgt, beholdes. Kryss av for nivåforslaget hvis du vil erstatte valget.', 'reginor-lite')
                    : __('Arrangementsnavnet ga ikke ett entydig treff blant tilgjengelige kursnivåer. Velg Kursnivå under Undervisning, eller opprett nivået i Kursinnhold og ressurser.', 'reginor-lite');
                list.append(note);
            }
            function countEvenings() {
                if (!data.start_date || !data.end_date || data.end_date < data.start_date) return null;
                const days = (Date.parse(data.end_date + 'T12:00:00Z') - Date.parse(data.start_date + 'T12:00:00Z')) / 86400000;
                if (!Number.isFinite(days) || days > 7 * 104) return null;
                const breaks = [...(data.period_breaks || [])];
                for (const row of editor.closest('[data-rnl-course-editor]')?.querySelectorAll('[data-break-row]') || []) {
                    if (row.querySelector('[type="checkbox"]')?.checked) continue;
                    const from = row.querySelector('[data-field="from"]')?.value;
                    const until = row.querySelector('[data-field="until"]')?.value || from;
                    if (from) breaks.push({ from, until });
                }
                let count = 0;
                for (let day = 0; day <= days; day += 7) {
                    const date = new Date(Date.parse(data.start_date + 'T12:00:00Z') + day * 86400000).toISOString().slice(0, 10);
                    if (!breaks.some(b => b.from <= date && date <= b.until)) count++;
                }
                return count >= 1 && count <= 104 ? count : null;
            }
            const count = countEvenings();
            if (count !== null) {
                add('session_count', count);
                const note = document.createElement('p'); note.className = 'rnl-help';
                note.textContent = __('Antallet er et forslag med én kurskveld per uke mellom LetsReg-datoene, uten kursfrie dager. Kontroller datoene i forhåndsvisningen.', 'reginor-lite'); list.append(note);
            }
            function set(key, value) {
                const target = field(key); if (!target) return;
                if (window.RegiNorRichText) window.RegiNorRichText.set(target, value); else target.value = value;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const apply = button(__('Bruk valgte forslag i skjemaet', 'reginor-lite'), () => {
                if (field('timezone')?.value !== data.timezone) {
                    announce(__('Tidssonen er endret. Bruk kursets lagrede tidssone, eller lagre den nye tidssonen før du henter forslag på nytt.', 'reginor-lite'), true); return;
                }
                for (const [key, choice] of choices) {
                    if (!choice.checkbox.checked) continue;
                    if (key === 'session_count') {
                        // Only count the proposed weekly pattern, never mix it with a different manually retained start.
                        const start = choices.get('first_date'); const weekday = choices.get('weekday');
                        if ((!start?.checkbox.checked && field('first_date')?.value !== data.start_date) || (!weekday?.checkbox.checked && field('weekday')?.value !== weekday?.value)) continue;
                        const currentCount = countEvenings(); if (currentCount !== null) set(key, String(currentCount));
                    } else set(key, choice.value);
                }
                announce(__('Valgte forslag er fylt inn. Kontroller feltene og forhåndsvis kursdatoene før du lagrer.', 'reginor-lite'));
            });
            const priceNote = document.createElement('p'); priceNote.className = 'rnl-help';
            const priceButton = button(__('Bruk fra-pris per person', 'reginor-lite'), () => {
                const price = minimum(); if (price === null) return;
                set('price_minor', (price / 100).toFixed(2).replace('.', ',')); set('price_basis', 'person');
                const from = editor.querySelector('[type="checkbox"][name="data[price_from]"]');
                if (from) { from.checked = true; from.dispatchEvent(new Event('change', { bubbles: true })); }
                announce(__('Fra-prisen er fylt inn per person. Kontroller valuta, prisvilkår og eventuelle tillegg før lagring.', 'reginor-lite'));
            });
            function minimum() {
                const selected = [...mapping.querySelectorAll('select[name$="[role]"]')].filter(el => el.value !== '');
                if (!selected.length) return null;
                const amounts = selected.map(el => data.prices?.find(p => String(p.id) === el.name.match(/\[categories\]\[(\d+)\]/)?.[1])?.price_minor);
                return amounts.every(Number.isInteger) ? Math.min(...amounts) : null;
            }
            const refreshPrice = () => {
                const price = minimum(); priceButton.disabled = price === null;
                priceNote.textContent = price === null ? __('Velg kategorier som har pris hos LetsReg for å foreslå en fra-pris.', 'reginor-lite')
                    : __('Laveste pris i valgte kategorier: ', 'reginor-lite') + (price / 100).toFixed(2).replace('.', ',') + '. ' + __('Forslaget bruker NOK og per person, også ved parpåmelding. Kontroller valuta, avgifter og tillegg mot LetsReg.', 'reginor-lite');
            };
            // Replace the handler when another event is selected; old responses must not keep affecting the controls.
            mapping.onchange = refreshPrice; refreshPrice();
            details.append(summary, info, list, apply, priceNote, priceButton); container.append(details);
            if (importing) apply.click();
        }
        async function select(id, checkedData = null) {
            const retained = !mapping.hidden && mapping.elements.event_id.value === String(id)
                ? [...mapping.querySelectorAll('[data-categories] select')].map(el => ({ name: el.name, value: el.value })) : [];
            const data = checkedData || await request('select', null, { event_id: id });
            if (!data) return false;
            invalidateImport();
            restoreUrl();
            const currentUrl = urlField ? urlField.value : root.dataset.registrationUrl;
            suggestedUrl = data.event_url;
            const useUrl = mapping.elements.use_api_url;
            const preview = mapping.querySelector('[data-url-preview]');
            preview.hidden = !suggestedUrl;
            mapping.querySelector('[data-url-missing]').hidden = !!suggestedUrl;
            useUrl.checked = !!suggestedUrl && (!currentUrl || currentUrl === suggestedUrl);
            const link = mapping.querySelector('[data-event-url]');
            link.textContent = suggestedUrl || '';
            if (suggestedUrl) link.href = suggestedUrl; else link.removeAttribute('href');
            mapping.querySelector('[data-url-difference]').textContent = currentUrl && currentUrl !== suggestedUrl
                ? __('Kurset har allerede en annen lenke. Kryss av for å erstatte den ved lagring: ', 'reginor-lite') + currentUrl : '';
            if (urlField && useUrl.checked) {
                originalUrl = currentUrl;
                urlField.value = suggestedUrl;
                urlField.dispatchEvent(new Event('input', { bubbles: true }));
            }
            mapping.elements.event_id.value = data.event_id;
            mapping.elements.verification_id.value = data.verification_id;
            mapping.querySelector('[data-event-name]').textContent = data.event_name || __('Arrangement uten navn', 'reginor-lite');
            // Only escaped markup rendered by our authenticated endpoint, never provider HTML.
            mapping.querySelector('[data-categories]').innerHTML = data.html;
            retained.forEach(({ name, value }) => {
                const control = mapping.elements.namedItem(name);
                if (control && [...control.options].some(option => option.value === value)) control.value = value;
            });
            window.RegiNorCategorySuggestions?.(mapping.querySelector('[data-category-suggestions]'));
            batch?.selected(data);
            suggestions(data.suggestions);
            mapping.hidden = false; pending = true;
            announce(importing ? __('Arrangementet er valgt. Velg kategorier, kursbeskrivelse og sal, og kontroller forslaget nedenfor.', 'reginor-lite') : __('Arrangementet er valgt. Velg kategorier og lagre koblingen.', 'reginor-lite'));
            mapping.querySelector('[data-event-name]').focus();
            return true;
        }
        function saved(data) {
            if (data.registration_url !== null) {
                root.dataset.registrationUrl = data.registration_url;
                if (urlField && (urlField.value === originalUrl || urlField.value === suggestedUrl)) {
                    urlField.value = data.registration_url;
                    urlField.dispatchEvent(new Event('input', { bubbles: true }));
                }
            } else { restoreUrl(); }
            originalUrl = null; suggestedUrl = null;
            root.dataset.version = String(data.version);
            // Refresh only this course's version tokens. Do not reset any unsaved editor values.
            for (const form of document.querySelectorAll('form')) {
                if (form.querySelector('input[name="id"]')?.value === root.dataset.id) {
                    const version = form.querySelector('input[name="version"]');
                    if (version) version.value = String(data.version);
                }
            }
            const choice = results.querySelector('select'); if (choice) choice.value = '';
            root.querySelector('[data-saved-summary]').innerHTML = data.html;
            root.querySelector('[data-remove-panel]').hidden = !data.linked;
            root.querySelector('[data-remove-panel]').open = false;
            root.querySelector('[data-editor]').open = false;
            mapping.hidden = true; pending = false;
            announce(data.message); message.focus();
        }
        if (urlField) urlField.addEventListener('input', () => {
            if (pending && urlField.value !== suggestedUrl) mapping.elements.use_api_url.checked = false;
        });
        mapping.elements.use_api_url.addEventListener('change', () => {
            if (!urlField) return;
            if (mapping.elements.use_api_url.checked && suggestedUrl) {
                originalUrl = urlField.value;
                urlField.value = suggestedUrl;
                urlField.dispatchEvent(new Event('input', { bubbles: true }));
            } else if (originalUrl !== null && urlField.value === suggestedUrl) {
                urlField.value = originalUrl;
                originalUrl = null;
                urlField.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        search.addEventListener('submit' , (event) => { event.preventDefault(); find(search.elements.query.value.trim()); });
        mapping.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (importing) { editor.requestSubmit(); return; }
            const data = await request('save', mapping);
            if (data) saved(data);
        });
        root.querySelector('[data-remove]').addEventListener('click', async () => {
            const data = await request('remove');
            if (data) saved(data);
        });
        root.querySelector('[data-cancel]').addEventListener('click', () => {
            batch?.cancelled();
            invalidateImport();
            restoreUrl();
            const choice = results.querySelector('select'); if (choice) choice.value = '';
            mapping.hidden = true; pending = false;
            announce(importing ? __('Arrangementsvalget er avbrutt. Ingen kurs er opprettet.', 'reginor-lite') : __('Valget er avbrutt. Den lagrede koblingen er beholdt.', 'reginor-lite'));
            search.elements.query.focus();
        });
        if (importing) {
            workspace.hidden = false;
            if (window.RegiNorImport) batch = window.RegiNorImport({ workspace, root, editor, mapping, importPreview, request, announce, select,
                invalidate: invalidateImport, completed: () => { pending = false; importProposal = null; } });
            mapping.querySelector('button[type="submit"]').hidden = true;
            workspace.addEventListener('input', invalidateImport);
            workspace.addEventListener('change', invalidateImport);
            editor.addEventListener('submit', async event => {
                if (event.defaultPrevented) return; // The shared editor validates and focuses required/date fields first.
                event.preventDefault(); invalidateImport();
                if (mapping.hidden || !mapping.elements.event_id.value) {
                    announce(__('Velg et arrangement hos LetsReg først. Kursfeltene dine er beholdt.', 'reginor-lite'), true); return;
                }
                window.RegiNorRichText?.save(editor);
                const body = new FormData(editor);
                for (const [key, value] of new FormData(mapping)) body.append(key, value);
                if (batch?.receipt()) body.set('receipt', batch.receipt());
                const data = await request('preview_import', body);
                if (!data) {
                    // Retain the server's specific explanation until the setup changes.
                    const box = document.createElement('div'); box.dataset.importIssues = ''; box.className = 'rnl-validation-summary';
                    const heading = document.createElement('h3'); heading.textContent = __('Forhåndsvisningen kunne ikke fullføres', 'reginor-lite');
                    const explanation = document.createElement('p'); explanation.textContent = message.textContent;
                    box.append(heading, explanation); importPreview.replaceChildren(box); importPreview.hidden = false;
                    importPreview.focus(); return;
                }
                importProposal = data.proposal;
                importPreview.innerHTML = data.html; importPreview.hidden = false;
                batch?.preview(data);
                importPreview.focus();
                const issues = importPreview.querySelector('[data-import-issues]');
                announce(issues
                    ? __('Kurset har feil som må rettes før import. Se forklaringene i forhåndsvisningen nedenfor.', 'reginor-lite')
                    : __('Kontroller datoene i forhåndsvisningen. Kurset kan deretter legges i importlisten eller opprettes som utkast. Ingenting er lagret ennå.', 'reginor-lite'));
            });
            importPreview.addEventListener('click', async event => {
                if (!event.target.closest('[data-confirm-import]') || !importProposal || batch?.hasPending()) return;
                const data = await request('confirm_import', null, { proposal: importProposal });
                if (!data) return;
                const destination = new URL(data.redirect, window.location.href);
                if (destination.origin !== window.location.origin) {
                    announce(__('Kurset er opprettet. Gå tilbake til perioden for å åpne det.', 'reginor-lite')); pending = false; return;
                }
                pending = false; importProposal = null;
                window.location.assign(destination.href);
            });
        }
        window.addEventListener('beforeunload', (event) => { if (pending || batch?.hasPending()) { event.preventDefault(); event.returnValue = ''; } });
        root.hidden = false;
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { mount };
    if (typeof document !== 'undefined') document.querySelectorAll('[data-rnl-letsreg-picker]').forEach(mount);
}());
