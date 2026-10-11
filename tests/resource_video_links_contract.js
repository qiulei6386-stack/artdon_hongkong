'use strict';

// Run the actual browser scripts with a small DOM; no browser, network or database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const adminScript = fs.readFileSync(path.join(root, 'admin/assets/resource_video_links.js'), 'utf8');
const publicTemplate = fs.readFileSync(path.join(root, 'resources-videos.php'), 'utf8');
const publicScript = [...publicTemplate.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)]
  .map(match => match[1]).find(code => code.includes("document.getElementById('videoRelatedLink')"));
assert.ok(publicScript, 'Find the actual public video modal script');

class Element {
  constructor(tagName, attributes = {}) {
    this.tagName = tagName.toLowerCase();
    this.attributes = new Map(Object.entries(attributes));
    this.children = [];
    this.parentElement = null;
    this.listeners = new Map();
    this.dataset = {};
    this.hidden = false;
    this.value = '';
    this.focused = false;
    this.selected = false;
    this._textContent = '';
    this._innerHTML = '';
    const classes = new Set();
    this.classList = {
      add: name => classes.add(name),
      remove: name => classes.delete(name),
      contains: name => classes.has(name),
      toggle(name, force) {
        const enabled = force === undefined ? !classes.has(name) : force;
        if (enabled) classes.add(name); else classes.delete(name);
        return enabled;
      }
    };
  }
  set textContent(value) {
    this.children.forEach(child => { child.parentElement = null; });
    this.children = [];
    this._textContent = String(value);
  }
  get textContent() { return this._textContent + this.children.map(child => child.textContent).join(''); }
  set innerHTML(value) { this.textContent = ''; this._innerHTML = String(value); }
  get innerHTML() { return this._innerHTML; }
  set href(value) { this.setAttribute('href', value); }
  get href() { return this.getAttribute('href') || ''; }
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
  removeAttribute(name) { this.attributes.delete(name); }
  matches(selector) {
    if (selector === this.tagName) return true;
    const attr = selector.match(/^\[([\w-]+)(?:="([^"]*)")?\]$/);
    if (attr) return this.attributes.has(attr[1]) && (attr[2] === undefined || this.attributes.get(attr[1]) === attr[2]);
    return false;
  }
  querySelectorAll(selector) {
    const found = [];
    for (const child of this.children) {
      if (child.matches(selector)) found.push(child);
      found.push(...child.querySelectorAll(selector));
    }
    return found;
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  closest(selector) { return this.matches(selector) ? this : this.parentElement && this.parentElement.closest(selector); }
  appendChild(child) {
    if (child.parentElement) {
      const old = child.parentElement.children;
      old.splice(old.indexOf(child), 1);
    }
    this.children.push(child);
    child.parentElement = this;
    return child;
  }
  cloneNode(deep = false) {
    const copy = new Element(this.tagName, Object.fromEntries(this.attributes));
    copy._textContent = this._textContent;
    if (deep) this.children.forEach(child => copy.appendChild(child.cloneNode(true)));
    return copy;
  }
  addEventListener(name, callback) {
    const handlers = this.listeners.get(name) || [];
    handlers.push(callback);
    this.listeners.set(name, handlers);
  }
  fire(name, properties = {}) {
    const event = {
      target: this, defaultPrevented: false, propagationStopped: false,
      preventDefault() { this.defaultPrevented = true; },
      stopPropagation() { this.propagationStopped = true; },
      ...properties
    };
    for (const handler of this.listeners.get(name) || []) handler(event);
    return event;
  }
  click() { return this.fire('click'); }
  focus() { this.focused = true; }
  select() { this.selected = true; }
}

class Document extends Element {
  constructor() { super('document'); }
  createElement(tagName) { return new Element(tagName); }
  getElementById(id) { return this.querySelector('[id="' + id + '"]'); }
}

const catalog = [
  {title: 'ARMI Track Light', detail: '系列 · ARMI', search: 'ARMI Track Light armi-track-light ARMI', url: '/products/track-lights/armi'},
  {title: 'ARMI 45 Spotlight', detail: '产品 · AT1707 / 013-M1707 · ARMI Track Light', search: 'ARMI 45 Spotlight AT1707 / 013-M1707 ARMI Track Light armi-45', url: '/products/track-lights/armi/at1707-013-m1707'},
  {title: 'ZENITH Downlight', detail: '系列 · ZENITH', search: 'ZENITH Downlight zenith-downlight', url: '/products/downlights/zenith'}
];

function makeAdmin(options = {}) {
  const document = new Document();
  const section = document.appendChild(new Element('section', {'data-rv-links': ''}));
  const add = (tag, attr) => section.appendChild(new Element(tag, attr));
  const search = add('input', {'data-rv-product-search': ''});
  const results = add('div', {'data-rv-product-results': ''});
  const status = add('p', {'data-rv-product-status': ''});
  const url = add('input', {name: 'related_url'});
  const label = add('input', {name: 'related_label'});
  const preview = add('a', {'data-rv-link-preview': ''});
  const clear = add('button', {'data-rv-link-clear': ''});
  const copy = add('button', {'data-rv-link-copy': ''});
  url.value = options.url || '';
  label.value = options.label || '';
  const data = document.appendChild(new Element('script', {id: 'rv-link-products'}));
  data.textContent = JSON.stringify(options.products || catalog);
  const navigator = options.navigator || {};
  vm.runInNewContext(adminScript, {document, navigator}, {filename: 'resource_video_links.js'});
  return {
    document, search, results, status, url, label, preview, clear, copy,
    searchFor(value) { search.value = value; search.fire('input'); },
    typeUrl(value) { url.value = value; url.fire('input'); }
  };
}

function makePublic(options = {}) {
  const document = new Document();
  const ids = {};
  for (const id of ['videoSearch', 'videoSort', 'videoGrid', 'videoLoad', 'videoLoadWrap', 'videoEmpty',
    'videoSectionTitle', 'videoSectionDesc', 'videoListTitle', 'productSubcategoryBar',
    'videoModal', 'videoPlayer', 'videoLinkActions', 'videoRelatedLink', 'videoDescriptionDialog', 'videoDescriptionTitle', 'videoDescriptionContent']) {
    ids[id] = document.appendChild(new Element(id === 'videoRelatedLink' ? 'a' : 'div', {id}));
  }
  ids.videoLinkActions.hidden = true;
  const addCard = (relatedUrl, relatedLabel) => {
    const card = ids.videoGrid.appendChild(new Element('article', {'data-video-card': ''}));
    card.dataset = {main: 'product', sub: 'track-lights', title: 'Test lighting video', desc: '',
      date: '2026-10-11', popular: '0', source: 'local', video: '/uploads/test.mp4', relatedUrl, relatedLabel};
    card.play = card.appendChild(new Element('button', {'data-video-open': ''}));
    card.appendChild(new Element('h3')).textContent = 'Test lighting video';
    card.preview = card.appendChild(new Element('p', {'data-description-preview': '', class: 'rv-card-description'}));
    card.preview.clientHeight = 100; card.preview.scrollHeight = relatedUrl ? 180 : 100;
    card.preview.textContent = 'A complete video description';
    card.bodyLink = card.preview.appendChild(new Element('a', {href: '/products/track-lights/armi'}));
    card.bodyLink.textContent = 'ARMI';
    card.more = card.appendChild(new Element('button', {'data-description-open': ''}));
    return card;
  };
  const linked = addCard('/products/track-lights/armi', 'ARMI Details');
  const unlinked = addCard('', '');
  if (options.highlights) {
    const template = unlinked.appendChild(new Element('template', {'data-video-highlights': ''}));
    template.content = new Element('fragment');
    const section = template.content.appendChild(new Element('section', {class: 'rv-highlights'}));
    section.appendChild(new Element('h3')).textContent = 'REDLINE at a Glance';
    const list = section.appendChild(new Element('ul'));
    for (const line of ['Two sizes – Ø55 mm and Ø75 mm', 'Trimmed and trimless', '0']) {
      list.appendChild(new Element('li')).textContent = line;
    }
  }
  const close = document.appendChild(new Element('button', {'data-video-close': ''}));
  // Only server-rendered description literals are substituted; all event logic is real.
  const executable = publicScript.replace(/<\?=[\s\S]*?\?>/g, '""');
  vm.runInNewContext(executable, {document, window: {addEventListener() {}}}, {filename: 'resources-videos-inline.js'});
  document.fire('DOMContentLoaded');
  return {ids, linked, unlinked, close, click(target) { document.fire('click', {target}); }};
}

let checks = 0;
async function test(name, callback) {
  await callback();
  checks++;
  console.log('PASS ' + name);
}

async function main() {
  await test('Multiple search words must all match and model punctuation is ignored', () => {
    const env = makeAdmin();
    env.searchFor('ARMI 45');
    assert.equal(env.results.children.length, 1);
    assert.equal(env.results.children[0].children[0].textContent, 'ARMI 45 Spotlight');
    env.searchFor('at1707 013.m1707');
    assert.equal(env.results.children.length, 1);
    env.searchFor('AT1707_013M1707');
    assert.equal(env.results.children.length, 1);
    env.searchFor('ARMI ZENITH');
    assert.equal(env.results.children.length, 0);
  });

  await test('Choosing a product fills its URL and default button label without submitting', () => {
    const env = makeAdmin();
    env.searchFor('ARMI 45');
    const button = env.results.children[0];
    assert.equal(button.type, 'button');
    button.click();
    assert.equal(env.url.value, catalog[1].url);
    assert.equal(env.label.value, 'View Product');
    assert.equal(env.preview.getAttribute('href'), catalog[1].url);
    assert.equal(env.preview.hidden, false);
    assert.equal(env.results.hidden, true);
  });

  await test('Manual URL and custom label survive further searching and preview refresh', () => {
    const env = makeAdmin({label: 'Download Specifications'});
    env.searchFor('ARMI 45');
    env.results.children[0].click();
    assert.equal(env.label.value, 'Download Specifications');
    const manual = 'https://example.test/catalog.pdf?size=45&source=video#page=2';
    env.typeUrl(manual);
    env.searchFor('ZENITH');
    assert.equal(env.url.value, manual);
    assert.equal(env.label.value, 'Download Specifications');
    assert.equal(env.preview.getAttribute('href'), manual);
  });

  await test('Clipboard rejection selects the typed URL for manual copy', async () => {
    const writes = [];
    const env = makeAdmin({url: ' https://example.test/details ', navigator: {clipboard: {
      writeText(value) { writes.push(value); return Promise.reject(new Error('Clipboard permission denied')); }
    }}});
    env.copy.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(writes, ['https://example.test/details']);
    assert.equal(env.url.focused, true);
    assert.equal(env.url.selected, true);
    assert.match(env.status.textContent, /Ctrl\/Cmd\+C/);
  });

  await test('Missing clipboard API falls back to selecting the URL and empty copy gives feedback', () => {
    const env = makeAdmin({url: '/products/downlights/zenith'});
    env.copy.click();
    assert.equal(env.url.focused, true);
    assert.equal(env.url.selected, true);
    const empty = makeAdmin();
    empty.copy.click();
    assert.match(empty.status.textContent, /请先填写/);
  });

  await test('Clearing removes URL, label, search results and preview href', () => {
    const env = makeAdmin();
    env.searchFor('ARMI 45');
    env.results.children[0].click();
    env.clear.click();
    for (const input of [env.url, env.label, env.search]) assert.equal(input.value, '');
    assert.equal(env.results.children.length, 0);
    assert.equal(env.results.hidden, true);
    assert.equal(env.status.textContent, '');
    assert.equal(env.preview.hidden, true);
    assert.equal(env.preview.getAttribute('href'), null);
  });

  await test('No results gives manual-entry feedback while retaining an existing URL', () => {
    const env = makeAdmin({url: 'https://example.test/custom'});
    env.searchFor('missing-model-999999');
    assert.equal(env.results.children.length, 0);
    assert.match(env.status.textContent, /未找到产品/);
    assert.equal(env.url.value, 'https://example.test/custom');
  });

  await test('Search Enter never submits, selects a visible result, and ignores hidden results', () => {
    const env = makeAdmin();
    env.searchFor('ARMI 45');
    assert.equal(env.search.fire('keydown', {key: 'Enter'}).defaultPrevented, true);
    assert.equal(env.url.value, catalog[1].url);
    env.typeUrl('/manually-entered');
    assert.equal(env.search.fire('keydown', {key: 'Enter'}).defaultPrevented, true);
    assert.equal(env.url.value, '/manually-entered');
    env.searchFor('no-such-product');
    assert.equal(env.search.fire('keydown', {key: 'Enter'}).defaultPrevented, true);
    assert.equal(env.url.value, '/manually-entered');
  });

  await test('Opening an unlinked video removes the previous video CTA and reopening restores it', () => {
    const env = makePublic();
    env.click(env.linked.play);
    assert.equal(env.ids.videoLinkActions.hidden, false);
    assert.equal(env.ids.videoRelatedLink.getAttribute('href'), env.linked.dataset.relatedUrl);
    assert.equal(env.ids.videoRelatedLink.textContent, 'ARMI Details');
    assert.equal(env.ids.videoModal.getAttribute('aria-hidden'), 'false');
    env.click(env.close);
    assert.equal(env.ids.videoModal.getAttribute('aria-hidden'), 'true');
    assert.equal(env.ids.videoPlayer.innerHTML, '');
    env.click(env.unlinked.play);
    assert.equal(env.ids.videoLinkActions.hidden, true);
    assert.equal(env.ids.videoRelatedLink.getAttribute('href'), null);
    assert.equal(env.ids.videoRelatedLink.textContent, '');
    env.click(env.linked.play);
    assert.equal(env.ids.videoLinkActions.hidden, false);
    assert.equal(env.ids.videoRelatedLink.getAttribute('href'), env.linked.dataset.relatedUrl);
  });

  await test('Description links navigate separately from playback and full-text dialog retains anchors', () => {
    const env = makePublic();
    env.click(env.linked.bodyLink);
    assert.equal(env.ids.videoPlayer.innerHTML, '');
    assert.equal(env.ids.videoModal.getAttribute('aria-hidden'), null);
    assert.equal(env.linked.more.hidden, false);
    assert.equal(env.unlinked.more.hidden, true);
    env.click(env.linked.more);
    assert.equal(env.ids.videoDescriptionDialog.getAttribute('open'), '');
    assert.equal(env.ids.videoDescriptionTitle.textContent, 'Test lighting video');
    const full = env.ids.videoDescriptionContent.children[0];
    assert.equal(full.getAttribute('class'), null);
    assert.equal(full.querySelector('a').getAttribute('href'), '/products/track-lights/armi');
    assert.equal(env.ids.videoPlayer.innerHTML, '');
    env.click(full.querySelector('a'));
    assert.equal(env.ids.videoPlayer.innerHTML, '');
  });

  await test('Highlights keep More available for short descriptions and do not leak into another video dialog', () => {
    const env = makePublic({highlights: true});
    assert.equal(env.unlinked.more.hidden, false);
    env.click(env.unlinked.more);
    assert.equal(env.ids.videoDescriptionContent.children.length, 2);
    const highlights = env.ids.videoDescriptionContent.children[1];
    assert.equal(highlights.querySelector('h3').textContent, 'REDLINE at a Glance');
    assert.deepEqual(highlights.querySelectorAll('li').map(line => line.textContent),
      ['Two sizes – Ø55 mm and Ø75 mm', 'Trimmed and trimless', '0']);
    assert.equal(env.ids.videoPlayer.innerHTML, '');
    env.click(env.linked.more);
    assert.equal(env.ids.videoDescriptionContent.children.length, 1);
    assert.equal(env.ids.videoDescriptionContent.querySelector('section'), null);
  });

  console.log('resource video link contract passed (' + checks + ' groups)');
}

main().catch(error => { console.error(error); process.exitCode = 1; });
