const { test } = require('node:test');
const assert = require('node:assert/strict');
const { withinWindow, clockLabel } = require('../../public/assets/js/child-today.js');
test('desktop clock uses the same window after the device hour changes', () => {
  assert.equal(withinWindow('07:00', new Date(2026, 8, 4, 8, 0)), true);
  assert.equal(withinWindow('07:00', new Date(2026, 8, 4, 8, 1)), false);
  assert.equal(withinWindow('18:00', new Date(2026, 8, 4, 17, 0)), true);
  assert.equal(clockLabel(17 * 60), '5 petang');
  assert.equal(clockLabel(8 * 60 + 45), '8:45 pagi');
});
test('phone time window includes both boundaries, excludes outside and permits untimed', () => {
  const now = new Date(2026, 8, 4, 17, 0);
  assert.equal(withinWindow('18:00:00', now), true);
  assert.equal(withinWindow('16:00:00', now), true);
  assert.equal(withinWindow('18:01:00', now), false);
  assert.equal(withinWindow('15:59:00', now), false);
  assert.equal(withinWindow('', now), true);
});
test('daily tasks do not wrap to the wrong calendar day at midnight', () => {
  assert.equal(withinWindow('23:30', new Date(2026, 8, 4, 0, 0)), false);
  assert.equal(withinWindow('00:30', new Date(2026, 8, 4, 0, 0)), true);
});
