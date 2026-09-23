/* Pure field rules run identically in the admin enhancement and here. */
const assert = require('node:assert/strict');
const { check, validDate, registrationNote } = require('../plugin/reginor-lite/assets/admin.js');
let checks = 0;
const test = (condition) => { checks++; assert.ok(condition); };
const field = (key, value, type = 'date', required = false, extra = {}) => ({ key, value, type, required, ...extra });
const bounds = { min: '2030-01-07', max: '2030-01-21' };
test(validDate('2028-02-29')); test(!validDate('2030-02-29')); test(!validDate('2030-13-01'));
for (const date of ['2030-01-07', '2030-01-21']) test(check(field('from', date), bounds) === '');
for (const date of ['2030-01-06', '2030-01-22']) test(check(field('from', date), bounds) !== '');
test(check(field('until', '2030-01-10'), { ...bounds, after: '2030-01-11' }) !== '');
test(check(field('until', ''), bounds) === '');
test(check(field('from', '', 'date', true), bounds) !== '');
test(check(field('from', '', 'date', false, { badInput: true }), bounds) !== '');
test(check(field('first_date', '2030-01-08'), { ...bounds, weekday: '1' }) !== '');
test(check(field('first_date', '2030-01-07'), { ...bounds, weekday: '1' }) === '');
test(check(field('title', '  ', 'text', true), {}) !== '');
test(check(field('end_time', '18:00', 'time'), { startTime: '18:00' }) !== '');
test(check(field('end_time', '19:00', 'time'), { startTime: '18:00' }) === '');
test(check(field('sales_until', '2030-01-20T18:00', 'datetime-local'), { after: '2030-01-01T18:00' }) === ''); // Late sales stay allowed.
test(check(field('visible_until', '2030-01-01T18:00', 'datetime-local'), { after: '2030-01-01T18:00' }) !== '');
for (const value of ['0', '105', '1.5']) test(check(field('session_count', value, 'number'), {}) !== '');
for (const value of ['1200,50', '0', '1000000']) test(check(field('price_minor', value, 'text'), {}) === '');
for (const value of ['1 200', '1200,555', '1000000,01']) test(check(field('price_minor', value, 'text'), {}) !== '');
for (const value of ['http://letsreg.com/event/a', 'https://user:pass@letsreg.com/a', 'bad link', 'https://letsreg.com/a b']) test(check(field('registration_url', value, 'url'), {}) !== '');
test(check(field('registration_url', '', 'url'), {}) === '');
test(check(field('registration_url', 'https://www.letsreg.com/event/example', 'url'), {}) === '');
for (const url of ['https://www.letsreg.com/no/register/SalsaØvet1_4_26', 'https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26']) {
    test(check(field('registration_url', url, 'url'), {}) === '');
    test(registrationNote(url, 'available', ['www.letsreg.com']) === '');
    test(new URL(url).href === 'https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26');
}
const hosts = ['letsreg.com', 'www.letsreg.com', 'letsreg.no', 'www.letsreg.no'];
for (const host of hosts) {
    test(check(field('registration_url', 'https://' + host, 'url'), {}) === '');
    test(registrationNote('https://' + host, 'available', hosts) === '');
    test(registrationNote('https://' + host + '/no/event/kurs?lang=no#register', 'available', hosts) === '');
}
for (const url of ['https://www.letsreg.no.evil.test/event/kurs', 'https://other.letsreg.no/event/kurs', 'https://www.letsreg.no:8443/event/kurs']) test(registrationNote(url, 'available', hosts) !== '');
test(registrationNote('', 'available', hosts).includes('før publisering'));
test(registrationNote('', 'unknown', hosts) === '');
for (const url of ['https://letsreg.com.evil.test/a', 'https://letsreg.com:8443/a']) test(registrationNote(url, 'available', hosts) !== '');
test(registrationNote('https://www.letsreg.com:443/event/example', 'available', hosts) === '');
test(check(field('course_id', '0', 'select-one'), {}) !== '');
test(check(field('timezone', 'Europe/Oslo', 'text'), {}) === '');
test(check(field('timezone', 'not/a/timezone', 'text'), {}) !== '');
console.log(`Admin form validation: ${checks} checks passed.`);
