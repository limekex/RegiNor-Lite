(() => {
    'use strict';
    const __ = window.wp.i18n.__;
    const roots = document.querySelectorAll('.rnl-public, [data-rnl-capacity-preview]');
    roots.forEach((root) => {
        const serverNow = Number(root.dataset.rnlNow) * 1000;
        const expires = Number(root.dataset.rnlExpiry) * 1000;
        const loadedAt = Date.now();
        let timer, expired = false;
        const check = () => {
            if (expired || !expires || !serverNow || !root.isConnected) return;
            const remaining = expires - Math.max(Date.now(), serverNow + Date.now() - loadedAt);
            if (remaining > 0) { clearTimeout(timer); timer = setTimeout(check, Math.min(remaining, 60000)); return; }
            expired = true;
            // Do not leave an obsolete booking link or expired course visible in a long-open tab.
            const note = document.createElement('div'); note.className = 'rnl-empty'; note.setAttribute('role', 'status');
            const capacity = root.dataset.rnlCapacityPreview !== undefined;
            const title = document.createElement('h2'); title.textContent = capacity ? __('Opplysningene har utløpt', 'reginor-lite') : __('Kursinformasjonen må oppdateres', 'reginor-lite');
            const text = document.createElement('p'); text.textContent = capacity ? __('Sjekk ledige plasser hos LetsReg. Oppdater siden for å se resultatet av neste kontroll.', 'reginor-lite') : __('Last siden på nytt for å se kurs og påmelding slik de er nå.', 'reginor-lite');
            const button = document.createElement('button'); button.className = 'rnl-button'; button.textContent = __('Vis oppdatert kursinformasjon', 'reginor-lite'); button.addEventListener('click', () => {
                const url = new URL(location.href);
                url.searchParams.set('rnl_refresh', Date.now().toString(36) + '-' + Math.random().toString(36).slice(2));
                location.replace(url.href);
            });
            note.append(title, text, button); root.replaceChildren(note); clearTimeout(timer);
        };
        check(); window.addEventListener('pageshow', check); document.addEventListener('visibilitychange', check);
    });
    const restoreFocus = () => {
        if (!/^#rnl-(course-\d+(?:-embed-\d+)?|results(?:-\d+)?)$/.test(location.hash)) return;
        const target = document.getElementById(location.hash.slice(1));
        if (target) target.focus({ preventScroll: true });
    };
    restoreFocus(); window.addEventListener('hashchange', restoreFocus);
})();
