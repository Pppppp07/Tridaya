/* Peringatan dan keluar otomatis saat sesi diam. Batasnya dijaga server
   (BatasDiam); skrip ini memberi tahu lebih dulu dan menutup halaman. */
(function (akar) {
  'use strict';

  var KABAR = 60;       // aktivitas dikabarkan ke server paling sering tiap 60 detik
  var PERINGATAN = 60;  // peringatan muncul 60 detik sebelum habis

  /* Inti tanpa DOM, supaya bisa diuji (tests/Frontend/sesi-diam.test.cjs).
     tanya()/kabari() → Promise sisa detik menurut server, null bila sesi sudah berakhir. */
  function buatPenjaga(o) {
    var batas = o.batas * 1000;
    var kontak = o.kini();   // permintaan terakhir yang dihitung server
    var gerak = 0;           // aktivitas terakhir di halaman ini
    var jam = null, sibuk = false, antre = null, tampil = false, selesai = false;

    function akhir() { return kontak + batas; }
    function jadwal(ms) {
      if (jam !== null) o.lepasJam(jam);
      jam = selesai ? null : o.pasangJam(periksa, Math.max(0, ms));
    }
    function berhenti(alasan, dariLuar) {
      if (selesai) return;
      selesai = true;
      if (jam !== null) o.lepasJam(jam);
      jam = null;
      o.akhiri(alasan, !!dariLuar);
    }
    function terima(sisa) {
      if (sisa === null) return berhenti('diam');
      kontak = o.kini() - (batas - sisa * 1000);
      if (sisa > PERINGATAN) {
        if (tampil) { tampil = false; o.tutup(); }
        return jadwal((sisa - PERINGATAN) * 1000);
      }
      if (!tampil) { tampil = true; o.peringatkan(); }
      jadwal(Math.max(sisa, 1) * 1000);
    }
    // Server tak terjangkau: ikuti hitungan sendiri, coba lagi sebentar lagi.
    function gagal() {
      var sisa = akhir() - o.kini();
      if (sisa <= 0) return berhenti('diam');
      if (sisa <= PERINGATAN * 1000 && !tampil) { tampil = true; o.peringatkan(); }
      jadwal(Math.min(sisa, 15000));
    }
    function minta(fn) {
      if (selesai) return;
      if (sibuk) { antre = fn; return; }
      sibuk = true;
      fn().then(function (sisa) { sibuk = false; terima(sisa); lanjutAntre(); },
        function () { sibuk = false; gagal(); lanjutAntre(); });
    }
    function lanjutAntre() {
      var f = antre;
      antre = null;
      if (f) minta(f);
    }
    function periksa() {
      if (selesai) return;
      var sisa = akhir() - o.kini();
      if (sisa > PERINGATAN * 1000) return jadwal(sisa - PERINGATAN * 1000);
      // Masih bekerja sejak kabar terakhir: perpanjang tanpa peringatan.
      if (!tampil && gerak > kontak) return minta(o.kabari);
      // Tanya dulu: tab lain mungkin masih dipakai.
      minta(o.tanya);
    }

    jadwal(batas - PERINGATAN * 1000);
    return {
      periksa: periksa,
      sisa: function () { return akhir() - o.kini(); },
      // Saat peringatan tampil, hanya "Tetap masuk" yang memperpanjang.
      aktivitas: function () {
        if (selesai || tampil) return;
        gerak = o.kini();
        if (gerak - kontak >= KABAR * 1000) minta(o.kabari);
      },
      lanjut: function () { if (!selesai) { gerak = o.kini(); minta(o.kabari); } },
      dariTabLain: function (alasan) { berhenti(alasan, true); }
    };
  }

  akar.SimtlhpSesi = { buatPenjaga: buatPenjaga };

  if (typeof document === 'undefined') return;
  var b = document.body, d = b && b.dataset;
  if (!d || !d.sesiBatas) return;

  // Alamat dari server dipakai dengan asal halaman ini (APP_URL bisa berbeda host).
  function di(u) { var x = new URL(u, location.href); return location.origin + x.pathname + x.search; }
  var ALAMAT = di(d.sesi), BERAKHIR = di(d.sesiBerakhir), MASUK = di(d.sesiMasuk);
  var JALUR_KELUAR = new URL(d.sesiKeluar, location.href).pathname;
  var KUNCI = 'simtlhp.sesi.keluar';
  var kotak = document.querySelector('[data-sesi-peringatan]');
  var hitung = kotak && kotak.querySelector('[data-sesi-hitung]');
  var tombolLanjut = kotak && kotak.querySelector('[data-sesi-lanjut]');
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var detak = null, fokusLama = null, penjaga;

  function minta(metode) {
    return fetch(ALAMAT, { method: metode, credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token } })
      .then(function (r) {
        if (r.status === 401 || r.status === 419) return null;
        if (!r.ok) throw new Error('sesi ' + r.status);
        return r.json().then(function (j) {
          if (typeof j.sisa !== 'number') throw new Error('sesi');
          return j.sisa;
        });
      });
  }
  function kabarkan(alasan) {
    try { localStorage.setItem(KUNCI, JSON.stringify({ alasan: alasan, t: Date.now() })); } catch (e) { /* tanpa penyimpanan */ }
  }
  function gambarSisa() {
    var s = Math.max(0, Math.ceil(penjaga.sisa() / 1000));
    if (hitung) hitung.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  }

  penjaga = buatPenjaga({
    batas: parseInt(d.sesiBatas, 10),
    kini: function () { return Date.now(); },
    pasangJam: function (f, ms) { return setTimeout(f, ms); },
    lepasJam: function (id) { clearTimeout(id); },
    tanya: function () { return minta('GET'); },
    kabari: function () { return minta('POST'); },
    peringatkan: function () {
      if (!kotak) return;
      fokusLama = document.activeElement;
      gambarSisa();
      detak = setInterval(gambarSisa, 1000);
      kotak.hidden = false;
      if (tombolLanjut) tombolLanjut.focus();
    },
    tutup: function () {
      if (!kotak) return;
      clearInterval(detak);
      kotak.hidden = true;
      if (fokusLama && fokusLama.focus) fokusLama.focus();
    },
    akhiri: function (alasan, dariLuar) {
      clearInterval(detak);
      if (!dariLuar) kabarkan(alasan);
      location.replace(alasan === 'keluar' ? MASUK
        : BERAKHIR + (dariLuar ? '' : '?kembali=' + encodeURIComponent(location.pathname + location.search)));
    }
  });

  ['keydown', 'pointerdown', 'wheel', 'touchstart', 'scroll'].forEach(function (n) {
    addEventListener(n, penjaga.aktivitas, { capture: true, passive: true });
  });
  var gerakTerakhir = 0;
  addEventListener('pointermove', function () {
    var t = Date.now();
    if (t - gerakTerakhir > 5000) { gerakTerakhir = t; penjaga.aktivitas(); }
  }, { passive: true });

  // Tab yang tertidur atau laptop yang baru bangun: periksa saat kembali terlihat.
  document.addEventListener('visibilitychange', function () { if (!document.hidden) penjaga.periksa(); });
  addEventListener('focus', penjaga.periksa);
  addEventListener('pageshow', function (e) { if (e.persisted) penjaga.periksa(); });

  // Keluar di satu tab, tab lain ikut.
  addEventListener('storage', function (e) {
    if (e.key !== KUNCI || !e.newValue) return;
    var v = null;
    try { v = JSON.parse(e.newValue); } catch (x) { return; }
    penjaga.dariTabLain(v && v.alasan === 'keluar' ? 'keluar' : 'diam');
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.action && new URL(f.action, location.href).pathname === JALUR_KELUAR) kabarkan('keluar');
  }, true);

  if (kotak) {
    if (tombolLanjut) tombolLanjut.addEventListener('click', penjaga.lanjut);
    kotak.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); penjaga.lanjut(); return; }
      if (e.key !== 'Tab') return;
      var tombol = kotak.querySelectorAll('button');
      var awal = tombol[0], ujung = tombol[tombol.length - 1];
      if (e.shiftKey && document.activeElement === awal) { e.preventDefault(); ujung.focus(); }
      else if (!e.shiftKey && document.activeElement === ujung) { e.preventDefault(); awal.focus(); }
    });
  }
})(typeof window !== 'undefined' ? window : globalThis);
