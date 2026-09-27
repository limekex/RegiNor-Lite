const { JSDOM } = require('jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async function () {
    const dom = new JSDOM('<div class="rnl-admin"><textarea id="rnl-generated-shortcode">[reginor_courses styles="salsa"]</textarea><button id="rnl-copy-shortcode" hidden></button><p id="rnl-copy-status" data-success="copied" data-failure="manual" data-dirty="regenerate"></p><form><input></form></div>', { runScripts: 'outside-only' });
    const { window } = dom; let copied;
    window.navigator.clipboard = { writeText: async value => { copied = value; } };
    window.eval(fs.readFileSync('plugin/reginor-lite/assets/shortcode-generator.js', 'utf8'));
    const button = window.document.getElementById('rnl-copy-shortcode');
    const status = window.document.getElementById('rnl-copy-status');
    assert.equal(button.hidden, false); button.click(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(copied, '[reginor_courses styles="salsa"]'); assert.equal(status.textContent, 'copied');
    window.navigator.clipboard.writeText = async () => { throw Error('Denied'); };
    button.click(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(status.textContent, 'manual'); assert.equal(window.document.activeElement.id, 'rnl-generated-shortcode');
    window.document.querySelector('input').dispatchEvent(new window.Event('input', { bubbles: true }));
    assert.equal(button.disabled, true); assert.equal(status.textContent, 'regenerate');
    dom.window.close(); console.log('Shortcode copy, fallback and stale output protection passed.');
})();
