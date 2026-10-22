const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const kode = fs.readFileSync(path.join(__dirname, '../../public/js/simtlhp-sesi.js'), 'utf8');
const BATAS = 600;

async function tuntas() { for (let i = 0; i < 10; i++) await Promise.resolve(); }

/* Penjaga dengan jam dan server tiruan. Server menghitung diam dari `terakhir`. */
function siapkan({ putus = false } = {}) {
  const konteks = {};
  vm.runInNewContext(kode, konteks);
  let kini = 0, nomor = 0;
  const jam = new Map(), catatan = [];
  const server = { terakhir: 0 };
  const sisaServer = () => {
    const sisa = BATAS - Math.floor((kini - server.terakhir) / 1000);
    return sisa > 0 ? sisa : null;
  };
  const p = konteks.SimtlhpSesi.buatPenjaga({
    batas: BATAS,
    kini: () => kini,
    pasangJam: (f, ms) => { jam.set(++nomor, { f, t: kini + ms }); return nomor; },
    lepasJam: (id) => jam.delete(id),
    tanya: () => { catatan.push('tanya'); return putus ? Promise.reject(new Error('putus')) : Promise.resolve(sisaServer()); },
    kabari: () => {
      catatan.push('kabari');
      if (putus) return Promise.reject(new Error('putus'));
      const sisa = sisaServer();
      if (sisa !== null) server.terakhir = kini;
      return Promise.resolve(sisa === null ? null : BATAS);
    },
    peringatkan: () => catatan.push('peringatkan'),
    tutup: () => catatan.push('tutup'),
    akhiri: (alasan, luar) => catatan.push('akhiri:' + alasan + (luar ? ':luar' : '')),
  });
  async function sampai(detik) {
    const ujung = detik * 1000;
    for (;;) {
      let dulu = null;
      for (const [k, v] of jam) if (v.t <= ujung && (!dulu || v.t < dulu[1].t)) dulu = [k, v];
      if (!dulu) break;
      jam.delete(dulu[0]);
      kini = dulu[1].t;
      dulu[1].f();
      await tuntas();
    }
    kini = ujung;
  }
  return { p, sampai, catatan, server };
}

test('Diam: peringatan semenit sebelum habis, lalu keluar', async () => {
  const s = siapkan();
  await s.sampai(539);
  assert.deepEqual(s.catatan, []);
  await s.sampai(540);
  assert.deepEqual(s.catatan, ['tanya', 'peringatkan']);
  await s.sampai(600);
  assert.deepEqual(s.catatan, ['tanya', 'peringatkan', 'tanya', 'akhiri:diam']);
});

test('Aktivitas dikabarkan paling sering semenit dan menunda peringatan', async () => {
  const s = siapkan();
  await s.sampai(30); s.p.aktivitas();
  assert.deepEqual(s.catatan, []);
  await s.sampai(70); s.p.aktivitas(); await tuntas();
  assert.deepEqual(s.catatan, ['kabari']);
  await s.sampai(609);
  assert.equal(s.catatan.includes('peringatkan'), false);
  await s.sampai(610);
  assert.deepEqual(s.catatan.slice(-2), ['tanya', 'peringatkan']);
});

test('Aktivitas sesudah kabar terakhir memperpanjang tanpa peringatan', async () => {
  const s = siapkan();
  await s.sampai(30); s.p.aktivitas();
  await s.sampai(540);
  assert.deepEqual(s.catatan, ['kabari']);
  await s.sampai(1079);
  assert.equal(s.catatan.includes('peringatkan'), false);
});

test('Tab lain yang masih dipakai mencegah peringatan', async () => {
  const s = siapkan();
  s.server.terakhir = 300 * 1000;
  await s.sampai(540);
  assert.deepEqual(s.catatan, ['tanya']);
  await s.sampai(840);
  assert.deepEqual(s.catatan, ['tanya', 'tanya', 'peringatkan']);
});

test('Tetap masuk menutup peringatan; gerak tetikus saja tidak', async () => {
  const s = siapkan();
  await s.sampai(540);
  await s.sampai(550); s.p.aktivitas(); await tuntas();
  assert.deepEqual(s.catatan, ['tanya', 'peringatkan']);
  await s.sampai(560); s.p.lanjut(); await tuntas();
  assert.deepEqual(s.catatan, ['tanya', 'peringatkan', 'kabari', 'tutup']);
  await s.sampai(1000);
  assert.equal(s.catatan.some((c) => c.startsWith('akhiri')), false);
});

test('Server tak terjangkau: tetap keluar pada waktunya', async () => {
  const s = siapkan({ putus: true });
  await s.sampai(540);
  assert.deepEqual(s.catatan, ['tanya', 'peringatkan']);
  await s.sampai(600);
  assert.equal(s.catatan.at(-1), 'akhiri:diam');
});

test('Keluar di tab lain ikut menutup tab ini, sekali saja', async () => {
  const s = siapkan();
  s.p.dariTabLain('keluar');
  s.p.dariTabLain('diam');
  await s.sampai(700);
  assert.deepEqual(s.catatan, ['akhiri:keluar:luar']);
});
