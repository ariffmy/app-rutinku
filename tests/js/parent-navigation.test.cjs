// Run with: node --test tests/js/parent-navigation.test.cjs
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { runInNewContext } = require('node:vm');
const source = readFileSync(resolve(__dirname, '../../public/assets/js/app.js'), 'utf8');

function setup({ desktop = false, hasParent = true } = {}) {
  let document;
  class Element {
    hidden = false;
    attributes = {};
    listeners = {};
    children = [];
    addEventListener(name, callback) { this.listeners[name] = callback; }
    setAttribute(name, value) { this.attributes[name] = value; }
    contains(target) { return target === this || this.children.some((child) => child.contains(target)); }
    focus() { document.activeElement = this; }
    closest(selector) { return selector === 'a[href]' && this.isLink ? this : null; }
    emit(name, event = {}) { this.listeners[name]?.({ target: this, ...event }); }
  }
  const toggle = new Element();
  toggle.hidden = true;
  const link = new Element();
  link.isLink = true;
  const panel = new Element();
  panel.children = [link];
  const nav = new Element();
  nav.children = [toggle, panel];
  nav.querySelector = (selector) => selector === '[data-parent-menu-toggle]' ? toggle : panel;
  document = new Element();
  document.querySelector = (selector) => selector === '[data-parent-nav]' && hasParent ? nav : null;
  document.querySelectorAll = () => [];
  const media = new Element();
  media.matches = desktop;
  const window = new Element();
  window.matchMedia = (query) => {
    assert.equal(query, '(min-width: 1200px)');
    return media;
  };
  runInNewContext(source, { document, window, navigator: {}, Element });
  return { toggle, link, panel, nav, document, media };
}

test('mobile starts collapsed and the Menu button toggles accessible state', () => {
  const { toggle, panel } = setup();
  assert.equal(toggle.hidden, false);
  assert.equal(panel.hidden, true);
  assert.equal(toggle.attributes['aria-expanded'], 'false');
  toggle.emit('click');
  assert.equal(panel.hidden, false);
  assert.equal(toggle.attributes['aria-expanded'], 'true');
  toggle.emit('click');
  assert.equal(panel.hidden, true);
});

test('Escape closes the menu and returns keyboard focus', () => {
  const { toggle, panel, nav, document, link } = setup();
  toggle.emit('click');
  link.focus();
  let prevented = false;
  nav.emit('keydown', { key: 'Escape', preventDefault() { prevented = true; } });
  assert.equal(panel.hidden, true);
  assert.equal(document.activeElement, toggle);
  assert.equal(prevented, true);
});

test('navigation link and outside clicks close the mobile menu', () => {
  const { toggle, panel, document, link } = setup();
  toggle.emit('click');
  document.emit('click', { target: panel });
  assert.equal(panel.hidden, false);
  panel.emit('click', { target: link });
  assert.equal(panel.hidden, true);
  toggle.emit('click');
  document.emit('click');
  assert.equal(panel.hidden, true);
});

test('desktop links stay visible without a Menu button', () => {
  const { toggle, panel, document } = setup({ desktop: true });
  assert.equal(toggle.hidden, true);
  assert.equal(panel.hidden, false);
  document.emit('click');
  assert.equal(panel.hidden, false);
});

test('changing to mobile collapses the menu and preserves reachable focus', () => {
  const { toggle, panel, link, document, media } = setup({ desktop: true });
  link.focus();
  media.matches = false;
  media.emit('change');
  assert.equal(panel.hidden, true);
  assert.equal(toggle.hidden, false);
  assert.equal(document.activeElement, toggle);
  toggle.emit('click');
  media.matches = true;
  media.emit('change');
  assert.equal(panel.hidden, false);
  assert.equal(toggle.hidden, true);
  media.matches = false;
  media.emit('change');
  assert.equal(panel.hidden, true);
});

test('pages without Parent navigation do not register menu handlers', () => {
  const { document, toggle } = setup({ hasParent: false });
  assert.equal(document.listeners.click, undefined);
  assert.equal(toggle.listeners.click, undefined);
});
