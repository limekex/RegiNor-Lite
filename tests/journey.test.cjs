const assert = require('node:assert/strict');
const { randomUUID } = require('node:crypto');
const { start, attribution } = require('../plugin/reginor-lite/assets/journey.js');
let checks = 0;
const test = (value) => { checks++; assert.ok(value); };
function environment({ courseDetail = true, statistics = false, marketing = false, cmp = true, api = null, saved = null, storage = new Map(), autoFlush = true, href = 'https://example.com/kurs?utm_source=google&utm_campaign=Host&gclid=SYNTHETIC_123' } = {}) {
    const permissions = { statistics, marketing }; const listeners = {}; const requests = []; let reads = 0;
    const timers = new Map(); let timerId = 0;
    const flush = () => { while (timers.size) { const batch = [...timers.values()]; timers.clear(); batch.forEach(fn => fn()); } };
    if (saved !== null) storage.set('rnl_journey_v1', saved);
    const w = { location: { href }, crypto: { randomUUID },
        setTimeout: (fn) => { timers.set(++timerId, fn); return timerId; }, clearTimeout: (id) => timers.delete(id),
        sessionStorage: { getItem: (key) => { reads++; return storage.get(key) || null; }, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
        fetch: (url, options) => { requests.push({ url, ...options, data: JSON.parse(options.body) }); return Promise.resolve({ ok: true }); } };
    if (cmp) { w.cmplz_has_consent = (key) => permissions[key]; w.cmplz_get_cookie = (key) => permissions[key] ? 'allow' : 'deny'; }
    if (api !== null) w.wp_has_consent = (category) => api[category];
    const d = { referrer: 'https://www.google.com/search?q=private', addEventListener: (key, fn) => { listeners[key] = fn; },
        querySelectorAll: (selector) => selector === '[data-rnl-track-list]' ? [{}] : courseDetail ? [{ dataset: { rnlTrackCourse: '42' } }] : [] };
    const runtime = start(w, d, { endpoint: '/wp-json/reginor/v1/journey', page_id: 12 });
    if (autoFlush) flush();
    return { w, d, permissions, listeners, requests, runtime, storage, flush, reads: () => reads, events: () => requests.filter((r) => r.data.stage) };
}
for (const cmp of [true, false]) {
    const e = environment({ cmp });
    test(e.requests.length === 0 && e.reads() === 0 && !e.w.dataLayer && e.storage.size === 0);
}
const e = environment(); e.permissions.statistics = true; e.listeners.cmplz_status_change(); e.flush();
test(e.events().map((r) => r.data.stage).join(',') === 'landing,page_view,course_list,course_view');
test(e.events().every((r) => Object.keys(r.data.click_ids).length === 0 && r.data.campaign.utm_campaign === 'Host'));
test(e.w.dataLayer.filter((r) => r.event).length === 4);
const firstId = e.events()[0].data.journey_id;
e.listeners.cmplz_enable_category(); e.flush(); test(e.events().length === 4);
const click = { target: { closest: () => ({ dataset: { rnlLetsreg: '42' } }) }, defaultPrevented: false, preventDefault: () => { throw Error('Navigation blocked'); } };
e.listeners.click(click); e.listeners.click(click);
test(e.events().filter((r) => r.data.stage === 'letsreg_click').length === 1);
test(e.w.location.href.includes('gclid=SYNTHETIC_123'));
const next = environment({ statistics: true, storage: e.storage, href: 'https://example.com/autre?utm_source=facebook' });
test(next.events().length === 3 && next.events().every((r) => r.data.journey_id === firstId && r.data.campaign.utm_source === 'google'));
e.permissions.statistics = false; e.listeners.cmplz_status_change();
test(e.storage.size === 0 && e.requests.at(-1).data.operation === 'forget' && e.requests.at(-1).data.journey_id === firstId);
test(e.events().every((r) => r.signal.aborted));
const size = e.events().length; e.listeners.click(click); test(e.events().length === size);
const ads = environment({ statistics: true, marketing: true });
test(ads.events().every((r) => r.data.click_ids.gclid === 'SYNTHETIC_123'));
ads.permissions.marketing = false; ads.listeners.cmplz_status_change(); ads.flush();
test(ads.requests.some((r) => r.data.operation === 'forget'));
test(ads.events().at(-1).data.journey_id !== ads.events()[0].data.journey_id && Object.keys(ads.events().at(-1).data.click_ids).length === 0);
for (const saved of ['{bad', 'null', '[]', '{"id":"test"}', JSON.stringify({ id: randomUUID(), started: Date.now(), touched: Date.now(), campaign: null })]) {
    const bad = environment({ statistics: true, saved }); test(bad.events()[0].data.stage === 'landing');
}
const expired = JSON.parse(ads.storage.get('rnl_journey_v1')); expired.started = expired.touched = Date.now() - 31 * 60 * 1000;
const fresh = environment({ statistics: true, saved: JSON.stringify(expired) }); test(fresh.events()[0].data.journey_id !== expired.id);
const implicit = environment(); implicit.w.cmplz_has_consent = () => true; implicit.runtime.sync(); test(implicit.requests.length === 0);
const unavailable = environment(); unavailable.w.sessionStorage = { getItem() { throw Error(); }, setItem() { throw Error(); }, removeItem() { throw Error(); } };
unavailable.w.fetch = () => { throw Error('offline'); }; unavailable.permissions.statistics = true; unavailable.runtime.sync(); test(unavailable.w.dataLayer.some((r) => r.event === 'rnl_page_view'));
const attrs = attribution('https://example.com/?utm_source=user%40example.com&fbclid=synthetic', 'https://facebook.com/private/path', true);
test(attrs.campaign.utm_source === 'facebook' && attrs.campaign.utm_medium === 'social' && !JSON.stringify(attrs).includes('private/path'));
const again = environment({ statistics: true }); again.listeners.cmplz_revoke(); test(again.storage.size === 0 && again.requests.at(-1).data.operation === 'forget');
// List/calendar clicks may skip the detail page. Modified/middle clicks use the same event,
// preserve link decoration, and never synthesize a course view or confirmed registration.
const overview = environment({ statistics: true, marketing: true, courseDetail: false });
const direct = { dataset: { rnlLetsreg: '42' }, target: '_blank', rel: 'noopener',
    href: 'https://www.letsreg.com/event/test?utm_source=google&_gl=synthetic&ref=a%2Fb#registration' };
const originalHref = direct.href;
const auxiliary = { type: 'auxclick', button: 2, target: { closest: () => direct }, defaultPrevented: false,
    preventDefault: () => { throw Error('Native navigation blocked'); } };
overview.listeners.auxclick(auxiliary);
test(!overview.events().some(r => r.data.stage === 'letsreg_click'));
overview.listeners.auxclick({ ...auxiliary, button: 1 });
overview.listeners.click({ ...auxiliary, type: 'click', button: 0, ctrlKey: true });
test(overview.events().filter(r => r.data.stage === 'letsreg_click').length === 1);
const outbound = overview.events().find(r => r.data.stage === 'letsreg_click');
test(outbound.data.course_id === 42 && outbound.data.campaign.utm_campaign === 'Host' && outbound.data.click_ids.gclid === 'SYNTHETIC_123');
test(outbound.keepalive === true && overview.w.dataLayer.some(r => r.event === 'rnl_letsreg_click'));
test(!overview.events().some(r => r.data.stage === 'course_view'));
test(direct.href === originalHref && direct.target === '_blank' && direct.rel === 'noopener');
const prevented = environment({ statistics: true });
prevented.listeners.click({ ...auxiliary, type: 'click', defaultPrevented: true });
test(!prevented.events().some(r => r.data.stage === 'letsreg_click'));
for (const cmp of [true, false]) {
    const denied = environment({ cmp });
    denied.listeners.auxclick({ ...auxiliary, button: 1 });
    test(denied.requests.length === 0 && direct.href === originalHref);
}
// Complianz dispatches its category event before WP Consent API / Site Kit's
// downstream consent update. GTM must see that update before the FIRST event.
const ordering = environment({ autoFlush: false }); ordering.flush();
ordering.w.dataLayer = [{ consent: 'denied' }];
ordering.permissions.statistics = true;
ordering.listeners.cmplz_status_change();
ordering.listeners.cmplz_enable_category();
ordering.w.dataLayer.push({ consent: 'granted' });
ordering.flush();
assert.deepEqual(ordering.w.dataLayer.filter(row => row.consent || row.event).map(row => row.consent || row.event),
    ['denied', 'granted', 'rnl_landing', 'rnl_page_view', 'rnl_course_list', 'rnl_course_view']); checks++;
ordering.listeners.cmplz_run_after_all_scripts(); ordering.flush();
test(ordering.events().length === 4);

// "Reject all" can revoke marketing first, while statistics is still allowed.
// Cancel immediately, but never create a new journey in that intermediate state.
const withdrawal = environment({ statistics: true, marketing: true });
const beforeWithdrawal = withdrawal.events().length;
withdrawal.permissions.marketing = false; withdrawal.listeners.cmplz_status_change();
test(withdrawal.events().length === beforeWithdrawal && withdrawal.events().every(row => row.signal.aborted));
withdrawal.permissions.statistics = false; withdrawal.listeners.cmplz_status_change(); withdrawal.flush();
test(withdrawal.events().length === beforeWithdrawal && withdrawal.storage.size === 0);
test(withdrawal.requests.filter(row => row.data.operation === 'forget').every(row => !row.signal.aborted));
withdrawal.listeners.click(click); withdrawal.flush(); test(withdrawal.events().length === beforeWithdrawal);

// Saved consent on a new page still waits until downstream startup handlers run.
const restored = environment({ statistics: true, storage: ordering.storage, autoFlush: false });
test(restored.events().length === 0);
restored.w.dataLayer = [{ consent: 'granted' }]; restored.flush();
test(restored.w.dataLayer[0].consent === 'granted' && restored.events().length === 3);

// A click during a pending category update retains its course and native URL;
// withdrawing before the scheduled measurement cancels the click as well.
const queued = environment(); queued.permissions.statistics = true; queued.listeners.cmplz_status_change();
queued.listeners.click(click); test(queued.events().length === 0); queued.flush();
test(queued.events().filter(row => row.data.stage === 'letsreg_click').length === 1);
const revoked = environment(); revoked.permissions.statistics = true; revoked.listeners.cmplz_status_change();
revoked.listeners.click(click); revoked.listeners.cmplz_revoke(); revoked.flush();
test(revoked.events().length === 0 && revoked.storage.size === 0);
const rapid = environment(); rapid.permissions.statistics = true; rapid.listeners.cmplz_status_change();
rapid.permissions.statistics = false; rapid.listeners.cmplz_status_change(); rapid.flush();
test(rapid.events().length === 0 && rapid.storage.size === 0);
// API denial takes precedence even while Complianz still reports an earlier allowance.
const apiPermissions = { statistics: false, marketing: true };
const apiJourney = environment({ statistics: true, marketing: true, api: apiPermissions });
test(apiJourney.events().length === 0 && apiJourney.reads() === 0 && apiJourney.storage.size === 0);
apiPermissions.statistics = true;
apiJourney.listeners.wp_listen_for_consent_change(); apiJourney.flush();
test(apiJourney.events().length === 4 && apiJourney.events().every(r => r.data.click_ids.gclid === 'SYNTHETIC_123'));
const apiFirst = apiJourney.events()[0].data.journey_id;
apiPermissions.marketing = false; apiJourney.listeners.wp_listen_for_consent_change();
test(apiJourney.storage.size === 0 && apiJourney.requests.at(-1).data.operation === 'forget');
apiJourney.flush();
test(apiJourney.events().at(-1).data.journey_id !== apiFirst && Object.keys(apiJourney.events().at(-1).data.click_ids).length === 0);
const apiCount = apiJourney.events().length;
apiPermissions.statistics = false; apiJourney.listeners.wp_listen_for_consent_change();
test(apiJourney.events().every(r => r.signal.aborted) && apiJourney.requests.at(-1).data.operation === 'forget');
apiJourney.flush(); apiJourney.listeners.click(click);
test(apiJourney.events().length === apiCount && apiJourney.storage.size === 0);
for (const cmp of [false, true]) {
    const noConsent = environment({ cmp, api: { statistics: true, marketing: true } });
    test(noConsent.events().length === 0 && noConsent.reads() === 0);
}
const unknownConsent = environment({ statistics: true, api: {} });
test(unknownConsent.events().length === 0);
console.log(`${checks} journey consent/runtime checks passed.`);
