const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const dom = new JSDOM(`<div id="workspace"><form id="editor"><select name="description_mode"><option value="new">New</option></select><div data-new-description>
<input name="new_course[title]"><textarea name="new_course[description]"></textarea><input name="new_course[dance_style]"><textarea name="new_course[level_description]"></textarea><textarea name="new_course[partner_info]"></textarea>
</div><div data-existing-description></div><input name="data[registration_url]"></form><form id="mapping"><div data-categories></div></form><div id="preview" hidden></div></div>`, { runScripts: 'outside-only' });
const w = dom.window; w.wp = { i18n: { __: text => text } };
w.eval(fs.readFileSync('plugin/reginor-lite/assets/letsreg-import.js', 'utf8'));
const editor = w.document.getElementById('editor');
const api = w.RegiNorImport({ workspace: w.document.getElementById('workspace'), editor, mapping: w.document.getElementById('mapping'), importPreview: w.document.getElementById('preview'), announce() {} });
const field = key => editor.elements.namedItem('new_course[' + key + ']');
const data = { event_id: 1, event_name: 'Rueda', description: 'Entire marked text', description_template: {mode: 'structured', fields: {description: 'Dance in a ring.', dance_style: 'Rueda', level_description: 'Beginners course required.', partner_info: 'Partner optional.', price_terms: 'Discount.'}}, description_template_message: 'Template detected' };
api.selected(data);
for (const key of ['description', 'dance_style', 'level_description', 'partner_info']) assert.equal(field(key).value, data.description_template.fields[key]);
assert.equal(editor.querySelector('[data-template-message]').textContent, 'Template detected');
field('partner_info').value = 'My local adjustment'; api.selected(data);
assert.equal(field('partner_info').value, 'My local adjustment', 'Reselecting same event overwrites local editing');
api.selected({ event_id: 2, event_name: 'Legacy', description: 'Complete original description', description_template: {mode:'invalid'}, description_template_message:'Missing heading' });
assert.equal(field('description').value, 'Complete original description');
assert.equal(field('dance_style').value, '', 'Previous event fields leaked into next event');
assert.equal(field('partner_info').value, '');
assert.equal(editor.querySelectorAll('[data-template-message]').length, 1);
assert.equal(editor.querySelector('[data-template-message]').textContent, 'Missing heading');
dom.window.close(); console.log('Template import DOM checks passed: autofill, manual edits, malformed fallback and event isolation.');
