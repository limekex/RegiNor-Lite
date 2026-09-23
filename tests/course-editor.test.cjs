const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
let serial = 0;
const input = (key, name, value = '', type = 'text', external = false) => {
    const id = 'rnl-field-' + ++serial;
    return `<label for="${id}">${key}</label><input id="${id}" data-field="${key}" name="${name}" value="${value}" type="${type}" ${external ? 'form="rnl-course-editor"' : ''}><span id="${id}-error"></span>`;
};
const dom = new JSDOM(`<div data-rnl-course-editor><form id="rnl-course-editor" class="rnl-edit-form" data-period-start="2031-01-06" data-period-end="2031-02-10">
${input('weekday', 'data[weekday]', '1')}${input('start_time', 'data[start_time]', '18:00', 'time')}${input('end_time', 'data[end_time]', '19:00', 'time')}</form>
<form id="picker"><input name="query"></form><details id="advanced">${input('first_date', 'data[first_date]', '', 'date', true)}${input('timezone', 'data[timezone]', 'Europe/Oslo', 'text', true)}
<fieldset class="rnl-breaks"><div data-break-rows></div><details data-break-fallback><div data-break-row><input type="checkbox" name="data[breaks][0][remove]" form="rnl-course-editor">
${input('from', 'data[breaks][0][from]', '', 'date', true)}${input('until', 'data[breaks][0][until]', '', 'date', true)}${input('reason', 'data[breaks][0][reason]', '', 'text', true)}</div></details>
<button type="button" data-add-break hidden form="rnl-course-editor">Legg til</button><p data-break-status></p></fieldset></details>
<input name="data[appearance_color]" value="#334455" form="rnl-course-editor"><button type="submit" form="rnl-course-editor">Forhåndsvis</button></div>`, { runScripts: 'outside-only', url: 'http://localhost/' });
const w = dom.window; w.wp = { i18n: { __: text => text } };
w.eval(fs.readFileSync('plugin/reginor-lite/assets/admin.js', 'utf8'));
const $ = selector => w.document.querySelector(selector);
const form = $('#rnl-course-editor'); const first = $('[data-field="first_date"]');
first.value = '2031-03-03'; first.dispatchEvent(new w.Event('input', { bubbles: true }));
assert.equal(first.getAttribute('aria-invalid'), 'true', 'External first date does not validate instantly');
const bad = new w.Event('submit', { bubbles: true, cancelable: true }); form.dispatchEvent(bad);
assert.equal(bad.defaultPrevented, true); assert.equal($('#advanced').open, true); assert.equal(w.document.activeElement, first);
assert.match($('.rnl-validation-summary').textContent, /2031-03-03/);
assert.match($('.rnl-validation-summary').textContent, /2031-02-10/);
assert.equal($('.rnl-validation-summary').hidden, false);
first.value = '2031-01-13'; first.dispatchEvent(new w.Event('input', { bubbles: true }));
assert.equal($('.rnl-validation-summary').hidden, true, 'Resolved errors remain in summary');
$('[data-add-break]').click();
const row = $('[data-break-rows] [data-break-row]');
assert.ok(row, 'Cannot add progressive break outside form');
row.querySelector('[data-field="from"]').value = '2031-01-20'; row.querySelector('[data-field="reason"]').value = 'Ferie';
row.querySelector('[data-field="reason"]').dispatchEvent(new w.Event('input', { bubbles: true }));
const valid = new w.Event('submit', { bubbles: true, cancelable: true }); form.dispatchEvent(valid);
assert.equal(valid.defaultPrevented, false, 'Valid external fields prevent submission');
const data = new w.FormData(form);
assert.equal(data.get('data[first_date]'), '2031-01-13'); assert.equal(data.get('data[breaks][0][from]'), '2031-01-20');
assert.equal(data.get('data[appearance_color]'), '#334455'); assert.equal(data.has('query'), false, 'Picker controls joined course submit');
row.querySelector('[type="checkbox"]').checked = true;
row.querySelector('[type="checkbox"]').dispatchEvent(new w.Event('change', { bubbles: true }));
assert.equal(new w.FormData(form).has('data[breaks][0][from]'), false, 'Removed break still submitted');
dom.window.close();
console.log('Course editor DOM passed: external controls, instant date validation, focus, progressive breaks and form ownership.');
