const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const dom = new JSDOM(`<div hidden data-rnl-import-workspace data-rnl-course-editor>
<div hidden data-rnl-letsreg-picker data-mode="import" data-id="40" data-version="3" data-registration-url="" data-nonce="nonce" data-url="http://localhost/admin-ajax.php">
<div data-saved-summary></div><details data-editor open><form data-search-form><input name="query" value="Salsa"><input type="checkbox" name="period_only" checked><button>Search</button></form><div data-results></div>
<form data-mapping-form hidden><h4 data-event-name tabindex="-1"></h4><input name="event_id"><input name="verification_id"><div data-url-preview hidden><a data-event-url></a><p data-url-difference></p><input type="checkbox" name="use_api_url" value="1"></div><p data-url-missing hidden></p><div data-categories></div><div data-course-suggestions hidden></div><button type="submit">Save</button><button type="button" data-cancel>Cancel</button></form></details><details data-remove-panel hidden><button data-remove>Remove</button></details><p data-message tabindex="-1"></p></div>
<form id="rnl-course-editor" class="rnl-edit-form"><input name="id" value="40"><input name="version" value="3"><select name="course_id"><option value="70">Local description</option></select>
<select name="data[level_id]"><option value="0">Velg nivå</option><option value="101">Nybegynner</option><option value="102">Øvet 1</option></select><input name="data[title]"><input name="data[registration_url]"><input name="data[first_date]"><input name="data[weekday]"><input name="data[start_time]"><input name="data[end_time]"><input name="data[session_count]" value="2"><input name="data[room_id]" value="60"><input name="data[timezone]" value="Europe/Oslo"><input name="data[price_minor]" value="900"><textarea name="data[price_terms]"></textarea><input type="checkbox" name="data[price_from]"><input name="data[price_basis]" value="person"><button>Preview</button></form><section data-import-preview hidden tabindex="-1"></section></div>`, { url: 'http://localhost/editor', runScripts: 'outside-only' });
const w = dom.window; w.wp = { i18n: { __: text => text } };
let destination = null;
Object.defineProperty(w, 'location', { value: { href: 'http://localhost/editor', origin: 'http://localhost', assign: url => { destination = url; } } });
const $ = selector => w.document.querySelector(selector);
const editor = $('#rnl-course-editor'); const field = key => editor.elements.namedItem('data[' + key + ']');
const calls = []; const replies = [];
w.fetch = async (url, options) => {
    assert.equal(field('title').disabled, true, 'Import request leaves editable fields enabled while preview is generated');
    calls.push(Object.fromEntries(options.body.entries()));
    const reply = replies.shift(); assert.ok(reply, 'Unexpected request');
    return { ok: reply.success !== false, json: async () => reply };
};
w.eval(fs.readFileSync('plugin/reginor-lite/assets/admin.js', 'utf8'));
w.eval(fs.readFileSync('plugin/reginor-lite/assets/letsreg-picker.js', 'utf8'));
const tick = () => new Promise(resolve => setImmediate(resolve));
const submit = async el => { el.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); await tick(); };
const click = async el => { el.click(); await tick(); };
const previewReply = { success: true, data: { proposal: 'signed-preview', html: '<h3>Kontroller kursutkastet</h3><button type="button" data-confirm-import>Opprett kursutkast</button>' } };
(async () => {
    assert.equal($('[data-rnl-import-workspace]').hidden, false);
    assert.equal($('[data-mapping-form] button[type="submit"]').hidden, true, 'Import shows misleading separate save-mapping action');
    assert.equal(calls.length, 0);
    await submit(editor); assert.equal(calls.length, 0, 'Import preview before event choice sends request');
    replies.push({ success: true, data: { period: { start: '2031-01-06', end: '2031-02-10' }, events: [{ id: 5, name: 'Salsa import', active: true, start_on: '2031-01-06', start_label: '06.01.2031' }], has_more: false } });
    await submit($('[data-search-form]'));
    replies.push({ success: true, data: { event_id: 5, verification_id: 'verified', event_name: 'Salsa import', event_url: 'https://www.letsreg.com/event/5',
        html: '<select name="data[categories][11][role]"><option value="leader">Fører</option></select><select name="data[categories][11][registration]"><option value="pair">Par</option></select>',
        suggestions: { fields: { price_terms: 'Studentrabatt 100 kr.', level_id: 101, title: 'Salsa import', weekday: '1', start_time: '18:30', end_time: '20:00', first_date: '2031-01-06' }, start_date: '2031-01-06', end_date: '2031-02-10', period_breaks: [], prices: [{ id: 11, price_minor: 75000 }], timezone: 'Europe/Oslo' } } });
    const select = $('[data-results] select'); select.value = '5'; select.dispatchEvent(new w.Event('change')); await tick();
    assert.equal(field('title').value, 'Salsa import', 'Import does not fill provider name');
    assert.equal(field('level_id').value, '101', 'Import does not propose matched level');
    assert.equal(field('session_count').value, '6'); assert.equal(field('start_time').value, '18:30');
    assert.equal(field('room_id').value, '60', 'Provider overwrites manual room');
    assert.equal(field('price_terms').value, 'Studentrabatt 100 kr.', 'Template price terms not autofilled during import');
    assert.equal(field('registration_url').value, 'https://www.letsreg.com/event/5');
    replies.push(previewReply); await submit(editor);
    const sent = calls.at(-1);
    assert.equal(sent['data[level_id]'], '101', 'Suggested level omitted from preview');
    assert.equal(sent.operation, 'preview_import'); assert.equal(sent.mode, 'import');
    assert.equal(sent.course_id, '70'); assert.equal(sent['data[room_id]'], '60');
    assert.equal(sent['data[categories][11][registration]'], 'pair'); assert.equal(sent.verification_id, 'verified');
    assert.equal($('[data-import-preview]').hidden, false); assert.equal(w.document.activeElement, $('[data-import-preview]'));
    const oldConfirm = $('[data-confirm-import]'); field('title').value = 'Adjusted name'; field('title').dispatchEvent(new w.Event('input', { bubbles: true }));
    assert.equal($('[data-import-preview]').hidden, true, 'Editing fields leaves stale preview available');
    const before = calls.length; await click(oldConfirm); assert.equal(calls.length, before, 'Stale preview can be confirmed');
    replies.push(previewReply); await submit(editor);
    replies.push({ success: false, data: { message: 'Source expired' } }); await click($('[data-confirm-import]'));
    assert.equal(field('title').value, 'Adjusted name'); assert.equal(field('title').disabled, false);
    assert.equal(destination, null, 'Failure redirects away from user input');
    replies.push({ success: true, data: { redirect: 'http://localhost/wp-admin/admin.php?page=reginor-lite&period=40&group=99' } });
    await click($('[data-confirm-import]'));
    assert.match(destination, /group=99/); assert.equal(calls.at(-1).proposal, 'signed-preview');
    const leave = new w.Event('beforeunload', { cancelable: true }); w.dispatchEvent(leave); assert.equal(leave.defaultPrevented, false, 'Saved import still prompts on navigation');
    assert.equal(replies.length, 0); dom.window.close();
    console.log('Import DOM journey passed: shared search, suggestions, manual placement, combined preview, stale review, errors and confirmation.');
})().catch(error => { dom.window.close(); console.error(error); process.exitCode = 1; });
