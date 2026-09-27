const {JSDOM}=require('jsdom');
const fs=require('node:fs');const assert=require('node:assert/strict');
function setup(reduced=false){
 const dom=new JSDOM(`<section data-rnl-carousel><nav hidden data-rnl-carousel-controls><button data-rnl-carousel-previous></button><button data-rnl-carousel-next></button><button data-rnl-carousel-play data-play-label="Start" data-pause-label="Pause"></button></nav><ul tabindex="0" data-rnl-carousel-track><li><a href="#">Article</a></li></ul></section>`,{runScripts:'outside-only',pretendToBeVisual:true});
 const w=dom.window;const timers=new Map();let id=0;w.setTimeout=(fn,ms)=>{timers.set(++id,{fn,ms});return id;};w.clearTimeout=id=>timers.delete(id);
 const media={matches:reduced,addEventListener(){}};w.matchMedia=()=>media;
 const track=w.document.querySelector('ul');Object.defineProperties(track,{clientWidth:{value:500,configurable:true},scrollWidth:{value:1500,configurable:true}});
 track.scrollBy=({left})=>{track.scrollLeft+=left;track.dispatchEvent(new w.Event('scroll'));};track.scrollTo=({left})=>{track.scrollLeft=left;track.dispatchEvent(new w.Event('scroll'));};
 w.eval(fs.readFileSync('plugin/reginor-lite/assets/level-carousel.js','utf8'));
 return {w,track,timers,section:w.document.querySelector('section'),play:w.document.querySelector('[data-rnl-carousel-play]'),run(){const entry=[...timers][0];assert(entry);timers.delete(entry[0]);entry[1].fn();}};
}
let c=setup();assert.equal([...c.timers.values()][0].ms,7000);c.run();assert.equal(c.track.scrollLeft,500);c.run();assert.equal(c.track.scrollLeft,1000);c.run();assert.equal(c.track.scrollLeft,0);
c.play.click();assert.equal(c.timers.size,0);assert.equal(c.play.textContent,'Start');c.play.click();assert.equal(c.timers.size,1);
c.section.dispatchEvent(new c.w.Event('mouseenter'));assert.equal(c.timers.size,0);c.section.dispatchEvent(new c.w.Event('mouseleave'));assert.equal(c.timers.size,1);
c.track.focus();assert.equal(c.timers.size,0);c.track.dispatchEvent(new c.w.KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));assert.equal(c.track.scrollLeft,500);
c.w.close();c=setup(true);assert.equal(c.timers.size,0);assert.equal(c.play.textContent,'Start');c.play.click();assert.equal(c.timers.size,1);
c.track.dispatchEvent(new c.w.Event('touchstart'));assert.equal(c.timers.size,0);c.w.close();console.log('Level carousel: autoplay, wrap, pause, hover, focus, keyboard, touch and reduced motion passed.');
