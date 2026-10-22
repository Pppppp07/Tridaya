const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Jalankan fungsi produksi tanpa paket DOM/browser. Respons sengaja tetap
// dapat tiba setelah abort untuk menguji invalidasi, termasuk browser lama.
const script = fs.readFileSync(path.join(__dirname, '../../public/js/simtlhp.js'), 'utf8');
const start = script.indexOf('  function pasangCari() {');
const end = script.indexOf('  /* ================= preview berkas', start);
assert.ok(start >= 0 && end > start, 'Fungsi pencarian produksi ditemukan');

function node(tagName = 'DIV') {
  const listeners = new Map(), attributes = new Map();
  return {
    tagName, hidden: false, value: '', dataset: {},
    addEventListener(type, fn) {
      if (!listeners.has(type)) listeners.set(type, []);
      listeners.get(type).push(fn);
    },
    emit(type, data = {}) {
      const event = { target: this, defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; }, ...data };
      for (const fn of listeners.get(type) || []) fn(event);
      return event;
    },
    setAttribute(name, value) { attributes.set(name, value); },
    removeAttribute(name) { attributes.delete(name); },
    getAttribute(name) { return attributes.get(name) ?? null; },
  };
}

function harness({ abort = true } = {}) {
  const wrapper = node(), input = node('INPUT'), panel = node();
  const clear = node('BUTTON'), keyboardHint = node('KBD'), outside = node();
  const document = node(), location = { href: '/rekomendasi' };
  const requests = [], timers = new Map();
  let nextTimer = 0, visible = true, html = '', items = [];
  document.activeElement = input;
  wrapper.dataset.sumber = '/cari';
  wrapper.contains = el => [wrapper, input, panel, clear, keyboardHint, ...items].includes(el);
  Object.defineProperty(panel, 'innerHTML', {
    get() { return html; },
    set(value) {
      html = value;
      items = [...value.matchAll(/data-i="(\d+)"/g)].map(match => {
        const item = node('A'), classes = new Set();
        item.dataset.i = match[1];
        item.classList = { toggle(name, on) { if (on) classes.add(name); else classes.delete(name); },
          contains(name) { return classes.has(name); } };
        return item;
      });
    },
  });
  input.focus = () => { document.activeElement = input; input.emit('focus'); };
  input.blur = () => { document.activeElement = outside; };
  const $ = (selector, root) => {
    if (!root && selector === '[data-cari-glob]') return wrapper;
    if (root === wrapper) return { input, '.panel': panel, '.bersih': clear, kbd: keyboardHint }[selector];
    return null;
  };
  vm.runInNewContext(script.slice(start, end) + '\npasangCari();', {
    $, $$: (selector, root) => selector === '.item' && root === panel ? items : [],
    esc: value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;',
      '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])),
    tampak: () => visible, document, location,
    window: { AbortController: abort ? AbortController : undefined }, AbortController,
    setTimeout(fn) { const id = ++nextTimer; timers.set(id, fn); return id; },
    clearTimeout(id) { timers.delete(id); },
    fetch(url, options) {
      let resolve, reject;
      const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
      requests.push({ url, options, resolve, reject });
      return promise;
    },
  });
  return { wrapper, input, panel, clear, outside, document, location, requests,
    setVisible(value) { visible = value; },
    tick() { const waiting = [...timers.values()]; timers.clear(); waiting.forEach(fn => fn()); },
    type(value) { input.value = value; input.emit('input'); },
    key(key, data) { return input.emit('keydown', { key, ...data }); },
    items() { return items; },
  };
}

const flush = () => new Promise(resolve => setImmediate(resolve));
function results(title, count = 1) {
  return { lap: [], jumlah: count, rek: Array.from({ length: count }, (_, i) => ({
    link: '/rekomendasi/' + title + '/' + i, kode: title, cap: '', uraian: title,
    satker: 'Balai', nomorTemuan: i + 1,
  })) };
}
async function respond(request, data, options = {}) {
  request.resolve({ ok: true, json: async () => data, ...options });
  await flush();
}

test('Respons lama diabaikan segera setelah kata berubah, sebelum debounce berikutnya', async () => {
  const h = harness({ abort: false });
  h.type('aset'); h.tick();
  h.type('dokumen');
  await respond(h.requests[0], results('aset'));
  assert.equal(h.panel.hidden, true);
  h.tick();
  await respond(h.requests[1], results('dokumen'));
  assert.equal(h.panel.hidden, false);
  assert.match(h.panel.innerHTML, /dokumen/);
  assert.doesNotMatch(h.panel.innerHTML, /aset/);
});

test('Respons yang tiba terbalik tidak menimpa hasil terbaru', async () => {
  const h = harness();
  h.type('aset'); h.tick();
  h.type('dokumen'); h.tick();
  assert.equal(h.requests[0].options.signal.aborted, true);
  await respond(h.requests[1], results('dokumen'));
  await respond(h.requests[0], results('aset'));
  assert.match(h.panel.innerHTML, /dokumen/);
  assert.doesNotMatch(h.panel.innerHTML, /aset/);
  assert.equal(h.wrapper.getAttribute('aria-busy'), null);
});

for (const close of ['clear', 'Escape', 'focusout', 'outside']) {
  test(`Menutup melalui ${close} mencegah respons tertunda membuka panel lagi`, async () => {
    const h = harness();
    h.type('aset'); h.tick();
    assert.equal(h.wrapper.getAttribute('aria-busy'), 'true');
    if (close === 'clear') h.clear.emit('click');
    if (close === 'Escape') h.key('Escape');
    if (close === 'focusout') {
      h.document.activeElement = h.outside;
      h.wrapper.emit('focusout', { relatedTarget: h.outside });
    }
    if (close === 'outside') h.document.emit('mousedown', { target: h.outside });
    await respond(h.requests[0], results('aset'));
    assert.equal(h.panel.hidden, true);
    assert.equal(h.panel.innerHTML, '');
    assert.equal(h.wrapper.getAttribute('aria-busy'), null);
    h.key('Enter');
    assert.equal(h.location.href, '/rekomendasi');
  });
}

test('Enter sebelum hasil kata baru tersedia tidak membuka hasil kata sebelumnya', async () => {
  const h = harness();
  h.type('aset'); h.tick();
  await respond(h.requests[0], results('aset'));
  h.type('dokumen');
  h.key('Enter');
  assert.equal(h.location.href, '/rekomendasi');
  h.tick();
  await respond(h.requests[1], results('dokumen', 2));
  assert.equal(h.key('ArrowDown').defaultPrevented, true);
  assert.equal(h.items()[1].classList.contains('pilih'), true);
  assert.equal(h.key('Enter').defaultPrevented, true);
  assert.equal(h.location.href, '/rekomendasi/dokumen/1');
});

for (const failure of ['HTTP', 'network', 'JSON', 'schema']) {
  test(`Kegagalan ${failure} tampil sebagai gagal memuat, bukan hasil kosong`, async () => {
    const h = harness();
    h.type('aset'); h.tick();
    if (failure === 'network') { h.requests[0].reject(new TypeError('Offline')); await flush(); }
    else if (failure === 'HTTP') await respond(h.requests[0], null, { ok: false });
    else if (failure === 'JSON') await respond(h.requests[0], null, {
      json: async () => { throw new SyntaxError('Bukan JSON'); },
    });
    else await respond(h.requests[0], { message: 'Sesi berakhir' });
    assert.equal(h.panel.hidden, false);
    assert.match(h.panel.innerHTML, /belum dapat dimuat/);
    assert.doesNotMatch(h.panel.innerHTML, /Tidak ada yang cocok/);
    assert.equal(h.wrapper.getAttribute('aria-busy'), null);
    h.key('Enter');
    assert.equal(h.location.href, '/rekomendasi');
  });
}

test('Respons kosong tetap dibedakan dari kegagalan', async () => {
  const h = harness();
  h.type('tidakada'); h.tick();
  await respond(h.requests[0], results('tidakada', 0));
  assert.equal(h.panel.hidden, false);
  assert.match(h.panel.innerHTML, /Tidak ada yang cocok/);
});

test('Pintasan tidak mencuri fokus dari latar inert; IME tidak membuka hasil', async () => {
  const h = harness();
  h.document.activeElement = h.outside;
  h.setVisible(false);
  const blocked = h.document.emit('keydown', { key: 'k', ctrlKey: true, target: h.outside });
  assert.equal(blocked.defaultPrevented, false);
  assert.equal(h.document.activeElement, h.outside);
  h.setVisible(true);
  h.document.emit('keydown', { key: '/', target: h.outside });
  assert.equal(h.document.activeElement, h.input);
  h.type('aset'); h.tick();
  await respond(h.requests[0], results('aset'));
  h.key('Enter', { isComposing: true });
  assert.equal(h.location.href, '/rekomendasi');
});
