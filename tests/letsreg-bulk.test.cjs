const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const dom = new JSDOM(`<div hidden data-rnl-import-workspace data-rnl-course-editor>
<div hidden data-rnl-letsreg-picker data-mode="import" data-id="40" data-version="3" data-registration-url="" data-nonce="nonce" data-url="http://localhost/admin-ajax.php">
<div data-saved-summary></div><details data-editor open><form data-search-form><input name="query" value="Salsa"><input type="checkbox" name="period_only" checked><button>Search</button></form><div data-results></div>
<form data-mapping-form hidden><h4 data-event-name tabindex="-1"></h4><input name="event_id"><input name="verification_id"><div data-url-preview hidden><a data-event-url></a><p data-url-difference></p><input type="checkbox" name="use_api_url" value="1"></div><p data-url-missing hidden></p><div data-categories></div><div data-course-suggestions hidden></div><button type="submit">Save</button><button type="button" data-cancel>Cancel</button></form></details><details data-remove-panel hidden><button data-remove>Remove</button></details><p data-message tabindex="-1"></p></div>
<form id="rnl-course-editor" class="rnl-edit-form"><input name="id" value="40"><input name="version" value="3"><select name="course_id"><option value="70">Local description</option></select>
<select name="data[level_id]"><option value="0">Velg nivå</option><option value="101">Nybegynner</option><option value="102">Øvet 1</option></select><input name="data[title]"><input name="data[registration_url]"><input name="data[first_date]"><input name="data[weekday]"><input name="data[start_time]"><input name="data[end_time]"><input name="data[session_count]" value="2"><input name="data[room_id]" value="60"><input name="data[timezone]" value="Europe/Oslo"><input name="data[price_minor]" value="900"><input type="checkbox" name="data[price_from]"><input name="data[price_basis]" value="person"><button>Preview</button></form><section data-import-preview hidden tabindex="-1"></section></div>`, { url: 'http://localhost/editor', runScripts: 'outside-only' });
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
editor.insertAdjacentHTML('afterbegin', `<select name="description_mode"><option value="new">New</option><option value="existing">Existing</option></select><div data-new-description><input name="new_course[title]"><textarea name="new_course[description]"></textarea><input name="new_course[dance_style]"><input name="new_course[level_description]"><input name="new_course[partner_info]"></div><div data-existing-description></div>`);
editor.querySelector('[data-existing-description]').append(editor.elements.course_id);
$('[data-rnl-import-workspace]').insertAdjacentHTML('beforeend', '<section data-import-batch tabindex="-1" hidden></section>');
// Exercise the real field-validator contract, including controls in separate forms.
for (const [name, label, required] of [['new_course[dance_style]', 'Dansestil', true], ['data[title]', 'Kursnavn', true], ['data[room_id]', 'Sal', false]]) {
    const el = editor.elements.namedItem(name); el.id = 'test-' + label;
    el.dataset.field = name.match(/\[([^\]]+)\]/)[1]; el.required = required;
    const caption = w.document.createElement('label'); caption.htmlFor = el.id; caption.textContent = label; el.before(caption);
    const error = w.document.createElement('span'); error.id = el.id + '-error'; el.after(error);
}
w.eval(fs.readFileSync('plugin/reginor-lite/assets/admin.js', 'utf8'));
w.eval(fs.readFileSync('plugin/reginor-lite/assets/letsreg-import.js', 'utf8'));
w.eval(fs.readFileSync('plugin/reginor-lite/assets/letsreg-picker.js', 'utf8'));
const tick = () => new Promise(resolve => setImmediate(resolve));
const submit = async el => { el.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true })); await tick(); };
const click = async el => { el.click(); await tick(); };
const previewReply = { success: true, data: { proposal: 'signed-preview', html: '<h3>Kontroller kursutkastet</h3><button type="button" data-confirm-import>Opprett kursutkast</button>' } };

const named = name => editor.elements.namedItem(name);
const findButton = (text, scope = w.document) => [...scope.querySelectorAll('button')].find(el => el.textContent === text);
const eventReply = id => ({success:true,data:{event_id:id,verification_id:'v'+id,receipt:'receipt'+id,event_name:'Salsa '+id,description:'Description '+id,event_url:'https://www.letsreg.com/event/'+id,
    html:'<select name="data[categories][11][role]"><option value="leader">Fører</option></select><select name="data[categories][11][registration]"><option value="pair">Par</option></select>',
    suggestions:{fields:{...(id === 5 ? {level_id:101} : {}),title:'Salsa '+id,weekday:'1',first_date:'2031-01-06',start_time:'18:30',end_time:'20:00'},start_date:'2031-01-06',end_date:'2031-02-10',period_breaks:[],prices:[],timezone:'Europe/Oslo'}}});
const stage = async (id) => {
    replies.push({success:true,data:{proposal:'proposal'+id,html:'<h3>Review '+id+'</h3><button type="button" data-confirm-import>Opprett kursutkast</button>'}});
    await submit(editor); await click(findButton('Legg i importlisten', $('[data-import-preview]')));
};
(async () => {
    assert.equal(named('course_id').disabled,true,'New description still requires existing description');
    replies.push({success:true,data:{period:{start:'2031-01-06',end:'2031-02-10'},events:[5,6].map(id=>({id,name:'Salsa '+id,active:true,start_on:'2031-01-06',start_label:'06.01.2031'})),has_more:false}});
    await submit($('[data-search-form]'));
    await click($('[data-bulk-event="5"]')); await click($('[data-bulk-event="6"]'));
    assert.equal(findButton('Opprett alle kursutkast').disabled,true,'Unreviewed selection can be committed');
    replies.push(eventReply(5)); await click(findButton('Kontroller valgte kurs'));
    assert.equal(field('level_id').value, '101', 'Bulk import omitted level suggestion');
    field('level_id').value = '102'; field('level_id').dispatchEvent(new w.Event('change', { bubbles: true }));
    assert.equal(named('new_course[description]').value,'Description 5'); assert.equal(named('new_course[title]').value,'Salsa 5');
    const beforeInvalid = calls.length;
    field('room_id').value = '0';
    const role = $('[data-categories] select[name$="[role]"]');
    role.insertAdjacentHTML('afterbegin', '<option value="">Ikke ta med</option>'); role.value = '';
    await click(findButton('Kontroller neste valgte kurs'));
    const summary = $('.rnl-validation-summary');
    assert.equal(summary.hidden, false); assert.equal(w.document.activeElement, summary);
    assert.match(summary.textContent, /Dansestil mangler/); assert.match(summary.textContent, /Sal mangler/);
    assert.match(summary.textContent, /Ingen priskategori er valgt/); assert.match(summary.textContent, /LetsReg bestemmer ikke lokal sal/);
    summary.querySelector('button').click(); assert.equal(w.document.activeElement, named('new_course[dance_style]'));
    await submit(editor); assert.equal(calls.length, beforeInvalid, 'Invalid fields sent to API');
    assert.equal(named('new_course[description]').value, 'Description 5', 'Validation cleared description');
    field('room_id').value = '60'; role.value = 'leader';
    named('new_course[description]').value='Reviewed local text'; named('new_course[dance_style]').value='Salsa';
    await click(findButton('Kontroller neste valgte kurs'));
    assert.equal(summary.hidden, true); assert.match($('[data-message]').textContent, /mangler en oppdatert forhåndsvisning/);
    replies.push(previewReply); await submit(editor);
    await click(findButton('Kontroller neste valgte kurs'));
    assert.match($('[data-message]').textContent, /forhåndsvist, men er ikke lagt i importlisten/);
    assert.equal(w.document.activeElement, $('[data-stage-import]'));
    field('title').dispatchEvent(new w.Event('input', { bubbles: true }));
    await click(findButton('Kontroller neste valgte kurs'));
    assert.match($('[data-message]').textContent, /mangler en oppdatert forhåndsvisning/, 'Edited preview still treated as ready');
    replies.push({ success: false, data: { message: 'Du har valgt 7 undervisningskvelder, men bare 5 får plass før 2031-02-10.' } });
    await submit(editor);
    await click(findButton('Kontroller neste valgte kurs'));
    assert.match($('[data-import-issues]').textContent, /7 undervisningskvelder, men bare 5/);
    assert.equal(w.document.activeElement, $('[data-import-preview]'));
    assert.equal($('[data-stage-import]'), null, 'Failed preview can still be staged');
    await stage(5);
    assert.equal(calls.at(-1)['data[level_id]'], '102', 'Manual level omitted from bulk preview');
    assert.equal(calls.at(-1).receipt,'receipt5'); assert.equal(calls.at(-1)['new_course[description]'],'Reviewed local text');
    assert.equal(editor.hidden,true); assert.equal(findButton('Opprett alle kursutkast').disabled,true);
    replies.push({success:false,data:{message:'Rate limit; try again'}}); await click(findButton('Kontroller neste valgte kurs'));
    assert.ok($('[data-import-batch]').textContent.includes('Salsa 5'),'Lookup failure loses staged course');
    replies.push(eventReply(6)); await click(findButton('Kontroller neste valgte kurs'));
    assert.equal(field('level_id').value, '0', 'Level leaks from previous import to unmatched event');
    assert.equal(named('new_course[description]').value,'Description 6'); assert.equal(field('title').value,'Salsa 6');
    named('description_mode').value='existing'; named('description_mode').dispatchEvent(new w.Event('change',{bubbles:true}));
    assert.equal(named('course_id').disabled,false); assert.equal(named('new_course[description]').disabled,true);
    await stage(6);
    assert.equal(calls.at(-1).receipt,'receipt6'); assert.equal(calls.at(-1)['new_course[description]'],undefined);
    assert.equal(findButton('Opprett alle kursutkast').disabled,false);
    const leave = new w.Event('beforeunload',{cancelable:true}); w.dispatchEvent(leave); assert.equal(leave.defaultPrevented,true,'Staged batch can be silently lost');
    replies.push({success:false,data:{message:'Expired source'}}); await click(findButton('Opprett alle kursutkast'));
    assert.equal(destination,null); assert.ok($('[data-import-batch]').textContent.includes('Salsa 5'));
    // Refresh an expired item; editable description and local settings survive the fresh API read.
    replies.push(eventReply(5)); await click(findButton('Rediger: Salsa 5'));
    assert.equal(field('level_id').value, '102', 'Editing staged course loses manual level');
    assert.equal(named('new_course[description]').value,'Reviewed local text'); assert.equal(named('description_mode').value,'new');
    assert.equal(findButton('Opprett alle kursutkast').disabled,true); await stage(5);
    replies.push({success:true,data:{count:2,redirect:'http://localhost/wp-admin/admin.php?page=reginor-lite&period=40'}});
    await click(findButton('Opprett alle kursutkast'));
    assert.match(destination,/period=40/); assert.equal(calls.at(-1).operation,'confirm_import_batch');
    const savedLeave = new w.Event('beforeunload',{cancelable:true}); w.dispatchEvent(savedLeave); assert.equal(savedLeave.defaultPrevented,false);
    assert.equal(replies.length,0); dom.window.close();
    console.log('Bulk import DOM passed: multi-selection, descriptions, staged review, failed fetch/save, retained edits and combined creation.');
})().catch(error=>{dom.window.close();console.error(error);process.exitCode=1;});
