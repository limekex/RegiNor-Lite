/* Complianz controls collection; GTM receives events but is never loaded/configured here. */
(function () {
    'use strict';
    const campaignKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_content', 'utm_term'];
    const clickKeys = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'ScCid', 'li_fat_id'];
    const label = (value) => typeof value === 'string' && new TextEncoder().encode(value).length <= 100 && /^[\p{L}\p{N} _.,+\-]*$/u.test(value) ? value.trim() : '';
    function attribution(href, referrer, marketing) {
        const url = new URL(href); const campaign = {}; const click_ids = {};
        campaignKeys.forEach((key) => { campaign[key] = label(url.searchParams.get(key) || ''); });
        if (marketing) clickKeys.forEach((key) => { const value = url.searchParams.get(key) || ''; if (/^[A-Za-z0-9_.-]{1,256}$/.test(value)) click_ids[key] = value; });
        if (!campaign.utm_source) {
            let host = ''; try { host = new URL(referrer).hostname; } catch (_) { /* No external referrer. */ }
            if (host === url.hostname) host = '';
            const source = /(^|\.)(google\.[a-z.]+)$/.test(host) ? 'google' : /(^|\.)(facebook\.com|fb\.com)$/.test(host) ? 'facebook' : /(^|\.)instagram\.com$/.test(host) ? 'instagram' : /(^|\.)bing\.com$/.test(host) ? 'bing' : /(^|\.)tiktok\.com$/.test(host) ? 'tiktok' : host ? 'referral' : 'direct';
            campaign.utm_source = source;
            campaign.utm_medium = campaign.utm_medium || (['google', 'bing'].includes(source) ? 'organic' : ['facebook', 'instagram', 'tiktok'].includes(source) ? 'social' : source);
            if (click_ids.gclid || click_ids.gbraid || click_ids.wbraid) { campaign.utm_source = 'google'; campaign.utm_medium = 'cpc'; }
            if (click_ids.msclkid) { campaign.utm_source = 'bing'; campaign.utm_medium = 'cpc'; }
            // A social click identifier alone does not prove that traffic came from a paid ad.
        }
        return { campaign, click_ids };
    }
    function consent(w) {
        const allowed = (category) => typeof w.cmplz_has_consent === 'function' && typeof w.cmplz_get_cookie === 'function'
            && w.cmplz_has_consent(category) === true && w.cmplz_get_cookie(category) === 'allow'
            && (typeof w.wp_has_consent !== 'function' || w.wp_has_consent(category) === true);
        return { statistics: allowed('statistics'), marketing: allowed('marketing') };
    }
    function start(w, d, config) {
        const key = 'rnl_journey_v1'; let journey = null; let prior = { statistics: false, marketing: false }; let seen = new Set();
        const pending = new Set();
        let syncTimer = null; const queuedClicks = new Set();
        const uuid = () => w.crypto.randomUUID();
        const send = (payload) => {
            const controller = new AbortController();
            // A second category-revocation event must not abort the deletion
            // request started by the first one.
            if (payload.operation !== 'forget') pending.add(controller);
            try { const request = w.fetch(config.endpoint, { method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', 'X-RNL-Journey': '1' }, body: JSON.stringify(payload), signal: controller.signal });
            Promise.resolve(request).catch(() => {}).finally(() => pending.delete(controller));
            } catch (_) { pending.delete(controller); }
        };
        const save = () => { try { w.sessionStorage.setItem(key, JSON.stringify(journey)); } catch (_) { /* Memory-only session if storage is unavailable. */ } };
        const forget = () => {
            pending.forEach((controller) => controller.abort()); pending.clear();
            let old = journey;
            try { old = old || JSON.parse(w.sessionStorage.getItem(key) || 'null'); w.sessionStorage.removeItem(key); } catch (_) { /* No usable local state. */ }
            journey = null; seen = new Set();
            (w.dataLayer = w.dataLayer || []).push({ rnl: null });
            if (old?.id) send({ operation: 'forget', journey_id: old.id });
        };
        function emit(stage, course = 0) {
            const c = consent(w); if (!c.statistics || !journey) return;
            if (!c.marketing) journey.click_ids = {};
            const fingerprint = stage + ':' + course;
            if (seen.has(fingerprint)) return;
            seen.add(fingerprint); journey.touched = Date.now(); save();
            const payload = { event_id: uuid(), journey_id: journey.id, stage, course_id: course, page_id: config.page_id,
                statistics: true, marketing: c.marketing, campaign: journey.campaign, click_ids: c.marketing ? journey.click_ids : {} };
            send(payload);
            (w.dataLayer = w.dataLayer || []).push({ rnl: null });
            w.dataLayer.push({ event: 'rnl_' + stage, rnl: payload });
        }
        function sync(forceForget = false) {
            if (typeof w.cmplz_has_consent !== 'function' || typeof w.cmplz_get_cookie !== 'function') return;
            const c = consent(w);
            if (!c.statistics && !forceForget && !journey && !prior.statistics) {
                try { w.sessionStorage.removeItem(key); } catch (_) {}
                prior = c; return;
            }
            if (journey && (Date.now() - journey.touched >= 30 * 60 * 1000 || Date.now() - journey.started >= 24 * 60 * 60 * 1000)) { journey = null; seen = new Set(); }
            if (forceForget || !c.statistics || (prior.marketing && !c.marketing)) {
                // Deleting our prior state is permitted for withdrawal; nothing new is measured.
                forget(); prior = c; if (forceForget || !c.statistics) return;
            }
            if (!journey) {
                let saved = null; try { saved = JSON.parse(w.sessionStorage.getItem(key) || 'null'); } catch (_) { /* Start without persistent state. */ }
                const valid = saved && /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(saved.id || '')
                    && saved.campaign && typeof saved.campaign === 'object' && !Array.isArray(saved.campaign)
                    && campaignKeys.every((key) => typeof saved.campaign[key] === 'string' && label(saved.campaign[key]) === saved.campaign[key])
                    && saved.click_ids && typeof saved.click_ids === 'object' && !Array.isArray(saved.click_ids)
                    && Object.entries(saved.click_ids).every(([key, value]) => clickKeys.includes(key) && typeof value === 'string' && /^[A-Za-z0-9_.-]{1,256}$/.test(value))
                    && Number.isFinite(saved.started) && Number.isFinite(saved.touched) && saved.started <= saved.touched && saved.touched <= Date.now()
                    && Date.now() - saved.touched < 30 * 60 * 1000 && Date.now() - saved.started < 24 * 60 * 60 * 1000;
                if (valid && !c.marketing && Object.keys(saved.click_ids || {}).length) { journey = saved; forget(); saved = null; }
                const resumed = valid && saved;
                journey = resumed ? saved : { id: uuid(), started: Date.now(), touched: Date.now(), ...attribution(w.location.href, d.referrer, c.marketing) };
                save(); if (!resumed) emit('landing');
            }
            // Attribution stays tied to the landing captured with consent; later URLs never overwrite it.
            prior = c;
            emit('page_view');
            d.querySelectorAll('[data-rnl-track-list]').forEach(() => emit('course_list'));
            d.querySelectorAll('[data-rnl-track-course]').forEach((node) => emit('course_view', Number(node.dataset.rnlTrackCourse)));
        }
        function scheduleSync() {
            const c = consent(w);
            // Revocation stops in-flight collection immediately. Do not restart a
            // statistics-only journey while Complianz is still changing categories.
            if (!c.statistics || (prior.marketing && !c.marketing)) {
                queuedClicks.clear();
                if (journey || prior.statistics) forget();
                prior = c;
            }
            if (syncTimer !== null) w.clearTimeout(syncTimer);
            // Complianz emits category events before the WP Consent API / Site Kit
            // listener queues Google's update. Measure after that whole task, using
            // final consent, without changing consent or replaying any RNL event.
            syncTimer = w.setTimeout(() => {
                syncTimer = null;
                sync();
                queuedClicks.forEach((course) => emit('letsreg_click', course));
                queuedClicks.clear();
            }, 0);
        }
        ['cmplz_status_change', 'cmplz_enable_category', 'cmplz_cookie_warning_loaded', 'cmplz_run_after_all_scripts', 'wp_listen_for_consent_change'].forEach((event) => d.addEventListener(event, scheduleSync));
        d.addEventListener('cmplz_revoke', () => {
            if (syncTimer !== null) w.clearTimeout(syncTimer);
            syncTimer = null; queuedClicks.clear(); sync(true);
        });
        const trackRegistration = (event) => {
            if (event.type === 'auxclick' && event.button !== 1) return;
            const link = event.target.closest?.('[data-rnl-letsreg]');
            if (!link || event.defaultPrevented) return;
            if (syncTimer !== null) {
                if (consent(w).statistics) queuedClicks.add(Number(link.dataset.rnlLetsreg));
            } else { sync(); emit('letsreg_click', Number(link.dataset.rnlLetsreg)); }
            // Never delay navigation, alter the destination or claim that a click is a purchase.
        };
        d.addEventListener('click', trackRegistration);
        d.addEventListener('auxclick', trackRegistration);
        scheduleSync();
        return { sync, emit };
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { attribution, consent, start };
    if (typeof window !== 'undefined' && window.rnlJourneyConfig && window.crypto?.randomUUID && window.fetch) start(window, document, window.rnlJourneyConfig);
}());
