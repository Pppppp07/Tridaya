const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/js/simtlhp.js'), 'utf8');
const helperStart = script.indexOf('  function alamatPreview(');
const helperEnd = script.indexOf('  /* Ikon', helperStart);
const previewStart = script.indexOf('  function pasangPreview()');
const previewEnd = script.indexOf('  /* ================= penegasan', previewStart);
assert.ok(helperStart > 0 && helperEnd > helperStart && previewEnd > previewStart);

// DOM kecil: metadata harus menjadi teks. Pemakaian innerHTML dalam preview
// ditolak agar payload HTML tidak dapat lolos hanya karena mock tidak memparsenya.
function node(tagName = 'DIV') {
  const attrs = new Map(), events = new Map();
  let value = '';
  const el = { tagName, children: [], parentNode: null, dataset: {}, style: {}, className: '',
    appendChild(child) { this.children.push(child); child.parentNode = this; return child; },
    removeChild(child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; },
    setAttribute(k, v) { attrs.set(k, String(v)); },
    getAttribute(k) { return attrs.get(k) ?? null; },
    closest(s) {
      if (s === 'a[data-preview]' && this.tagName === 'A' && this.dataset.preview !== undefined) return this;
      if (s === '[data-tutup]' && attrs.has('data-tutup')) return this;
      return this.parentNode?.closest(s) ?? null;
    },
    addEventListener(k, fn) { if (!events.has(k)) events.set(k, new Set()); events.get(k).add(fn); },
    removeEventListener(k, fn) { events.get(k)?.delete(fn); },
    emit(k, e = {}) { for (const fn of events.get(k) ?? []) fn(e); },
  };
  Object.defineProperty(el, 'textContent', {
    get() { return value + this.children.map(c => c.textContent).join(''); },
    set(v) { value = String(v); this.children = []; },
  });
  Object.defineProperty(el, 'innerHTML', { set() { throw new Error('Preview tidak boleh memparse metadata sebagai HTML'); } });
  return el;
}

function harness() {
  const document = node(); document.body = node('BODY');
  document.createElement = tag => node(tag.toUpperCase());
  document.createTextNode = text => { const n = node('#TEXT'); n.textContent = text; return n; };
  const location = { href: 'https://tridaya.test/rekomendasi/1', origin: 'https://tridaya.test' };
  vm.runInNewContext(script.slice(helperStart, helperEnd) + script.slice(previewStart, previewEnd) + '\npasangPreview();',
    { document, location, URL });
  return { document, click(data = { nama: 'Bukti.pdf' }, href = '/berkas/1', modifiers = {}) {
    const a = node('A'); a.dataset.preview = typeof data === 'string' ? data : JSON.stringify(data);
    a.setAttribute('href', href);
    const e = { target: a, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; },
      stopPropagation() {}, ...modifiers };
    document.emit('click', e);
    return { e, overlay: document.body.children.at(-1) };
  } };
}
function all(el) { return [el, ...el.children.flatMap(all)]; }
function action(overlay) { return all(overlay).find(n => n.textContent === 'Buka berkas' || n.textContent === 'Buka link'); }

test('Nama, jenis, pengunggah, tanggal, dan link berbahaya hanya ditampilkan sebagai teks', () => {
  const h = harness();
  const payload = '<img src=x onerror="alert(1)"><script>alert(1)</script>\'"';
  const { overlay } = h.click({ nama: payload + '.pdf', jenis: payload, oleh: payload, tanggal: payload, link: payload });
  assert.ok(overlay.textContent.includes(payload));
  assert.ok(all(overlay).every(n => !['IMG', 'SCRIPT'].includes(n.tagName)));
  assert.equal(action(overlay).tagName, 'SPAN');
  assert.equal(action(overlay).getAttribute('aria-disabled'), 'true');
});

for (const url of ['javascript:alert(1)', 'JAVASCRIPT:alert(1)', 'data:text/html,<script>alert(1)</script>',
  'vbscript:msgbox(1)', 'file:///C:/secret.txt', 'https://lain.test/berkas/1', '//lain.test/1',
  'https://tridaya.test@lain.test/1', 'java\nscript:alert(1)']) {
  test('Href berkas ditolak: ' + JSON.stringify(url), () => {
    const { overlay } = harness().click({ nama: 'Bukti.pdf' }, url);
    assert.equal(action(overlay).tagName, 'SPAN');
    assert.equal(action(overlay).href, undefined);
  });
}

test('Rute berkas milik aplikasi tetap bisa dibuka, termasuk query dan fragment', () => {
  const { overlay } = harness().click({ nama: 'Bukti.pdf' }, '/berkas/12?versi=2#halaman');
  assert.equal(action(overlay).href, 'https://tridaya.test/berkas/12?versi=2#halaman');
});

test('Link bukti terbuka melalui gateway aplikasi dengan perlindungan tab baru', () => {
  const { overlay } = harness().click({ nama: 'Bukti.xlsx', link: 'https://drive.pu.go.id/1?x=2&y=3', buka: '/berkas/42/buka' });
  const a = action(overlay);
  assert.equal(a.href, 'https://tridaya.test/berkas/42/buka');
  assert.equal(a.target, '_blank'); assert.equal(a.rel, 'noopener noreferrer');
});

test('Link HTTPS tanpa gateway yang disetujui server tetap dinonaktifkan', () => {
  const { overlay } = harness().click({ nama: 'Bukti.pdf', link: 'https://drive.google.com/file/d/12345678901/view' });
  assert.equal(action(overlay).getAttribute('aria-disabled'), 'true');
});

for (const buka of ['https://situslain.test/1', '//situslain.test/1', 'javascript:alert(1)']) {
  test('Gateway preview lintas origin atau skrip ditolak: ' + buka, () => {
    const { overlay } = harness().click({ nama: 'Bukti.pdf', link: 'https://drive.google.com/file/d/12345678901/view', buka });
    assert.equal(action(overlay).getAttribute('aria-disabled'), 'true');
  });
}

test('Metadata JSON rusak/null/array tidak mematikan interaksi berikutnya', () => {
  const h = harness();
  for (const data of ['{rusak', 'null', '[]']) {
    const { e } = h.click(data, 'javascript:alert(1)');
    assert.equal(e.defaultPrevented, true);
    assert.equal(h.document.body.children.length, 0);
  }
  assert.ok(h.click().overlay);
});

test('Preview tetap bisa ditutup dengan Escape dan tombol, tanpa menumpuk overlay', () => {
  const h = harness();
  h.click(); h.document.emit('keydown', { key: 'Escape' });
  assert.equal(h.document.body.children.length, 0);
  const { overlay } = h.click();
  const button = all(overlay).find(n => n.tagName === 'BUTTON');
  overlay.emit('click', { target: button });
  assert.equal(h.document.body.children.length, 0);
});
