/* Small live status controls. Authenticated refresh also advances the bounded provider queue. */
(function () {
    'use strict';
    const __ = window.wp.i18n.__;
    const rows = [...document.querySelectorAll('[data-rnl-status-row]')];
    if (!rows.length) return;
    const states = new Map(rows.map(form => [form.elements.id.value, { form, saved: form.elements.status.value, busy: false }]));
    function apply(row) {
        const state = states.get(String(row.id)); if (!state) return;
        state.form.elements.version.value = String(row.version);
        state.form.elements.status.value = row.status; state.saved = row.status;
        state.form.querySelector('[data-status-description]').textContent = row.description;
    }
    async function request(form, operation) {
        const body = new FormData(form); body.set('operation', operation);
        const response = await fetch(form.dataset.url, { method: 'POST', credentials: 'same-origin', body });
        const json = await response.json();
        if (!response.ok || !json.success) throw new Error(json.data?.message || __('Statusen kunne ikke oppdateres. Prøv igjen.', 'reginor-lite'));
        return json.data.rows;
    }
    let generation = 0;
    for (const state of states.values()) {
        const { form } = state; const select = form.elements.status; const message = form.querySelector('[data-status-message]');
        form.querySelector('[data-status-save]').hidden = true;
        async function save(event) {
            event.preventDefault(); if (state.busy) return;
            state.busy = true; generation++;
            message.setAttribute('role', 'status'); message.textContent = __('Lagrer …', 'reginor-lite');
            // Construct FormData before disabling the select.
            const pending = request(form, 'save'); select.disabled = true; form.setAttribute('aria-busy', 'true');
            try {
                (await pending).forEach(apply); message.textContent = __('Lagret', 'reginor-lite');
            } catch (error) {
                select.value = state.saved; message.setAttribute('role', 'alert');
                message.textContent = error.message || __('Kunne ikke bekrefte lagringen. Last siden på nytt for å se lagret status.', 'reginor-lite');
            } finally { select.disabled = false; form.removeAttribute('aria-busy'); state.busy = false; generation++; }
        }
        select.addEventListener('change', save); form.addEventListener('submit', save);
    }
    let refreshing = false;
    async function refresh() {
        if (refreshing || document.hidden || [...states.values()].some(state => state.busy)) return;
        refreshing = true; const current = generation;
        try {
            const result = await request(rows[0], 'read');
            if (current !== generation) return;
            for (const row of result) {
                const state = states.get(String(row.id));
                if (state && !state.busy && document.activeElement !== state.form.elements.status) {
                    apply(row);
                    const message = state.form.querySelector('[data-status-message]');
                    message.textContent = ''; message.setAttribute('role', 'status');
                }
            }
        } catch (_) {
            if (current !== generation) return;
            for (const state of states.values()) {
                state.form.querySelector('[data-status-message]').textContent = __('Statusvisningen kunne ikke oppdateres. Prøver igjen om ett minutt.', 'reginor-lite');
            }
        } finally { refreshing = false; }
    }
    refresh();
    setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', refresh);
}());
