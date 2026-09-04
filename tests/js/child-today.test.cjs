const { test } = require('node:test');
const assert = require('node:assert/strict');
const { withinWindow } = require('../../public/assets/js/child-today.js');
const at = (h, m = 0, s = 0) => new Date(2026, 8, 4, h, m, s);
test('two-hour task appears only from 5pm until before 7pm', () => {
  assert.equal(withinWindow('17:00:00', at(16, 59, 59), 120), false);
  assert.equal(withinWindow('17:00:00', at(17), 120), true);
  assert.equal(withinWindow('17:00:00', at(18, 54), 120), true);
  assert.equal(withinWindow('17:00:00', at(18, 59, 59), 120), true);
  assert.equal(withinWindow('17:00:00', at(19), 120), false);
  assert.equal(withinWindow('17:00:00', at(20), 120), false);
});
test('uses each duration including strings from HTML attributes', () => {
  assert.equal(withinWindow('17:00', at(17, 14, 59), '15'), true);
  assert.equal(withinWindow('17:00', at(17, 15), '15'), false);
  assert.equal(withinWindow('17:00', at(18), '120'), true);
  assert.equal(withinWindow('17:00', at(18), 'bad'), false);
  assert.equal(withinWindow('17:00', at(17), 0), false);
});
test('untimed tasks remain visible; future daily tasks do not wrap backwards', () => {
  assert.equal(withinWindow('', at(12), 15), true);
  assert.equal(withinWindow('23:30', at(0), 120), false);
  assert.equal(withinWindow('23:30', at(23, 59), 120), true);
  assert.equal(withinWindow('00:30', at(0), 15), false);
  assert.equal(withinWindow('00:30', at(0, 30), 15), true);
});
