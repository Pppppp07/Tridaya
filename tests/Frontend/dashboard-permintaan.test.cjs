const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/simtlhp.js'), 'utf8');
const dashboard = script.indexOf('  function pasangDasbor() {');
const start = script.indexOf('    function muat(alamat, o) {', dashboard);
const end = script.indexOf('    function kirimUbah(', start);
assert.ok(dashboard >= 0 && start > dashboard && end > start);

function harness({ abort = true } = {}) {
  const requests = [], historyEntries = [], renders = [], attributes = new Map();
  const location = { href: 'http://test/ringkasan' };
  const form = {
    action: 'http://test/ringkasan', innerHTML: '',
    setAttribute(name, value) { attributes.set(name, value); },
    removeAttribute(name) { attributes.delete(name); },
  };
  const sandbox = {
    form, location, URL, AbortController,
    window: { AbortController: abort ? AbortController : undefined },
    history: { pushState(s, t, u) { historyEntries.push(u); }, replaceState(s, t, u) { historyEntries.push(u); } },
    DOMParser: class { parseFromString(html) { return { querySelector() { return html === 'missing' ? null : { innerHTML: html }; } }; } },
    fetch(url, options) {
      let resolve, reject;
      const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
      requests.push({ url, options, resolve, reject }); return promise;
    },
    potretIsi: () => ({}), wadahTampak: () => new Set(), tutupMenu() {}, sembunyikanKotak() {},
    pasangTumbuh() {}, pasangSifatLama() {}, siapkan() {}, sambungIsi() { renders.push(form.innerHTML); },
  };
  vm.runInNewContext('var nomorMuat = 0, pengendaliMuat = null;\n' + script.slice(start, end) + '\nthis.load = muat;', sandbox);
  return { load: sandbox.load, form, location, requests, historyEntries, renders, attributes };
}

async function flush() { for (let i = 0; i < 12; i++) await Promise.resolve(); }
async function respond(request, { url = request.url, canonical = null, html = 'new', ok = true } = {}) {
  request.resolve({ ok, url, headers: { get() { return canonical; } }, text: async () => html });
  await flush();
}

for (const abort of [true, false]) {
  test(`Filter terakhir menang ketika jawaban lama datang belakangan (abort=${abort})`, async () => {
    const h = harness({ abort });
    h.load('http://test/ringkasan?jenis=LHP', { dorong: true });
    h.load('http://test/ringkasan?jenis=LHA', { dorong: true });
    if (abort) assert.equal(h.requests[0].options.signal.aborted, true);
    await respond(h.requests[1], { html: 'LHA' });
    await respond(h.requests[0], { html: 'LHP' });
    assert.deepEqual(h.renders, ['LHA']);
    assert.deepEqual(h.historyEntries, ['http://test/ringkasan?jenis=LHA']);
    assert.equal(h.attributes.has('aria-busy'), false);
  });

  test(`Kegagalan permintaan lama tidak memindahkan halaman baru (abort=${abort})`, async () => {
    const h = harness({ abort });
    h.load('http://test/ringkasan?jenis=LHP');
    h.load('http://test/ringkasan?jenis=LHA');
    h.requests[0].reject(new Error('network'));
    await flush();
    assert.equal(h.location.href, 'http://test/ringkasan');
    assert.equal(h.attributes.get('aria-busy'), 'true');
    await respond(h.requests[1], { html: 'LHA' });
    assert.deepEqual(h.renders, ['LHA']);
  });
}

test('Alamat bersih dipakai tanpa pengalihan kedua dan tanpa menyimpan isi sensitif', async () => {
  const h = harness();
  h.load('http://test/ringkasan?ubah=jenis:LHA', { dorong: true });
  assert.equal(h.requests[0].options.headers['X-Simtlhp-Fragment'], 'isi');
  await respond(h.requests[0], { canonical: 'http://test/ringkasan?jenis=LHA', html: 'LHA' });
  assert.deepEqual(h.historyEntries, ['http://test/ringkasan?jenis=LHA']);
  assert.equal(h.requests.length, 1);
});

test('Sesi berakhir tetap mengantar ke halaman login', async () => {
  const h = harness();
  h.load('http://test/ringkasan?jenis=LHA');
  await respond(h.requests[0], { url: 'http://test/masuk', html: 'login' });
  assert.equal(h.location.href, 'http://test/masuk');
  assert.deepEqual(h.renders, []);
});

test('Respons server gagal tidak mengganti data dashboard dengan halaman error', async () => {
  const h = harness();
  h.load('http://test/ringkasan?jenis=LHA');
  await respond(h.requests[0], { ok: false, html: 'error' });
  assert.deepEqual(h.renders, []);
  assert.equal(h.location.href, 'http://test/ringkasan?jenis=LHA');
});
