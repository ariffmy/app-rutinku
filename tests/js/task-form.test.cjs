const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');
const source = readFileSync(resolve(__dirname, '../../public/assets/js/task-form.js'), 'utf8');
function setup() {
  const fields = Object.fromEntries(Object.entries({ title: '', duration_minutes: '15', points: '10', task_hour: '23', task_minute: '45', schedule_type: 'daily', start_date: '2026-09-03' }).map(([key, value]) => [key, { value }]));
  const listeners = {};
  const slider = { value: '10', addEventListener: (key, callback) => { slider[key] = callback; } };
  const nodes = { '[data-points-range]': slider, '[data-time-preview]': {}, '[data-date-section]': {}, '[data-weekly-section]': {} };
  const form = { elements: { namedItem: name => fields[name] }, querySelector: key => nodes[key], querySelectorAll: () => [], contains: () => true, addEventListener: (key, callback) => { listeners[key] = callback; } };
  runInNewContext(source, { document: { querySelector: () => form } });
  const click = dataset => listeners.click({ target: { closest: () => ({ dataset }) } });
  return { fields, nodes, slider, listeners, click };
}
test('duration selection computes next-day end time', () => {
  const { fields, nodes, click } = setup();
  click({ duration: '30' });
  assert.equal(fields.duration_minutes.value, '30');
  assert.equal(nodes['[data-time-preview]'].textContent, '11:45 malam – 12:15 tengah malam (esok)');
});
test('point adjustment clamps and ideas fill editable fields', () => {
  const { fields, click } = setup();
  click({ pointsChange: '-100' });
  assert.equal(fields.points.value, 0);
  fields.points.value = '9999';
  click({ pointsChange: '100' });
  assert.equal(fields.points.value, 10000);
  click({ idea: 'Baca buku', minutes: '15', stars: '10' });
  assert.equal(fields.title.value, 'Baca buku');
  assert.equal(fields.points.value, '10');
});
test('Malay clock labels cover noon, afternoon, morning and optional time', () => {
  const { fields, nodes, listeners } = setup();
  fields.duration_minutes.value = '30';
  for (const [hour, minute, expected] of [
    ['08', '00', '8 pagi – 8:30 pagi'],
    ['12', '00', '12 tengah hari – 12:30 tengah hari'],
    ['13', '45', '1:45 tengah hari – 2:15 petang'],
    ['18', '45', '6:45 petang – 7:15 malam'],
    ['', '00', ''],
  ]) {
    fields.task_hour.value = hour;
    fields.task_minute.value = minute;
    listeners.change();
    assert.equal(nodes['[data-time-preview]'].textContent, expected);
  }
});
test('weekly and inherited modes show the appropriate controls', () => {
  const { fields, nodes, listeners } = setup();
  fields.schedule_type.value = 'weekly';
  listeners.change();
  assert.equal(nodes['[data-weekly-section]'].hidden, false);
  assert.equal(fields.start_date.required, true);
  fields.schedule_type.value = 'inherit';
  listeners.change();
  assert.equal(nodes['[data-date-section]'].hidden, true);
  assert.equal(fields.start_date.required, false);
});
test('slider updates point number and unrelated pages are safe', () => {
  const { fields, slider } = setup();
  slider.value = '55';
  slider.input();
  assert.equal(fields.points.value, '55');
  assert.doesNotThrow(() => runInNewContext(source, { document: { querySelector: () => null } }));
});
