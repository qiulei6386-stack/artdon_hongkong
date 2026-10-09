'use strict';

// Run without a browser, network, application bootstrap, or database.
// Exercise the actual inline tracker and the existing AJAX response chains.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'includes/google_analytics.php'), 'utf8');
const tracker = [...php.matchAll(/<script>([\s\S]*?)<\/script>/g)]
  .map(match => match[1]).find(code => code.includes('window.artdonTrackInquiryLead ='));
assert.ok(tracker, 'Extract the actual shared tracker from its PHP template');

const nonce = '0123456789abcdef0123456789abcdef';
const secondNonce = 'abcdef0123456789abcdef0123456789';
const committed = id => ({ok: true, status: 'ok', lead_created: true, lead_event_id: id});
const expected = [['event', 'generate_lead', {form_name: 'contact_inquiry'}]];
let checks = 0;

function makeEnvironment(options = {}) {
  const events = [];
  const writes = [];
  const listeners = new Map();
  const storage = options.storage || new Map();
  const jar = options.jar || new Map();
  const document = {
    readyState: options.readyState || 'complete',
    addEventListener(name, callback, config) {
      const entries = listeners.get(name) || [];
      entries.push({callback, once: !!(config && config.once)});
      listeners.set(name, entries);
    }
  };
  Object.defineProperty(document, 'cookie', {
    get() {
      if (options.cookieBlocked) throw new Error('Cookie access blocked');
      return [...jar].map(([name, value]) => name + '=' + value).join('; ');
    },
    set(value) {
      if (options.cookieBlocked) throw new Error('Cookie access blocked');
      writes.push(value);
      const [pair] = value.split(';');
      const equal = pair.indexOf('=');
      const name = pair.slice(0, equal);
      const content = pair.slice(equal + 1);
      if (/Max-Age=0(?:;|$)/i.test(value)) jar.delete(name);
      else jar.set(name, content);
    }
  });
  const window = {
    sessionStorage: {
      getItem(key) {
        if (options.storageBlocked) throw new Error('Storage access blocked');
        return storage.get(key) || null;
      },
      setItem(key, value) {
        if (options.storageBlocked) throw new Error('Storage access blocked');
        storage.set(key, value);
      }
    },
    gtag(...args) {
      if (options.analyticsThrows) throw new Error('Analytics unavailable');
      events.push(JSON.parse(JSON.stringify(args)));
    }
  };
  if (options.analyticsMissing) delete window.gtag;
  const context = vm.createContext({window, document, location: {search: options.search || ''}});
  vm.runInContext(tracker, context, {filename: 'inline-inquiry-lead.js'});
  return {
    context, window, document, events, writes, storage, jar,
    fire(name) {
      const entries = listeners.get(name) || [];
      listeners.set(name, entries.filter(entry => !entry.once));
      for (const entry of entries) entry.callback();
    }
  };
}

function test(name, callback) {
  callback();
  checks++;
  console.log('PASS ' + name);
}

test('Committed success sends only the fixed generate_lead parameters', () => {
  const env = makeEnvironment();
  env.window.artdonTrackInquiryLead({...committed(nonce), email: 'private@example.test', message: 'Private content'});
  assert.deepEqual(env.events, expected);
  assert.ok(env.storage.has('artdon_ga4_lead_' + nonce));
});

test('Fake success, failure, and malformed metadata send no event', () => {
  const env = makeEnvironment();
  const invalid = [
    null, undefined, {}, {ok: true},
    {ok: true, lead_created: false, lead_event_id: nonce},
    {ok: false, status: 'error', lead_created: true, lead_event_id: nonce},
    {ok: 1, lead_created: true, lead_event_id: nonce},
    {ok: true, lead_created: 'true', lead_event_id: nonce},
    committed(''), committed(nonce.slice(1)), committed(nonce + '0'),
    committed(nonce.toUpperCase()), committed('?' + nonce),
    committed([nonce]), committed({toString() { return nonce; }})
  ];
  for (const result of invalid) env.window.artdonTrackInquiryLead(result);
  assert.deepEqual(env.events, []);
});

test('Repeated receipt is deduplicated in memory and across page instances', () => {
  const storage = new Map();
  const first = makeEnvironment({storage});
  first.window.artdonTrackInquiryLead(committed(nonce));
  first.window.artdonTrackInquiryLead(committed(nonce));
  assert.deepEqual(first.events, expected);
  const second = makeEnvironment({storage});
  second.window.artdonTrackInquiryLead(committed(nonce));
  assert.deepEqual(second.events, []);
  second.window.artdonTrackInquiryLead(committed(secondNonce));
  assert.deepEqual(second.events, expected);
});

test('Blocked storage still sends once and does not break the caller', () => {
  const env = makeEnvironment({storageBlocked: true});
  assert.doesNotThrow(() => env.window.artdonTrackInquiryLead(committed(nonce)));
  assert.doesNotThrow(() => env.window.artdonTrackInquiryLead(committed(nonce)));
  assert.deepEqual(env.events, expected);
});

test('Native receipt waits for DOM readiness, deletes its cookie, and never replays', () => {
  const jar = new Map([['unrelated', 'keep'], ['artdon_ga4_inquiry_lead', nonce]]);
  const storage = new Map();
  const env = makeEnvironment({jar, storage, readyState: 'loading'});
  assert.deepEqual(env.events, []);
  assert.ok(jar.has('artdon_ga4_inquiry_lead'));
  env.fire('DOMContentLoaded');
  assert.deepEqual(env.events, expected);
  assert.equal(jar.has('artdon_ga4_inquiry_lead'), false);
  assert.equal(jar.get('unrelated'), 'keep');
  assert.equal(env.writes.length, 1);
  assert.match(env.writes[0], /Max-Age=0; Path=\/; SameSite=Lax/);
  env.fire('DOMContentLoaded');
  assert.deepEqual(env.events, expected);
  const refresh = makeEnvironment({jar, storage});
  assert.deepEqual(refresh.events, []);
  jar.set('artdon_ga4_inquiry_lead', nonce);
  const repeatedCookie = makeEnvironment({jar, storage});
  assert.deepEqual(repeatedCookie.events, []);
  assert.equal(jar.has('artdon_ga4_inquiry_lead'), false);
});

test('A forged inquiry=ok URL and invalid cookie do not imply a saved lead', () => {
  const plain = makeEnvironment({search: '?inquiry=ok'});
  assert.deepEqual(plain.events, []);
  const badCookie = makeEnvironment({jar: new Map([['artdon_ga4_inquiry_lead', 'invalid']])});
  assert.deepEqual(badCookie.events, []);
});

test('Cookie denial, missing Analytics, and Analytics exceptions stay contained', () => {
  assert.doesNotThrow(() => makeEnvironment({cookieBlocked: true}));
  const absent = makeEnvironment({analyticsMissing: true});
  assert.doesNotThrow(() => absent.window.artdonTrackInquiryLead(committed(nonce)));
  assert.deepEqual(absent.events, []);
  const broken = makeEnvironment({analyticsThrows: true});
  assert.doesNotThrow(() => broken.window.artdonTrackInquiryLead(committed(nonce)));
  assert.deepEqual(broken.events, []);
  assert.equal(broken.storage.size, 0, 'A failed queue operation is not marked sent');
  assert.doesNotThrow(() => makeEnvironment({analyticsThrows: true, jar: new Map([['artdon_ga4_inquiry_lead', nonce]])}));
});

function responseChain(file, startMarker, endMarker) {
  const source = fs.readFileSync(path.join(root, file), 'utf8');
  const start = source.indexOf(startMarker);
  const end = source.indexOf(endMarker, start);
  assert.ok(start >= 0 && end > start, 'Extract existing response chain in ' + file);
  return '__submissionPromise = ' + source.slice(start, end);
}

const chains = {
  contact: responseChain('contact.php', 'fetch(form.action,', '\n  });'),
  floating: responseChain('includes/floating_actions.php', "fetch(form.getAttribute('action')", '\n    });')
};

async function submitChain(kind, result, options = {}) {
  const env = makeEnvironment();
  let resets = 0;
  let refreshes = 0;
  let trackerCalls = 0;
  const actualTracker = env.window.artdonTrackInquiryLead;
  env.window.artdonTrackInquiryLead = data => { trackerCalls++; actualTracker(data); };
  env.window.ArtdonInquiryCaptcha = {refresh() { refreshes++; }};
  Object.assign(env.context, {
    form: {action: '/submit_inquiry.php', getAttribute() { return '/submit_inquiry.php'; }, reset() { resets++; }},
    btn: {disabled: true, textContent: 'Submitting...', getAttribute() { return 'Submit inquiry'; }},
    old: 'Send message', status: {textContent: '', className: ''},
    show() {}, showToast() {}, closeInquiry() {}, setTimeout() {},
    FormData: class FormData {},
    fetch() {
      if (options.networkError) return Promise.reject(new Error('Offline'));
      return Promise.resolve({
        ok: options.httpOk !== false,
        json() { return options.jsonError ? Promise.reject(new Error('Invalid JSON')) : Promise.resolve(result); }
      });
    }
  });
  vm.runInContext(chains[kind], env.context, {filename: kind + '-response-chain.js'});
  await env.context.__submissionPromise;
  return {events: env.events, resets, refreshes, trackerCalls};
}

(async function main() {
  for (const kind of ['contact', 'floating']) {
    const success = await submitChain(kind, committed(nonce));
    assert.deepEqual(success.events, expected);
    assert.equal(success.trackerCalls, 1);
    assert.equal(success.resets, 1);
    const silentlyBlocked = await submitChain(kind, {ok: true, status: 'ok', lead_created: false, lead_event_id: null});
    assert.deepEqual(silentlyBlocked.events, []);
    assert.equal(silentlyBlocked.resets, 1, 'Silent anti-spam success keeps its existing UI');
    const rejected = await submitChain(kind, {ok: false, status: 'captcha', lead_created: false}, {httpOk: false});
    assert.deepEqual(rejected.events, []);
    assert.equal(rejected.trackerCalls, 0);
    assert.equal(rejected.resets, 0);
    assert.equal(rejected.refreshes, 1);
    for (const options of [{networkError: true}, {jsonError: true}]) {
      const failed = await submitChain(kind, committed(nonce), options);
      assert.deepEqual(failed.events, []);
      assert.equal(failed.trackerCalls, 0);
      assert.equal(failed.resets, 0);
    }
    checks++;
    console.log('PASS ' + kind + ' actual AJAX chain covers saved, silent block, validation, JSON, and network outcomes');
  }
  console.log('Inquiry lead contract passed (' + checks + ' groups).');
})().catch(error => {
  console.error(error.stack || error);
  process.exitCode = 1;
});
