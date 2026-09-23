const assert = require('node:assert/strict');
const { validPoint } = require('../plugin/reginor-lite/assets/map.js');
for (const point of [[59.9139, 10.7522], ['-33.9', '18.4'], [0, 0], [-90, -180], [90, 180]]) assert.equal(validPoint(...point), true);
for (const point of [[91, 0], [0, -181], ['', ''], [null, 10], [false, 1], [undefined, 1], [1, 'oops'], [Infinity, 0], [' ', 0]]) assert.equal(validPoint(...point), false);
console.log('14 map coordinate checks passed.');

// Public maps initialize without a button; the administrative picker remains opt-in.
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('plugin/reginor-lite/assets/map.js', 'utf8');
function scenario(editing = false, library = true) {
    const publicMarkup = '<div data-rnl-venue-map data-latitude="59.91" data-longitude="10.75"><div data-map-canvas hidden></div><p data-map-fallback>Fallback</p><a href="https://www.openstreetmap.org/">OpenStreetMap</a></div>';
    const editorMarkup = '<form><section data-rnl-map-picker><button type="button" data-map-open hidden></button><div data-map-canvas hidden></div><input data-map-latitude value="59.91"><input data-map-longitude value="10.75"><p data-map-status></p><button type="button" data-map-center hidden></button><button type="button" data-map-clear hidden></button><input data-map-query><button type="button" data-map-search hidden></button><div data-map-results></div></section></form>';
    const dom = new JSDOM(editing ? editorMarkup : publicMarkup, { runScripts: 'outside-only' });
    const calls = [];
    dom.window.wp = { i18n: { __: text => text } };
    if (library) dom.window.L = {
        map(canvas) { calls.push(canvas); return { setView() { return this; }, on() {}, zoomControl: { setPosition() {}, _zoomInButton: dom.window.document.createElement('button'), _zoomOutButton: dom.window.document.createElement('button') } }; },
        tileLayer() { return { addTo() {} }; },
        divIcon() {},
        marker() { return { addTo() { return this; }, on() {} }; }
    };
    dom.window.eval(source);
    return { dom, calls, document: dom.window.document };
}
let test = scenario();
assert.equal(test.calls.length, 1, 'Public map must load without clicking');
assert.equal(test.document.querySelector('[data-map-canvas]').hidden, false);
assert.equal(test.document.querySelector('[data-map-fallback]').hidden, true);
test.dom.window.close();
test = scenario(true);
assert.equal(test.calls.length, 0, 'Editor map must wait for user action');
test.document.querySelector('[data-map-open]').click();
assert.equal(test.calls.length, 1);
test.dom.window.close();
test = scenario(false, false);
assert.equal(test.document.querySelector('[data-map-fallback]').hidden, false);
assert.ok(test.document.querySelector('a[href="https://www.openstreetmap.org/"]'));
test.dom.window.close();
console.log('Public auto-open, editor opt-in and missing-library fallback passed.');
