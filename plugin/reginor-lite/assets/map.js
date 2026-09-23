(function () {
    'use strict';
    const validPoint = (lat, lon) => [lat, lon].every((v) => (typeof v === 'number' || typeof v === 'string') && String(v).trim() !== '') && Number.isFinite(Number(lat)) && Number.isFinite(Number(lon)) && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lon)) <= 180;
    if (typeof module !== 'undefined' && module.exports) module.exports = { validPoint };
    if (typeof document === 'undefined' || typeof L === 'undefined') return;
    const { __ } = wp.i18n;
    document.querySelectorAll('[data-rnl-map-picker], [data-rnl-venue-map]').forEach((root) => {
        const editing = root.hasAttribute('data-rnl-map-picker');
        const canvas = root.querySelector('[data-map-canvas]'); const open = root.querySelector('[data-map-open]');
        const lat = root.querySelector('[data-map-latitude]'); const lon = root.querySelector('[data-map-longitude]');
        const status = root.querySelector('[data-map-status]'); const center = root.querySelector('[data-map-center]');
        let map, marker, searchSequence = 0;
        const point = () => editing ? [lat.value, lon.value] : [root.dataset.latitude, root.dataset.longitude];
        const showMarker = (coords) => {
            if (!marker) {
                marker = L.marker(coords, { draggable: editing, icon: L.divIcon({ className: 'rnl-map-marker', html: '<span aria-hidden="true">●</span>', iconSize: [28, 28], iconAnchor: [14, 14] }), title: __('Kurssted', 'reginor-lite'), alt: __('Kurssted', 'reginor-lite') }).addTo(map);
                if (editing) marker.on('dragend', () => choose(marker.getLatLng().wrap()));
            } else marker.setLatLng(coords);
        };
        const choose = (coords) => {
            lat.value = coords.lat.toFixed(6); lon.value = coords.lng.toFixed(6); showMarker([lat.value, lon.value]);
            status.textContent = __('Kartpunkt valgt. Lagre oppføringen for å beholde det.', 'reginor-lite');
            lat.dispatchEvent(new Event('input', { bubbles: true })); lon.dispatchEvent(new Event('input', { bubbles: true }));
        };
        const start = () => {
            canvas.hidden = false; if (open) open.hidden = true;
            if (map) { map.invalidateSize(); return; }
            const coords = point(); const hasPoint = validPoint(...coords);
            map = L.map(canvas, { scrollWheelZoom: false, worldCopyJump: true }).setView(hasPoint ? coords : [59.9139, 10.7522], hasPoint ? 17 : 11);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
            if (hasPoint) showMarker(coords);
            map.zoomControl.setPosition('topright');
            map.zoomControl._zoomInButton.setAttribute('aria-label', __('Zoom inn', 'reginor-lite'));
            map.zoomControl._zoomOutButton.setAttribute('aria-label', __('Zoom ut', 'reginor-lite'));
            if (editing) { map.on('click', (event) => choose(event.latlng.wrap())); center.hidden = false; }
        };
        if (open) { open.hidden = false; open.addEventListener('click', () => { start(); canvas.focus(); }); }
        // Resource forms are in disclosure panels: recalculate when reopened.
        root.closest('details')?.addEventListener('toggle', () => { if (map) map.invalidateSize(); });
        if (!editing) {
            if (validPoint(...point())) {
                start();
                const fallback = root.querySelector('[data-map-fallback]');
                if (fallback) fallback.hidden = true;
            }
            return;
        }
        center.addEventListener('click', () => choose(map.getCenter().wrap()));
        const clear = root.querySelector('[data-map-clear]'); clear.hidden = false;
        clear.addEventListener('click', () => { lat.value = ''; lon.value = ''; lat.setCustomValidity(''); lon.setCustomValidity(''); if (marker) { marker.remove(); marker = null; } status.textContent = __('Kartpunkt fjernet. Lagre oppføringen for å bekrefte.', 'reginor-lite'); });
        [lat, lon].forEach((input) => input.addEventListener('change', () => {
            const coords = point(); const valid = validPoint(...coords); const empty = coords.every((v) => v === '');
            [lat, lon].forEach((field) => field.setCustomValidity(valid || empty ? '' : __('Fyll inn begge koordinatene innenfor gyldige grenser, eller tøm begge.', 'reginor-lite')));
            if (map && valid) { showMarker(coords); map.panTo(coords); }
            else if (map && empty && marker) { marker.remove(); marker = null; }
        }));
        root.closest('form')?.addEventListener('submit', (event) => {
            const coords = point();
            if (validPoint(...coords) || coords.every((v) => v === '')) return;
            event.preventDefault(); event.stopImmediatePropagation();
            status.textContent = __('Fyll inn begge koordinatene innenfor gyldige grenser, eller tøm begge.', 'reginor-lite');
            lat.closest('details').open = true; lat.focus();
        }, true);
        const query = root.querySelector('[data-map-query]'); const search = root.querySelector('[data-map-search]'); const results = root.querySelector('[data-map-results]'); search.hidden = false;
        const lookup = async () => {
            if (search.disabled) return;
            const text = query.value.trim(); if (text.length < 3 || text.length > 250) { status.textContent = __('Skriv en adresse med mellom 3 og 250 tegn.', 'reginor-lite'); return; }
            const sequence = ++searchSequence; search.disabled = true; results.replaceChildren(); status.textContent = __('Søker etter adressen …', 'reginor-lite');
            let failure = __('Adressesøket svarer ikke nå. Prøv igjen, eller velg stedet direkte i kartet.', 'reginor-lite');
            try {
                const response = await fetch(root.dataset.searchUrl, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'rnl_map_search', nonce: root.dataset.nonce, query: text }) });
                const json = await response.json(); if (sequence !== searchSequence) return;
                if (!response.ok || !json.success || !Array.isArray(json.data)) { if (typeof json.data === 'string') failure = json.data; throw new Error(); }
                status.textContent = json.data.length ? __('Velg riktig adresse nedenfor.', 'reginor-lite') : __('Ingen treff. Prøv med gateadresse og by, eller velg stedet i kartet.', 'reginor-lite');
                json.data.forEach((row) => {
                    if (!validPoint(row.latitude, row.longitude)) return;
                    const button = document.createElement('button'); button.type = 'button'; button.className = 'rnl-map-result'; button.textContent = row.label;
                    button.addEventListener('click', () => { start(); choose({ lat: Number(row.latitude), lng: Number(row.longitude) }); map.setView([row.latitude, row.longitude], 17); results.replaceChildren(); }); results.appendChild(button);
                });
            } catch (_) { status.textContent = failure; }
            finally { search.disabled = false; }
        };
        search.addEventListener('click', lookup); query.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); lookup(); } });
    });
}());
