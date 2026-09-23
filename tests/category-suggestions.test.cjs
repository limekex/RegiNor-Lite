const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const row = (id, role, form, selected = '') => `<fieldset ${role ? `data-category-role="${role}" data-category-registration="${form}"` : ''}>
<select name="data[categories][${id}][role]"><option value="">Ikke med</option><option value="leader" ${selected === 'leader' ? 'selected' : ''}>Fører</option><option value="follower" ${selected === 'follower' ? 'selected' : ''}>Følger</option></select>
<select name="data[categories][${id}][registration]"><option value="single">Enkelt</option><option value="pair" ${selected && form === 'pair' ? 'selected' : ''}>Par</option></select></fieldset>`;
const markup = `<div data-category-suggestions><button type="button" hidden data-fill-category-suggestions>Fyll ut forslag</button><p data-category-suggestion-status role="status"></p>
${row(1403036, 'leader', 'single')}${row(1403037, 'follower', 'single')}${row(1403038, 'leader', 'pair', 'leader')}${row(1403039, 'follower', 'pair')}${row(5, '', 'single')}</div>`;
const dom = new JSDOM(`<form>${markup}</form>`, { runScripts: 'outside-only' });
const w = dom.window; w.wp = { i18n: { __: text => text } };
w.eval(fs.readFileSync('plugin/reginor-lite/assets/admin.js', 'utf8'));
const root = w.document.querySelector('[data-category-suggestions]');
const button = root.querySelector('button'); const form = root.closest('form');
const field = (id, name) => form.elements.namedItem(`data[categories][${id}][${name}]`);
assert.equal(button.hidden, false, 'Partial existing mapping hides fill action');
assert.equal(field(1403036, 'role').value, '', 'Page load silently includes previously excluded category');
let changes = 0; root.addEventListener('change', () => changes++);
button.click();
for (const [id, role, registration] of [[1403036, 'leader', 'single'], [1403037, 'follower', 'single'], [1403038, 'leader', 'pair'], [1403039, 'follower', 'pair']]) {
    assert.equal(field(id, 'role').value, role); assert.equal(field(id, 'registration').value, registration);
}
assert.equal(changes, 6, 'Preview/price handlers are not notified for all three newly filled categories');
assert.equal(field(5, 'role').value, '', 'Ambiguous category selected');
assert.equal(button.hidden, true); assert.match(root.textContent, /3 kategorier/);
assert.equal(w.document.activeElement, root.querySelector('[role="status"]'));
// Manual nonempty roles/forms survive filling another category, including after bulk restoration.
field(1403036, 'role').value = 'follower'; field(1403036, 'registration').value = 'pair';
field(1403039, 'role').value = '';
w.RegiNorCategorySuggestions(root); w.RegiNorCategorySuggestions(root);
assert.equal(button.hidden, false); button.click();
assert.equal(field(1403036, 'role').value, 'follower'); assert.equal(field(1403036, 'registration').value, 'pair');
assert.equal(changes, 8, 'Remount attached duplicate click handlers');
// The same enhancement works for category HTML inserted by the AJAX picker.
const dynamic = w.document.createElement('div'); dynamic.innerHTML = markup; w.document.body.append(dynamic);
w.RegiNorCategorySuggestions(dynamic.firstElementChild);
assert.equal(dynamic.querySelector('button').hidden, false);
dynamic.querySelector('button').click(); assert.match(dynamic.textContent, /3 kategorier/);
dom.window.close();
console.log('Category suggestion DOM passed: partial saved mapping, all four roles/forms, explicit refill, retained edits and dynamic mounting.');
