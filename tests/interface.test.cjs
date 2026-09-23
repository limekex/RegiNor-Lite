/* Behaviour checks for time-sensitive stale-page removal; no browser emulation library. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('plugin/reginor-lite/assets/interface.js', 'utf8');
function scenario(server, expiry, current, capacity = false, translate = (text) => text) {
    let clock = current, callback, delay, replacement, navigation;
    const handlers = {};
    const root = { dataset: { rnlNow: String(server), rnlExpiry: String(expiry) }, isConnected: true, replaceChildren(node) { replacement = node; } };
    if (capacity) root.dataset.rnlCapacityPreview = '';
    const context = { Date: { now: () => clock }, Math, Number, URL,
        document: { querySelectorAll: () => [root], createElement: (tag) => ({ tag, children: [], setAttribute() {}, addEventListener(name, fn) { this[name] = fn; }, append(...nodes) { this.children.push(...nodes); } }), addEventListener: (name, fn) => { handlers[name] = fn; }, getElementById: () => null },
        window: { wp: { i18n: { __: translate } }, addEventListener: (name, fn) => { handlers[name] = fn; } }, location: { hash: '', href: 'https://example.org/kursrekke/host/?rnl_view=week&utm_source=test&rnl_refresh=old#rnl-course-5', replace(url) { navigation = url; } },
        setTimeout: (fn, ms) => { callback = fn; delay = ms; return 1; }, clearTimeout() {} };
    vm.runInNewContext(source, context);
    return { replaced: () => replacement, delay: () => delay, advance: (time) => { clock = time; callback(); }, handlers, navigation: () => navigation };
}
let page = scenario(1000, 1010, 1000000);
assert.equal(page.replaced(), undefined);
assert.equal(page.delay(), 10000);
page.advance(1010000);
assert.equal(page.replaced().children[0].textContent, 'Kursinformasjonen må oppdateres');
page = scenario(1000, 1010, 1020000); // HTML already stale when loaded from a misconfigured cache.
assert.ok(page.replaced());
page = scenario(1000, 2000, 1000000);
assert.equal(page.delay(), 60000);
assert.equal(typeof page.handlers.pageshow, 'function');
assert.equal(typeof page.handlers.visibilitychange, 'function');
page = scenario(1000, 1010, 1000000, true);
page.advance(1010000);
assert.equal(page.replaced().children[0].textContent, 'Opplysningene har utløpt');
assert.ok(page.replaced().children[1].textContent.includes('Sjekk ledige plasser hos LetsReg'));
page = scenario(1000, 1010, 1020000, true, (text) => text === 'Opplysningene har utløpt' ? 'Information expired' : text);
assert.equal(page.replaced().children[0].textContent, 'Information expired');


const note = page.replaced(); page.handlers.pageshow();
assert.equal(page.replaced(), note, 'Repeated checks replace focused notice');
note.children[2].click();
const refreshed = new URL(page.navigation());
assert.equal(refreshed.searchParams.get('rnl_view'), 'week');
assert.equal(refreshed.searchParams.get('utm_source'), 'test');
assert.equal(refreshed.hash, '#rnl-course-5');
assert.notEqual(refreshed.searchParams.get('rnl_refresh'), 'old');
assert.equal(refreshed.searchParams.getAll('rnl_refresh').length, 1);

console.log('Utløp, oversettelse, fokus og oppdateringsadresse med bevarte filtre/sporing bestått.');
