const {test} = require('node:test');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const {init} = require('../plugin/reginor-lite/assets/course-tools.js');
function fixture(navigator) {
    const dom = new JSDOM(`<section data-rnl-tools data-rnl-share-url="https://example.org/kurs/salsa/" data-rnl-share-title="Salsa Øvet"><button data-rnl-native-share hidden>Del</button><div class="rnl-copy-field"><input readonly value="https://example.org/kurs/salsa/"><button data-rnl-copy hidden>Kopier</button></div><p data-rnl-tools-status role="status"></p></section>`, {url:'https://example.org/?gclid=private&utm_source=google'});
    init(dom.window.document, navigator);
    return {dom, document:dom.window.document, button:dom.window.document.querySelector('[data-rnl-copy]'), share:dom.window.document.querySelector('[data-rnl-native-share]'), status:dom.window.document.querySelector('[role=status]')};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
test('copy waits for success, uses canonical, and binds once',async()=>{
    let release,calls=0,value;
    const navigator={clipboard:{writeText:async v=>{calls++;value=v;return new Promise(r=>release=r);}}};
    const f=fixture(navigator);init(f.document,navigator);f.button.click();
    assert.equal(f.status.textContent,'');release();await tick();
    assert.equal(calls,1);assert.equal(value,'https://example.org/kurs/salsa/');assert.equal(f.status.textContent,'Lenken er kopiert.');
    assert.equal(f.share.hidden,true);
});
test('unavailable or rejected clipboard selects readable fallback',async()=>{
    for(const navigator of [{},{clipboard:{writeText:async()=>{throw Error('Denied');}}}]){
        const f=fixture(navigator);f.button.click();await tick();const input=f.document.querySelector('input');
        assert.equal(f.document.activeElement,input);assert.equal(input.selectionEnd,input.value.length);assert.match(f.status.textContent,/markerte lenken/);
    }
});
test('native share uses clean payload, cancellation normal, error has fallback',async()=>{
    let payload;const f=fixture({share:async p=>{payload=p;throw Object.assign(new Error(),{name:'AbortError'});}});
    assert.equal(f.share.hidden,false);f.share.click();await tick();
    assert.deepEqual(payload,{title:'Salsa Øvet',url:'https://example.org/kurs/salsa/'});assert.equal(f.status.textContent,'');assert.equal(f.document.activeElement,f.share);
    const bad=fixture({share:async()=>{throw new Error('Denied');}});bad.share.click();await tick();assert.match(bad.status.textContent,/Kopier lenke/);
});
