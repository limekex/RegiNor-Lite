const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let block;
vm.runInNewContext(fs.readFileSync('plugin/reginor-lite/assets/block.js', 'utf8'), { window: { wp: {
    blocks: { registerBlockType(name, definition) { block = definition; } },
    element: { createElement: (tag, props, ...children) => ({ tag, props, children }) },
    blockEditor: { useBlockProps: () => ({}) },
    components: { TextControl: 'text', SelectControl: 'select', ToggleControl: 'toggle' },
    i18n: { __: text => text }
} } });
const attributes = Object.fromEntries(Object.entries(block.attributes).map(([key, setting]) => [key, setting.default]));
const changes = [];
const tree = block.edit({ attributes, setAttributes: value => changes.push(value) });
const controls = tree.children.filter(child => ['text', 'select', 'toggle'].includes(child.tag));
assert.equal(controls.length, 8);
controls.find(c => c.props.label === 'Vis bare disse nivåene').props.onChange('intro,nybegynner');
controls.find(c => c.props.label === 'Utelat disse nivåene').props.onChange('videregående');
controls.find(c => c.props.label === 'Fremhevede kurs').props.onChange('only');
controls.find(c => c.props.label === 'Tillatte visninger').props.onChange('week');
controls.find(c => c.props.label === 'Vis filtre').props.onChange(false);
assert.deepEqual(JSON.parse(JSON.stringify(changes)), [
    { levels: 'intro,nybegynner' }, { exclude_levels: 'videregående' }, { featured: 'only' },
    { allowed_views: ['week'] }, { show_filters: false }
]);
console.log('Blokkvalg for nivåutvalg, fremheving og visning bestått.');
