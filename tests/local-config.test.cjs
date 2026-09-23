'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { configure } = require('../scripts/configure-local.cjs');

test('Local setup creates a private file and never overwrites an existing override', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'rnl-config-'));
    try {
        fs.copyFileSync(path.join(__dirname, '../.wp-env.override.example.json'), path.join(root, '.wp-env.override.example.json'));
        fs.copyFileSync(path.join(__dirname, '../.env.example'), path.join(root, '.env.example'));
        assert.match(configure(root), /Oppsett klart/);
        const target = path.join(root, '.wp-env.override.json');
        assert.equal(fs.statSync(target).mode & 0o777, 0o600);
        const credentials = path.join(root, '.env.letsreg.json');
        assert.equal(fs.statSync(credentials).mode & 0o777, 0o600);
        const config = JSON.parse(fs.readFileSync(credentials));
        assert.equal(Object.keys(config).length, 5);
        assert.ok(Object.values(config).every(value => value === ''));
        const original = JSON.stringify({ core: 'WordPress/WordPress#6.8', config: { RNL_LETSREG_PASSWORD: 'synthetic-only' } });
        fs.writeFileSync(target, original);
        fs.writeFileSync(credentials, 'synthetic-password-file');
        const output = configure(root);
        assert.match(output, /bevart/);
        assert.ok(!output.includes('synthetic-only'));
        assert.equal(fs.readFileSync(target, 'utf8'), original);
        assert.equal(fs.readFileSync(credentials, 'utf8'), 'synthetic-password-file');
    } finally { fs.rmSync(root, { recursive: true, force: true }); }
});

test('Local setup refuses a symlink without modifying its target', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'rnl-config-'));
    try {
        fs.copyFileSync(path.join(__dirname, '../.wp-env.override.example.json'), path.join(root, '.wp-env.override.example.json'));
        fs.copyFileSync(path.join(__dirname, '../.env.example'), path.join(root, '.env.example'));
        const target = path.join(root, 'preserve.json');
        fs.writeFileSync(target, 'preserve');
        fs.symlinkSync(target, path.join(root, '.wp-env.override.json'));
        assert.match(configure(root), /bevart/);
        assert.equal(fs.readFileSync(target, 'utf8'), 'preserve');
    } finally { fs.rmSync(root, { recursive: true, force: true }); }
});
