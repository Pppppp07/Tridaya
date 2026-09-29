/* Lapisan kenyamanan, padanan perilaku React di prototipe. Semuanya penambah,
   bukan penopang: tiap tugas tetap bisa dikerjakan kalau berkas ini gagal
   dimuat — filter berupa formulir biasa, baris berupa link, berkas
   membuka rutenya sendiri.

   Ditulis polos tanpa perkakas build: berkas ini disalin apa adanya, sama
   seperti berkas gayanya. */
(function () {
  'use strict';

  function $(s, akar) { return (akar || document).querySelector(s); }
  function $$(s, akar) { return Array.prototype.slice.call((akar || document).querySelectorAll(s)); }
  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  /* Link yang boleh jadi href: alamat web http/https saja (27 Sep) —
     padanan App\Rules\LinkAman::sah() dan `alamatWebSah` prototipe. */
  function linkSah(t) {
    t = String(t == null ? '' : t).trim();
    if (!t || /[\s\u0000-\u001f]/.test(t)) return false;
    try { var u = new URL(t); return (u.protocol === 'http:' || u.protocol === 'https:') && !!u.hostname; }
    catch (e) { return false; }
  }

  /* Ikon yang dipakai kotak yang dibangun skrip. Jalurnya sama persis dengan
     komponen `x-ikon`, jadi kotak buatan skrip tidak berbeda wajah dari yang
     digambar server. */
  var IKON = {
    awas: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    cek: '<path d="M20 6 9 17l-5-5"/>',
    titik: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="1"/>',
    silang: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    panah: '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
  };
  function ikon(nama, ukuran) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + ukuran + '" height="' + ukuran
      + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
      + ' stroke-linejoin="round" aria-hidden="true">' + (IKON[nama] || '') + '</svg>';
  }

  /* ================= isi yang diganti di tempat ================= */
  /* Prototipe (React) mengenali unsur yang sama sebelum dan sesudah isinya
     berubah: unsur itu tetap berdiri — gerak masuknya tidak berputar lagi,
     dan ukuran serta warnanya berubah halus lewat transisi CSS — sedangkan
     unsur yang baru muncul masuk dengan geraknya. Di sini isinya diganti utuh
     dari server, jadi "unsur yang sama" dikenali lewat kuncinya (26 Sep).

     Kunci: pangkalnya tombol bernilai terdekat (baris grafik, kartu, keping
     filter — nilainya unik) atau unsur ber-id/berlabel terdekat (panel),
     lalu jalur ke unsurnya: tag dan kelas pengenalnya (bukan kelas keadaan
     seperti redup), ditambah warnanya untuk potongan batang (potongan kosong
     tidak digambar, jadi urutannya bisa bergeser) atau urutannya di antara
     saudara sejenis. */
  var KELAS_KEADAAN = /^(redup|aktif|buka|tampil|sorot|ada|pilih)$/;
  function kelasPengenal(x) {
    var k = (x.getAttribute('class') || '').split(/\s+/);
    for (var i = 0; i < k.length; i++) if (k[i] && !KELAS_KEADAAN.test(k[i])) return k[i];
    return '';
  }
  function kunciUnsur(el, akar) {
    var pangkal = el.closest('button[value]');
    if (pangkal && !akar.contains(pangkal)) pangkal = null;
    if (!pangkal) {
      pangkal = el.closest('[id], [aria-labelledby]');
      if (pangkal && !akar.contains(pangkal)) pangkal = null;
    }
    var jalur = [];
    for (var x = el; x && x !== pangkal && x !== akar; x = x.parentElement) {
      var k = kelasPengenal(x), langkah = x.tagName + '.' + k;
      var warna = x.tagName === 'I' && x.style && x.style.background;
      if (warna) langkah += '@' + warna;
      else {
        var i = 0, s = x;
        while ((s = s.previousElementSibling)) if (s.tagName === x.tagName && kelasPengenal(s) === k) i++;
        langkah += ':' + i;
      }
      jalur.unshift(langkah);
    }
    var nama = !pangkal ? '' : pangkal.tagName === 'BUTTON' ? 'b:' + pangkal.value
      : '#' + (pangkal.id || pangkal.getAttribute('aria-labelledby'));
    return nama + '|' + jalur.join('>');
  }
  /* Yang boleh berubah halus: kartu angka, keping filter, dan isi panel
     grafik. Menu, pembuka menu, dan salinan pengukur keping dikelola skrip
     sendiri — keadaannya tidak disentuh. */
  var MORF = '.dsb-kpi, .dsb-kpi *, .dsb-keping > button, .dsb-keping > button *, .dsb-panel *, .dsb-rincian *';
  var BUKAN_MORF = '[aria-haspopup], [aria-haspopup] *, [data-menu] *, .dsb-ukur *';
  var SIFAT = ['style', 'class', 'aria-pressed', 'aria-expanded'];
  function setel(el, n, v) { if (v == null) el.removeAttribute(n); else el.setAttribute(n, v); }
  /* Sebelum diganti: unsur yang sedang tampil, dan sifat tiap unsur yang
     boleh berubah halus. */
  function potretIsi(akar) {
    var ada = new Set(), lama = new Map();
    $$('*', akar).forEach(function (el) {
      if (!el.getClientRects().length) return;
      var k = kunciUnsur(el, akar);
      ada.add(k);
      if (el.matches(MORF) && !el.matches(BUKAN_MORF)) {
        lama.set(k, SIFAT.map(function (n) { return el.getAttribute(n); }));
      }
    });
    return { ada: ada, lama: lama };
  }
  /* Tepat sesudah isinya diganti — sebelum browser sempat menghitung gaya
     unsur-unsur barunya: unsur yang tadi sudah tampil diberi lagi sifat
     lamanya. Kalau gaya barunya sempat terhitung lebih dulu, transisi dari
     yang lama ke yang baru justru saling membatalkan. Jangan membaca ukuran
     apa pun di sini. */
  function pasangSifatLama(akar, p) {
    var tunda = [];
    $$(MORF, akar).forEach(function (el) {
      if (el.matches(BUKAN_MORF)) return;
      var l = p.lama.get(kunciUnsur(el, akar));
      if (!l) return;
      var baru = SIFAT.map(function (n) { return el.getAttribute(n); });
      var beda = false;
      for (var i = 0; i < SIFAT.length; i++) if (baru[i] !== l[i]) { beda = true; setel(el, SIFAT[i], l[i]); }
      if (beda) tunda.push({ el: el, baru: baru, lama: l });
    });
    return tunda;
  }
  /* Sesudah halamannya siap: gerak masuk simt… pada unsur yang tadi sudah
     tampil dituntaskan seketika, lalu sifat barunya dipasang — transisi
     CSS-nya (lebar batang, potongan, tinggi pita, warna tombol) berjalan dari
     keadaan lama. */
  function sambungIsi(akar, p, tunda) {
    if (akar.getAnimations) {
      akar.getAnimations({ subtree: true }).forEach(function (a) {
        if (!/^simt/.test(a.animationName || '')) return;
        var t = a.effect && a.effect.target;
        if (!t || !p.ada.has(kunciUnsur(t, akar))) return;
        try { a.finish(); } catch (e) { /* gerak tanpa akhir tidak bisa dituntaskan */ }
      });
    }
    tunda.forEach(function (t) {
      for (var i = 0; i < SIFAT.length; i++) {
        /* Yang sementara itu diubah skrip lain dibiarkan. */
        if (t.baru[i] !== t.lama[i] && t.el.getAttribute(SIFAT[i]) === t.lama[i]) setel(t.el, SIFAT[i], t.baru[i]);
      }
    });
  }

  /* ================= keterangan (Info) ================= */
  /* Arah membuka dihitung saat ditekan, bukan dipatok: ikon di tepi layar
     akan melempar keterangannya ke luar pandangan kalau arahnya selalu sama. */
  function pasangInfo() {
    var terbuka = null;
    function tutup() {
      if (!terbuka) return;
      $('.isi', terbuka).hidden = true;
      $('button', terbuka).setAttribute('aria-expanded', 'false');
      terbuka = null;
    }
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('.info > button');
      if (!tombol) {
        if (terbuka && !e.target.closest('.info')) tutup();
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      var wadah = tombol.parentElement;
      if (terbuka === wadah) { tutup(); return; }
      tutup();
      var isi = $('.isi', wadah);
      var r = wadah.getBoundingClientRect();
      isi.classList.toggle('atas', window.innerHeight - r.bottom < 190);
      isi.classList.toggle('kiri', window.innerWidth - r.left < 300);
      isi.hidden = false;
      tombol.setAttribute('aria-expanded', 'true');
      terbuka = wadah;
    }, true);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });
  }

  /* ================= batang atas yang menempel ================= */
  /* Di ponsel batang atas ikut tergulir (22 Sep) — yang menempel tinggal
     batang laci, jadi jaraknya diukur dari batang itu. Sama dengan useLekat
     di prototipe. */
  function pasangLekat() {
    var bilah = $('.top'), laci = $('.nav-atas');
    if (!bilah) return;
    function ukur() {
      var satu = 0;
      if (getComputedStyle(bilah).position === 'sticky') {
        satu = (parseFloat(getComputedStyle(bilah).top) || 0) + bilah.offsetHeight;
      } else if (laci && getComputedStyle(laci).display !== 'none') {
        satu = laci.offsetHeight;
      }
      satu = Math.round(satu);
      document.body.style.setProperty('--lekat1', satu + 'px');
      document.body.style.setProperty('--lekat2', (satu + 44) + 'px');
    }
    ukur();
    if (window.ResizeObserver) {
      var pengamat = new ResizeObserver(ukur);
      pengamat.observe(bilah);
      if (laci) pengamat.observe(laci);
    }
    window.addEventListener('resize', ukur);
  }

  function jarakLekat(tambah) {
    var v = parseFloat(getComputedStyle(document.body).getPropertyValue('--lekat1'));
    return (isFinite(v) ? v : 72) + (tambah == null ? 14 : tambah);
  }

  /* ================= pintasan: antar ke sasaran lalu sorot (26 Sep) ================= */
  /* Padanan `gulirKe`, `gulirSeperlunya`, `sorotUnsur`, `bukaLewat`, dan
     `antarKe` prototipe. Kata Hizkia: tombol pintasan harus "langsung
     diarahkan ke pilihan tersebut dan di highlight … sistem melakukan scroll
     kebawah atau keatas sesuai dengan lokasi objek tersebut". Wadah yang
     tertutup dibuka lewat tombolnya sendiri, tata letaknya ditunggu, halaman
     digulir seperlunya, lalu unsur PERSISNYA disorot sekejap (data-sorot). */
  function gulirHalus(el, jeda) {
    var tujuan = Math.max(0, window.scrollY + el.getBoundingClientRect().top - jeda);
    /* "Kurangi animasi" di Profil → Tampilan (28 Sep) sama dengan permintaan perangkat. */
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.body.getAttribute('data-animasi') === 'kurang') {
      window.scrollTo(0, tujuan);
      return function () {};
    }
    var mula = window.scrollY, jarak = tujuan - mula;
    if (Math.abs(jarak) < 2) return function () {};
    var t0 = performance.now(), lama = 460, rangka = 0, batal = false;
    function langkah(t) {
      if (batal) return;
      var p = Math.min(1, (t - t0) / lama);
      /* melaju di tengah, melambat di dua ujungnya */
      var e = p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;
      window.scrollTo(0, mula + jarak * e);
      if (p < 1) rangka = requestAnimationFrame(langkah);
    }
    rangka = requestAnimationFrame(langkah);
    /* Tab yang tidak terlihat tidak diberi rangka gambar: kalau halamannya
       belum bergerak sama sekali, langsung dilompatkan. */
    var jaring = setTimeout(function () {
      if (!batal && Math.round(window.scrollY) === Math.round(mula)) window.scrollTo(0, tujuan);
    }, lama + 120);
    return function () { batal = true; cancelAnimationFrame(rangka); clearTimeout(jaring); };
  }
  /* Yang sudah terlihat utuh tidak digulir; yang belum ditaruh sedikit di
     bawah batang atas, atau tepat di bawahnya kalau lebih tinggi dari ruangnya. */
  function gulirSeperlunya(el) {
    var atas = jarakLekat(12), r = el.getBoundingClientRect(), layar = window.innerHeight;
    if (r.top >= atas && r.bottom <= layar - 12) return function () {};
    var ruang = layar - atas;
    return gulirHalus(el, r.height < ruang * 0.55 ? atas + Math.round(ruang * 0.16) : atas);
  }
  /* Satu tekan, satu yang ditunjuk: sorotan sebelumnya dilepas lebih dulu. */
  var sorotanKini = { unsur: [], jam: 0 };
  function sorotUnsur(unsur) {
    clearTimeout(sorotanKini.jam);
    sorotanKini.unsur.forEach(function (el) { el.removeAttribute('data-sorot'); });
    var daftar = Array.prototype.slice.call(unsur).filter(Boolean);
    /* Unsur yang sama ditunjuk lagi: geraknya diulang dari awal. */
    if (daftar.length) void daftar[0].offsetWidth;
    daftar.forEach(function (el) { el.setAttribute('data-sorot', ''); });
    sorotanKini = { unsur: daftar, jam: setTimeout(function () {
      daftar.forEach(function (el) { el.removeAttribute('data-sorot'); });
    }, 3200) };
  }
  /* Menekan tombol pembuka yang masih tertutup; true kalau ada yang dibuka. */
  function bukaLewat(tombol) {
    var tertutup = Array.prototype.slice.call(tombol).filter(function (b) {
      return b && b.getAttribute('aria-expanded') === 'false';
    });
    tertutup.forEach(function (b) { b.click(); });
    return tertutup.length > 0;
  }
  /* `buka` membuka wadahnya (boleh kosong), `cari` mengembalikan unsurnya
     sesudah tata letaknya mapan; yang pertama digulir, semuanya disorot. */
  function antarKe(buka, cari, jeda) {
    var dibuka = buka ? buka() : false, hentikan = function () {};
    var jam = setTimeout(function () {
      var unsur = Array.prototype.slice.call(cari() || []).filter(Boolean);
      if (!unsur.length) return;
      hentikan = gulirSeperlunya(unsur[0]);
      sorotUnsur(unsur);
    }, Math.max(jeda || 0, dibuka ? 280 : 30));
    return function () { clearTimeout(jam); hentikan(); };
  }

  /* ================= panel yang mengunci latarnya ================= */
  /* Padanan `tampak` dan `kunciPanel` di Prototipe/src/navigasi.js (22 Sep),
     dipakai laci dan panduan. Saudara di setiap tingkat diberi `inert`, jadi
     Tab, pencarian, dan klik tidak menyentuh halaman di belakang panel. Tab
     berputar di dalamnya, Esc menutupnya, halaman tidak ikut tergulir, dan
     fokus kembali ke pembukanya. */
  function tampak(el) {
    return !!el && el.isConnected && el.getClientRects().length > 0
      && getComputedStyle(el).visibility !== 'hidden' && !el.closest('[inert]');
  }
  function kunciPanel(panel, o) {
    var terkunci = [];
    function kunciLatar() {
      for (var cabang = panel; cabang.parentElement && cabang !== document.body; cabang = cabang.parentElement) {
        Array.prototype.forEach.call(cabang.parentElement.children, function (s) {
          if (s === cabang || s.matches('style, script, link') || (o.kecuali && s.matches(o.kecuali))) return;
          if (!terkunci.some(function (x) { return x[0] === s; })) terkunci.push([s, s.hasAttribute('inert')]);
          s.setAttribute('inert', '');
        });
      }
    }
    kunciLatar();
    /* Yang baru muncul ketika panel terbuka juga tidak boleh aktif. */
    var pengamat = new MutationObserver(kunciLatar);
    pengamat.observe(document.body, { childList: true, subtree: true });
    var overflow = document.documentElement.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    function daftar() {
      return $$('button, a[href], input, select, textarea, [tabindex]', panel).filter(function (el) {
        return !el.disabled && el.tabIndex >= 0 && tampak(el);
      });
    }
    function fokus() {
      var awal = o.fokusAwal && o.fokusAwal();
      (tampak(awal) ? awal : daftar()[0] || panel).focus();
    }
    var jam = setTimeout(fokus, 0);
    function keyboard(e) {
      if (e.defaultPrevented || e.isComposing) return;
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); o.tutup(); return; }
      if (e.key !== 'Tab') return;
      var items = daftar(), awal = items[0], akhir = items[items.length - 1];
      if (!awal) { e.preventDefault(); panel.focus(); return; }
      var aktif = document.activeElement;
      if (!panel.contains(aktif) || (e.shiftKey ? aktif === awal : aktif === akhir)) {
        e.preventDefault();
        (e.shiftKey ? akhir : awal).focus();
      }
    }
    function arahkan(e) { if (!panel.contains(e.target)) fokus(); }
    document.addEventListener('keydown', keyboard, true);
    document.addEventListener('focusin', arahkan);
    return function () {
      clearTimeout(jam);
      pengamat.disconnect();
      document.removeEventListener('keydown', keyboard, true);
      document.removeEventListener('focusin', arahkan);
      terkunci.forEach(function (x) { if (!x[1]) x[0].removeAttribute('inert'); });
      document.documentElement.style.overflow = overflow;
      var tujuan = o.kembali && o.kembali();
      if (tampak(tujuan)) tujuan.focus({ preventScroll: true });
    };
  }

  /* ================= laci navigasi di ponsel ================= */
  /* Selama terbuka laci menjadi dialog: latarnya dikunci, fokus masuk ke menu
     halaman ini lalu kembali ke tombol pembukanya. Laci yang tertinggal
     terbuka menutup sendiri begitu layarnya dilebarkan. Batasnya pasangan
     query yang sama dengan CSS-nya — zoom dapat menghasilkan 767,5 px. */
  var aturLaci = function () {};
  function pasangLaci() {
    var nav = $('.nav'), tirai = $('.tirai-nav'), pembuka = $('[data-buka-nav]');
    if (!nav) return;
    var desktop = window.matchMedia('(min-width:768px)');
    var lepas = null;
    aturLaci = function (buka) {
      buka = !!buka && !desktop.matches;
      if (buka === nav.classList.contains('buka')) return;
      nav.classList.toggle('buka', buka);
      if (tirai) tirai.classList.toggle('tampil', buka);
      document.documentElement.classList.toggle('laci-terbuka', buka);
      if (pembuka) pembuka.setAttribute('aria-expanded', buka ? 'true' : 'false');
      if (buka) {
        nav.setAttribute('role', 'dialog');
        nav.setAttribute('aria-modal', 'true');
        lepas = kunciPanel(nav, {
          tutup: function () { aturLaci(false); },
          fokusAwal: function () { return $('.menu[aria-current]', nav) || $('.menu', nav); },
          kembali: function () { return tampak(pembuka) ? pembuka : $('.menu[aria-current]', nav); },
          kecuali: '.tirai-nav',
        });
      } else {
        nav.removeAttribute('role');
        nav.removeAttribute('aria-modal');
        if (lepas) { var l = lepas; lepas = null; l(); }
      }
    };
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-buka-nav]')) aturLaci(true);
      else if (e.target.closest('[data-tutup-nav]')) aturLaci(false);
    });
    desktop.addEventListener('change', function () {
      aturLaci(false);
      /* Jangan meninggalkan fokus di menu yang baru saja disembunyikan. */
      if (!desktop.matches && nav.contains(document.activeElement) && pembuka) pembuka.focus();
    });
  }

  /* ================= lebar menu: otomatis, lebar, ringkas ================= */
  /* Tanpa pilihan, menunya rel di bawah 1121 px dan lebar di atasnya.
     Sejak 29 Sep pilihan tersimpan per akun di server. Tiga jalan: ikon panah di
     bagian atas sidebar, klik di area kosong menu, dan pintasan [ — sejajar dengan /
     untuk mencari. Di ponsel menunya laci, jadi tidak ada yang diciutkan. */
  var BUKAN_AREA_KOSONG = "button, a[href], input, select, textarea, label, summary, [role='button'], [contenteditable='true']";
  function bacaModeNav() {
    return document.body.dataset.modeMenu || 'otomatis';
  }
  function pasangCiut() {
    var nav = $('.nav'), kontrol = $('[data-kontrol-nav]'), tombol = $('[data-ciut-nav]');
    if (!nav || !kontrol || !tombol) return;
    var desktop = window.matchMedia('(min-width:768px)'), lebar = window.matchMedia('(min-width:1121px)');
    var mode = bacaModeNav();
    function ringkas() { return mode === 'ringkas' || (mode === 'otomatis' && !lebar.matches); }
    function terapkan() {
      var r = ringkas(), teks = r ? 'Lebarkan menu' : 'Ciutkan menu';
      document.body.classList.toggle('nav-ringkas', r);
      tombol.setAttribute('aria-expanded', r ? 'false' : 'true');
      tombol.setAttribute('aria-label', teks);
      tombol.title = teks + ' — atau klik area kosong menu, atau tekan [';
      $('[data-ciut-ikon="ciut"]', tombol).toggleAttribute('hidden', r);
      $('[data-ciut-ikon="lebar"]', tombol).toggleAttribute('hidden', !r);
    }
    var sibuk = false;
    function ganti() {
      if (sibuk) return;
      sibuk = true;
      tombol.disabled = true;
      simpanTampilan({menu: ringkas() ? 'lebar' : 'ringkas'}).finally(function () {
        sibuk = false; tombol.disabled = false;
      });
    }

    kontrol.hidden = false;
    tombol.addEventListener('click', ganti);

    /* Klik di area kosong menu — bukan pada yang punya tugasnya sendiri. Klik
       kedua dari klik ganda, klik bermodifier, dan klik yang mengakhiri
       pemilihan teks diabaikan. Lebar layar dibaca saat diklik. */
    nav.addEventListener('click', function (e) {
      var terpilih = window.getSelection ? String(window.getSelection()) : '';
      if (!desktop.matches || e.defaultPrevented || (e.button || 0) !== 0 || e.detail > 1
        || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey || terpilih) return;
      if (e.target.closest && e.target.closest(BUKAN_AREA_KOSONG)) return;
      ganti();
    });

    /* Pengulangan tombol, pengetikan, IME, modifier, dan jendela yang sedang
       terbuka tidak ikut membalik menunya. */
    document.addEventListener('keydown', function (e) {
      var t = e.target || {};
      if (e.key !== '[' || e.repeat || e.defaultPrevented || e.isComposing
        || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return;
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName || '') || t.isContentEditable) return;
      if (!desktop.matches || $$('.tirai, [aria-modal="true"]').some(tampak)) return;
      e.preventDefault();
      ganti();
    });
    lebar.addEventListener('change', terapkan);
    document.addEventListener('simtlhp:tampilan', function () { mode = bacaModeNav(); terapkan(); });
    terapkan();
  }

  /* ================= panduan singkat dari kaki menu ================= */
  /* Tanpa skrip panduannya terbuka lewat #panduan. Dengan skrip alamatnya
     tidak berubah: kelas `tampil` yang membukanya, dan penguncian yang sama
     dengan laci — Tab tetap di dalamnya, Esc dan bayangannya menutupnya, dan
     fokus kembali ke pembukanya atau, bila pembuka itu di dalam laci yang
     sudah tertutup, ke tombol pembuka laci. */
  function pasangPanduan() {
    var tirai = $('[data-panduan]');
    if (!tirai) return;
    var lembar = $('.lembar', tirai) || tirai, pembuka = null, lepas = null;
    function tutup() {
      if (!tirai.classList.contains('tampil')) return;
      tirai.classList.remove('tampil');
      if (lepas) { var l = lepas; lepas = null; l(); }
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-panduan]');
      if (b) {
        e.preventDefault();
        if (tirai.classList.contains('tampil')) return;
        pembuka = b.closest('[data-menu-akun]') ? $('[data-buka-akun]') : b;
        aturLaci(false);
        tirai.classList.add('tampil');
        lepas = kunciPanel(lembar, {
          tutup: tutup,
          fokusAwal: function () { return $('[data-tutup-panduan]', tirai); },
          kembali: function () { return tampak(pembuka) ? pembuka : $('[data-buka-nav]'); },
        });
        return;
      }
      if (tirai.classList.contains('tampil') && (e.target.closest('[data-tutup-panduan]') || e.target === tirai)) {
        e.preventDefault();
        tutup();
      }
    });
  }

  /* ================= jendela arsip rekomendasi ================= */
  /* Padanan `JendelaArsip` prototipe, dengan cara yang sama dengan panduan:
     terbuka tanpa mengubah alamat, latarnya dikunci, fokus ke Tutup, lalu
     kembali ke tombol pembukanya. Tanpa skrip jendelanya tetap terbuka lewat
     #arsip (CSS :target), dan Tutup menunjuk tombolnya kembali. */
  function pasangArsip() {
    var tirai = $('[data-arsip]');
    if (!tirai) return;
    var lembar = $('.lembar', tirai) || tirai, pembuka = null, lepas = null;
    function tutup() {
      if (!tirai.classList.contains('tampil')) return;
      tirai.classList.remove('tampil');
      if (lepas) { var l = lepas; lepas = null; l(); }
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-arsip]');
      if (b) {
        e.preventDefault();
        if (tirai.classList.contains('tampil')) return;
        pembuka = b;
        tirai.classList.add('tampil');
        lepas = kunciPanel(lembar, {
          tutup: tutup,
          fokusAwal: function () { return $('[data-tutup-arsip]', tirai); },
          kembali: function () { return pembuka; },
        });
        return;
      }
      if (tirai.classList.contains('tampil') && (e.target.closest('[data-tutup-arsip]') || e.target === tirai)) {
        e.preventDefault();
        tutup();
      }
    });
  }

  /* ================= pemberitahuan (26 Sep) ================= */
  /* Padanan PanelPemberitahuan, Pemberitahuan, Popup, dan tandaiBaca prototipe.
     - Lonceng membuka panel di bawahnya; isinya diambil dari server (yang
       sekaligus mencatat semuanya sudah dilihat — angka lonceng hilang).
       Menekan di luar panel atau Esc menutupnya, fokus kembali ke lonceng.
     - Tab "Belum dibaca" · "Semua" berganti di tempat; isi "Belum dibaca"
       dibekukan saat tabnya dipilih.
     - Tombol di ujung tiap baris menandai dibaca/belum dibaca di tempat.
     - Kotak popup menutup sendiri sesudah 8 detik; jamnya berhenti selama
       ditunjuk atau difokus, dan sisanya dibawa ke halaman berikutnya.
       "Lihat" membuka panel.
     - Tiap menit, selama tabnya terlihat, angka lonceng diperbarui dan pemberitahuan
       baru yang belum pernah dimunculkan sebagai pop-up ditampilkan (PemberitahuanController@ringkas). */
  function pasangPemberitahuan() {
    var lonceng = $('[data-buka-pemberitahuan]');
    var panel = $('[data-panel-pemberitahuan]');
    var halaman = $('[data-halaman-pemberitahuan]');
    var token = ($('meta[name="csrf-token"]') || {}).content;
    var terbuka = false;

    function kirim(url, data) {
      return fetch(url, { method: 'POST', credentials: 'same-origin', body: data || null,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
    }
    /* Angka lonceng. Lencana yang angkanya berubah dibuat ulang, supaya
       geraknya (membal) berputar lagi — sama dengan `key` di prototipe. */
    function aturLencana(baru, belum) {
      if (!lonceng) return;
      var n = $('.n', lonceng), teks = baru > 99 ? '99+' : String(baru);
      if (baru > 0) {
        if (!n || n.textContent !== teks) {
          if (n) n.remove();
          n = document.createElement('span');
          n.className = 'n';
          lonceng.appendChild(n);
        }
        n.textContent = teks;
      } else if (n) n.remove();
      lonceng.setAttribute('aria-label', 'Pemberitahuan, ' + baru + ' baru, ' + belum + ' belum dibaca');
    }
    function aturBelum(belum) {
      $$('[data-jml-belum]').forEach(function (x) { x.textContent = belum; });
      $$('[data-pemberitahuan-semua], [data-pemberitahuan-semua-f]').forEach(function (x) { x.hidden = !belum; });
    }
    function aturButir(li, dibaca) {
      li.classList.toggle('belum', !dibaca);
      var f = $('[data-tandai-pemberitahuan]', li);
      if (!f) return;
      $('input[name="baca"]', f).value = dibaca ? '0' : '1';
      var b = $('button', f), kata = dibaca ? 'Tandai belum dibaca' : 'Tandai dibaca';
      b.title = kata;
      b.setAttribute('aria-label', kata);
      b.innerHTML = ikon(dibaca ? 'titik' : 'cek', 15);
      var a = $('.kb-buka', li);
      if (a) a.setAttribute('aria-label', (dibaca ? '' : 'Belum dibaca. ') + a.getAttribute('aria-label').replace(/^Belum dibaca\. /, ''));
    }

    /* Tab: yang tampil ditentukan saat tabnya dipilih. */
    function terapkanTab(wadah, tab) {
      var isi = wadah && $('[data-isi-pemberitahuan]', wadah);
      if (!isi) return;
      isi.dataset.tab = tab;
      $$('[data-tab-pemberitahuan]', wadah).forEach(function (t) {
        t.setAttribute('aria-selected', t.dataset.tabPemberitahuan === tab ? 'true' : 'false');
      });
      var butir = $$('[data-pemberitahuan]', isi);
      butir.forEach(function (li) {
        li.hidden = !(tab === 'belum' ? li.classList.contains('belum') : li.hasAttribute('data-semua'));
      });
      $$('.kb-hari', isi).forEach(function (h) {
        h.hidden = !$$('[data-pemberitahuan]', h).some(function (li) { return !li.hidden; });
      });
      var ada = butir.some(function (li) { return !li.hidden; });
      $$('[data-kosong-pemberitahuan]', isi).forEach(function (k) { k.hidden = ada || k.dataset.kosongPemberitahuan !== tab; });
    }

    function semuaDibaca(url) {
      kirim(url).then(function (r) { return r.json(); }).then(function (d) {
        $$('[data-pemberitahuan]').forEach(function (li) { aturButir(li, true); });
        aturBelum(d.belum);
        if (!terbuka) aturLencana(d.baru, d.belum);
      }).catch(function () { location.reload(); });
    }

    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-tab-pemberitahuan]');
      if (t) { e.preventDefault(); terapkanTab(t.closest('.kb-panel, [data-halaman-pemberitahuan]'), t.dataset.tabPemberitahuan); return; }
      var semua = e.target.closest('[data-pemberitahuan-semua]');
      if (semua) { e.preventDefault(); semuaDibaca(semua.dataset.url); }
    });
    document.addEventListener('submit', function (e) {
      var f = e.target.closest('[data-tandai-pemberitahuan]');
      if (f) {
        e.preventDefault();
        var li = f.closest('[data-pemberitahuan]'), baca = $('input[name="baca"]', f).value === '1';
        kirim(f.action, new FormData(f)).then(function (r) { return r.json(); }).then(function (d) {
          aturButir(li, baca);
          aturBelum(d.belum);
          if (!terbuka) aturLencana(d.baru, d.belum);
        }).catch(function () { f.submit(); });
        return;
      }
      var fs = e.target.closest('[data-pemberitahuan-semua-f]');
      if (fs) { e.preventDefault(); semuaDibaca(fs.action); }
    });

    /* ---- panel lonceng ---- */
    function tutupPanel(fokus) {
      if (!terbuka) return;
      terbuka = false;
      panel.hidden = true;
      lonceng.setAttribute('aria-expanded', 'false');
      if (fokus) lonceng.focus();
    }
    function bukaPanel() {
      if (!panel || !lonceng || terbuka) return;
      terbuka = true;
      tutupPopup();
      lonceng.setAttribute('aria-expanded', 'true');
      panel.innerHTML = '<div class="kb-kosong"><span>Memuat pemberitahuan…</span></div>';
      panel.hidden = false;
      kirim(lonceng.dataset.panel).then(function (r) {
        if (!r.ok) throw new Error(r.status);
        return r.text();
      }).then(function (h) {
        if (!terbuka) return;
        panel.innerHTML = h;
        var jml = $('[data-jml-belum]', panel);
        aturLencana(0, jml ? +jml.textContent : 0);
      }).catch(function () { location.href = lonceng.href; });
    }
    if (lonceng && panel) {
      lonceng.addEventListener('click', function (e) {
        e.preventDefault();
        if (terbuka) tutupPanel(false); else bukaPanel();
      });
      document.addEventListener('mousedown', function (e) {
        if (terbuka && !panel.contains(e.target) && !lonceng.contains(e.target)) tutupPanel(false);
      });
    }

    /* ---- kotak popup ---- */
    /* Pindah halaman di sini memuat ulang seluruh halaman, sedangkan di
       prototipe kotaknya tetap berdiri dan jamnya berjalan terus (27 Sep —
       Hizkia: hitung mundurnya "selalu ke reset saat berpindah page").
       Supaya sama, kotak yang sedang tampil dititipkan ke sessionStorage
       bersama sisa waktunya; halaman berikutnya memasangnya lagi tanpa gerak
       masuk dan tanpa diumumkan ulang, garis sisa waktunya melanjutkan dari
       tempatnya. Yang sudah selesai — habis waktunya, ×, Esc, Buka, Lihat,
       halaman Pemberitahuan — dibuang dari titipan; server sendiri tidak
       pernah memunculkan pemberitahuan yang sama dua kali dalam satu sesi. */
    var LAMA_POPUP = 8000, KUNCI_POPUP = 'simtlhp.popup';
    function titipPopup(isi) {
      try {
        if (isi) sessionStorage.setItem(KUNCI_POPUP, JSON.stringify(isi));
        else sessionStorage.removeItem(KUNCI_POPUP);
      } catch (e) { /* penyimpanan ditolak: kotaknya cukup hidup di halaman ini */ }
    }
    function ambilTitipanPopup() {
      try { return JSON.parse(sessionStorage.getItem(KUNCI_POPUP) || 'null'); } catch (e) { return null; }
    }
    function pasangSatuPopup(kotak, sisaAwal) {
      var html = kotak.outerHTML, sisa = sisaAwal || LAMA_POPUP, mulai = Date.now(), tertahan = false;
      var jam = setTimeout(tutup, sisa);
      function catat() { titipPopup({ html: html, sampai: mulai + sisa, sisa: sisa, tertahan: tertahan }); }
      function lepas() { clearTimeout(jam); if (kotak.parentNode) kotak.parentNode.removeChild(kotak); }
      function tutup() { lepas(); titipPopup(null); }
      /* Ditunjuk lalu diklik = dua kali tahan; yang kedua tidak boleh
         memotong sisanya lagi. */
      function tahan() {
        if (tertahan) return;
        tertahan = true;
        clearTimeout(jam);
        sisa -= Date.now() - mulai;
        catat();
      }
      function lanjut() {
        if (!tertahan || kotak.matches(':hover') || kotak.contains(document.activeElement)) return;
        tertahan = false;
        mulai = Date.now();
        sisa = Math.max(1200, sisa);
        jam = setTimeout(tutup, sisa);
        catat();
      }
      kotak.addEventListener('mouseenter', tahan);
      kotak.addEventListener('focusin', tahan);
      kotak.addEventListener('mouseleave', lanjut);
      kotak.addEventListener('focusout', lanjut);
      $('[data-tutup-popup]', kotak).addEventListener('click', tutup);
      var lihat = $('[data-lihat-pemberitahuan]', kotak);
      if (lihat && panel) lihat.addEventListener('click', function (e) { e.preventDefault(); bukaPanel(); });
      var buka = $('.kb-aksi a:not([data-lihat-pemberitahuan])', kotak);
      if (buka) buka.addEventListener('click', function () { titipPopup(null); });
      kotak.tutupPopup = tutup;
      kotak.lepasPopup = lepas;
      catat();
    }
    function tutupPopup() {
      var k = $('[data-popup]');
      if (k && k.tutupPopup) k.tutupPopup(); else if (k) k.remove();
      titipPopup(null);
    }
    function pulihkanPopup() {
      var t = ambilTitipanPopup();
      if (!t) return;
      var sisa = t.tertahan ? t.sisa : t.sampai - Date.now();
      if (halaman || !(sisa > 400)) { titipPopup(null); return; }
      var w = document.createElement('div');
      w.innerHTML = t.html;
      var kotak = w.firstElementChild;
      if (!kotak || !kotak.hasAttribute('data-popup')) { titipPopup(null); return; }
      kotak.style.animation = 'none';
      kotak.setAttribute('aria-live', 'off');
      var garis = $('.kb-sisa', kotak);
      if (garis) garis.style.animationDelay = -Math.round(LAMA_POPUP - Math.min(sisa, LAMA_POPUP)) + 'ms';
      document.body.appendChild(kotak);
      pasangSatuPopup(kotak, sisa);
    }
    /* Kotak dari server = pemberitahuan baru yang belum pernah dimunculkan sebagai pop-up; ia
       menggantikan titipan halaman sebelumnya. */
    if ($('[data-popup]')) pasangSatuPopup($('[data-popup]'));
    else pulihkanPopup();
    /* Kembali lewat tombol Back bisa memulihkan halaman lama beserta
       kotaknya yang sempat membeku — samakan dengan titipannya. */
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted) return;
      var k = $('[data-popup]');
      if (k && k.lepasPopup) k.lepasPopup(); else if (k) k.remove();
      pulihkanPopup();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (terbuka) tutupPanel(true); else tutupPopup();
    });

    /* ---- pembaruan berkala ---- */
    if (lonceng && lonceng.dataset.ringkas) {
      setInterval(function () {
        if (document.visibilityState !== 'visible') return;
        fetch(lonceng.dataset.ringkas, { credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            if (!d) return;
            if (!terbuka) aturLencana(d.baru, d.belum);
            if (d.popup && !terbuka && !halaman && !$('[data-popup]')) {
              var w = document.createElement('div');
              w.innerHTML = d.popup;
              var kotak = w.firstElementChild;
              document.body.appendChild(kotak);
              pasangSatuPopup(kotak);
            }
          }).catch(function () { /* dicoba lagi menit berikutnya */ });
      }, 60000);
    }
  }

  /* ================= blok yang dilipat ================= */
  /* Kepala blok (mis. "Sudah dibaca") membuka dan menutup isinya sendiri. */
  function pasangBlokLipat() {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-blok]');
      if (!b) return;
      var isi = document.getElementById(b.getAttribute('aria-controls'));
      if (!isi) return;
      var buka = isi.hidden;
      isi.hidden = !buka;
      b.setAttribute('aria-expanded', buka ? 'true' : 'false');
      var panah = $('.panah', b);
      if (panah) panah.classList.toggle('buka', buka);
    });
  }

  /* ================= kartu berlipat di rincian rekomendasi ================= */
  /* Padanan komponen `Lipat`. Digambar terbuka supaya tanpa skrip isinya tetap
     terbaca; di sini seluruhnya dilipat saat halaman siap. Kepalanya menyebut
     isinya secara ringkas, jadi menutup tidak berarti buta. */
  function aturLipat(w, buka) {
    if (!w) return;
    w.classList.toggle('buka', buka);
    var kep = $('[data-lipat-kep]', w), isi = $('[data-lipat-isi]', w);
    if (isi) isi.hidden = !buka;
    if (kep) kep.setAttribute('aria-expanded', buka ? 'true' : 'false');
    var ringkas = $('[data-lipat-ringkas]', w);
    if (ringkas) ringkas.hidden = buka;
    var ajak = $('[data-lipat-ajak]', w);
    if (ajak) ajak.textContent = buka ? 'Sembunyikan' : 'Lihat selengkapnya';
  }

  /* Kepala rekomendasi: judulnya tetap, keterangannya yang dibaca selengkapnya. */
  function aturBaca(w, buka) {
    if (!w) return;
    $('[data-baca-rinci]', w).hidden = !buka;
    $('[data-baca-ringkas]', w).hidden = buka;
    var alih = $('[data-baca-alih]', w);
    alih.setAttribute('aria-expanded', buka ? 'true' : 'false');
    $('[data-baca-teks]', alih).textContent = buka ? 'Sembunyikan rincian' : 'Lihat rincian rekomendasi';
  }

  function pasangLipat() {
    $$('[data-lipat]').forEach(function (w) { aturLipat(w, false); });
    $$('[data-baca]').forEach(function (w) { aturBaca(w, false); });
    document.addEventListener('click', function (e) {
      var alih = e.target.closest('[data-baca-alih]');
      if (alih) { aturBaca(alih.closest('[data-baca]'), alih.getAttribute('aria-expanded') !== 'true'); return; }
      var kep = e.target.closest('[data-lipat-kep]');
      /* Menekan ikon Info membuka keterangannya, bukan melipat kartunya. */
      if (!kep || e.target.closest('.info')) return;
      var w = kep.closest('[data-lipat]');
      aturLipat(w, !w.classList.contains('buka'));
    });
    document.addEventListener('keydown', function (e) {
      if (!e.target.matches || !e.target.matches('[data-lipat-kep]')) return;
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.target.click(); }
    });
  }

  /* ================= baris tabel yang membuka halaman ================= */
  function pasangBarisLink() {
    document.addEventListener('click', function (e) {
      var tr = e.target.closest('tr[data-href]');
      if (!tr || e.target.closest('a, button, input, select, textarea, label, .info')) return;
      if (e.ctrlKey || e.metaKey) { window.open(tr.dataset.href, '_blank'); return; }
      location.href = tr.dataset.href;
    });
  }

  /* ================= filter ================= */
  /* Pilihan terkirim begitu diganti. Kotak cari memfilter baris yang sudah ada
     sambil diketik — seperti prototipe — dan Enter mengirimnya ke server. */
  function pasangFilter() {
    document.addEventListener('change', function (e) {
      var f = e.target.form;
      if (!e.target.matches('[data-kirim]') || !f) return;
      /* Catat laporan baru mengirim pilihannya sendiri tanpa memuat ulang
         halaman (pasangFormBaru, 29 Sep). Daftar yang ditukar di tempat
         dikirim lewat kiriman biasa, supaya ditangkap pasangDaftarDiTempat. */
      if (f.hasAttribute('data-form-baru')) return;
      if (f.hasAttribute('data-ganti-di-tempat') && f.requestSubmit) f.requestSubmit();
      else f.submit();
    });
    /* Didengar di dokumen, bukan di kotaknya: isi daftar bisa ditukar di
       tempat, dan kotak yang baru tetap bekerja. */
    document.addEventListener('input', function (e) {
      if (!e.target.matches('[data-filter-langsung]')) return;
      var tabel = document.querySelector('.tw table');
      if (!tabel) return;
      var kata = e.target.value.trim().toLowerCase();
      var n = 0, perlu = 0;
      $$('tbody tr[data-cari]', tabel).forEach(function (tr) {
        var cocok = !kata || tr.dataset.cari.indexOf(kata) >= 0;
        tr.hidden = !cocok;
        if (cocok) {
          n++;
          /* Baris daftar laporan membawa jumlah rekomendasinya yang perlu
             perhatian, baris daftar rekomendasi 0 atau 1 — dijumlahkan,
             sama dengan hitungan server. */
          perlu += parseInt(tr.dataset.perlu, 10) || 0;
          var no = $('[data-no]', tr);
          if (no) no.textContent = n;
        }
      });
      var hint = $('[data-hitung-tampil]');
      if (hint) {
        $('[data-n-tampil]', hint).textContent = n;
        var b = $('[data-perlu]', hint);
        if (b) { b.hidden = !perlu; $('[data-n-perlu]', b).textContent = perlu; }
      }
      var kosong = $('tr[data-kosong]', tabel);
      if (kosong) kosong.hidden = n > 0;
    });
  }

  /* ================= daftar yang ditukar di tempat (29 Sep) ================= */
  /* Hizkia: "mengapa dalam beberapa hal saat pindah pindah pilihan menu atau
     opsi … web selalu melakukan proses loading atau seperti refresh gitu".
     Kepala daftar Rekomendasi dan Daftar laporan tetap formulir GET biasa —
     alamatnya bisa disimpan dan dibagi, dan tetap bekerja tanpa skrip —
     tapi dengan skrip jawabannya ditukar di tempat, seperti dasbor: hanya
     isi halamannya yang berganti, layar tidak bergeser, fokus tetap di
     pilihan yang ditekan, dan tombol Kembali browser kembali ke pilihan
     sebelumnya. */
  function pasangDaftarDiTempat() {
    if (!$('form[data-ganti-di-tempat]') || !window.fetch || !window.DOMParser) return;
    var nomor = 0, pengendali = null, tampil = location.pathname + location.search;

    function alamatDari(form, t) {
      var q = new URLSearchParams();
      new FormData(form).forEach(function (v, k) { q.append(k, v); });
      /* Tombol yang ditekan menimpa isian tersembunyi yang senama, sama
         dengan kiriman biasa yang nilai terakhirnya menang. */
      if (t && t.name) q.set(t.name, t.value);
      var s = q.toString();
      return form.action + (s ? '?' + s : '');
    }
    /* Pilihan yang difokus dikenali dari nama dan urutannya — nilainya bisa
       berganti (keping yang menyala menjadi "lepaskan"). */
    function kunciFokus(wadah) {
      var el = document.activeElement;
      if (!el || !el.name || !wadah.contains(el)) return null;
      var k = { nama: el.name, ke: $$('[name]', wadah).filter(function (x) { return x.name === el.name; }).indexOf(el) };
      try {
        if (typeof el.selectionStart === 'number') { k.awal = el.selectionStart; k.akhir = el.selectionEnd; }
      } catch (e) { /* bukan isian teks */ }
      return k;
    }
    function pulihkanFokus(wadah, k) {
      if (!k) return;
      var sama = $$('[name]', wadah).filter(function (x) { return x.name === k.nama; });
      var el = sama[Math.min(k.ke, sama.length - 1)];
      if (!el || !tampak(el)) return;
      el.focus({ preventScroll: true });
      if (k.awal != null && el.setSelectionRange) {
        try { el.setSelectionRange(k.awal, k.akhir); } catch (e) { /* bukan isian teks */ }
      }
    }
    /* Angka di menu samping ikut pilihan jenis laporan (LHP/LHA). */
    function samakanMenu(dok) {
      var baru = $$('#nav-utama a.menu', dok);
      $$('#nav-utama a.menu').forEach(function (a) {
        var b = baru.filter(function (x) { return x.getAttribute('href') === a.getAttribute('href'); })[0];
        if (!b) return;
        a.className = b.className;
        ['title', 'aria-label', 'aria-current'].forEach(function (n) {
          if (b.hasAttribute(n)) a.setAttribute(n, b.getAttribute(n)); else a.removeAttribute(n);
        });
        a.innerHTML = b.innerHTML;
      });
    }

    function muat(url, dorong) {
      var form = $('form[data-ganti-di-tempat]'), wadah = form && form.closest('.body');
      if (!wadah) { location.href = url; return; }
      var ini = ++nomor;
      /* Pilihan yang ditekan beruntun: yang terakhir yang dipakai. */
      if (pengendali) pengendali.abort();
      pengendali = window.AbortController ? new AbortController() : null;
      form.setAttribute('aria-busy', 'true');
      fetch(url, { credentials: 'same-origin', signal: pengendali ? pengendali.signal : undefined,
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } })
        .then(function (r) {
          /* Yang bukan daftar ini — sesi berakhir, server bermasalah — dibuka
             sebagai halaman biasa, seperti tanpa skrip. */
          if (!r.ok || new URL(r.url, location.href).pathname !== new URL(url, location.href).pathname) return { pindah: r.url || url };
          return r.text().then(function (teks) { return { url: r.url, teks: teks }; });
        })
        .then(function (h) {
          if (ini !== nomor) return;
          if (h.pindah) { location.href = h.pindah; return; }
          var dok = new DOMParser().parseFromString(h.teks, 'text/html');
          var baru = dok.querySelector('form[data-ganti-di-tempat]'), wadahBaru = baru && baru.closest('.body');
          if (!wadahBaru) { location.href = url; return; }
          var fokus = kunciFokus(wadah), y = window.scrollY;
          wadah.innerHTML = wadahBaru.innerHTML;
          $$('[data-tanpa-js]', wadah).forEach(function (x) { x.hidden = true; });
          samakanMenu(dok);
          if (dok.title) document.title = dok.title;
          if (dorong && h.url !== location.href) history.pushState({ daftar: 1 }, '', h.url);
          else history.replaceState({ daftar: 1 }, '', h.url);
          tampil = location.pathname + location.search;
          window.scrollTo(0, y);
          pulihkanFokus(wadah, fokus);
        })
        .catch(function (e) {
          if (e && e.name === 'AbortError') return;
          location.href = url;
        })
        .then(function () {
          var f = ini === nomor && $('form[data-ganti-di-tempat]');
          if (f) f.removeAttribute('aria-busy');
        });
    }

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.matches || !form.matches('form[data-ganti-di-tempat]')) return;
      e.preventDefault();
      muat(alamatDari(form, e.submitter), true);
    });
    window.addEventListener('popstate', function () {
      var form = $('form[data-ganti-di-tempat]');
      if (!form || location.pathname !== new URL(form.action, location.href).pathname) return;
      /* Yang berganti cuma jangkarnya (jendela Panduan, Arsip): daftarnya tetap. */
      if (location.pathname + location.search === tampil) return;
      muat(location.href, false);
    });
  }

  /* ================= pencarian di batang atas ================= */
  function pasangCari() {
    var wadah = $('[data-cari-glob]');
    if (!wadah) return;
    var kotak = $('input', wadah), panel = $('.panel', wadah);
    var bersih = $('.bersih', wadah), kbd = $('kbd', wadah);
    var hasil = [], pilih = 0, jeda = null, nomor = 0;

    function tutup(kosongkan) {
      panel.hidden = true;
      if (kosongkan) { kotak.value = ''; kotak.blur(); segarkanTombol(); }
    }
    function segarkanTombol() { bersih.hidden = !kotak.value; kbd.hidden = !!kotak.value; }
    function gambar(data) {
      var kata = kotak.value.trim();
      hasil = [];
      if (kata.length < 2) { panel.hidden = true; return; }
      var h = '';
      if (!data.rek.length && !data.lap.length) {
        h = '<div class="kosong">Tidak ada yang cocok dengan "' + esc(kata) + '".</div>';
      } else {
        if (data.rek.length) h += '<div class="kel">Rekomendasi</div>';
        data.rek.forEach(function (x) {
          h += '<a class="item" href="' + esc(x.link) + '" data-i="' + hasil.length + '">' +
            '<span class="atas"><span class="mono kd">' + esc(x.kode) + '</span>' + x.cap + '</span>' +
            '<span class="jdl">' + esc(x.uraian) + '</span>' +
            '<span class="bwh">' + esc(x.satker) + ' &middot; Temuan ' + esc(x.nomorTemuan) + '</span></a>';
          hasil.push(x.link);
        });
        if (data.lap.length) h += '<div class="kel">Laporan</div>';
        data.lap.forEach(function (l) {
          h += '<a class="item" href="' + esc(l.link) + '" data-i="' + hasil.length + '">' +
            '<span class="atas"><span class="mono kd">' + esc(l.nomor) + '</span>' + l.sumber + '</span>' +
            '<span class="jdl">' + l.satker + '</span>' +
            '<span class="bwh">' + l.temuan + ' temuan &middot; diterima ' + esc(l.diterima) + '</span></a>';
          hasil.push(l.link);
        });
        if (data.jumlah > hasil.length) {
          h += '<div class="kosong">' + (data.jumlah - hasil.length) + ' hasil lain tidak ditampilkan. Persempit kata pencarinya.</div>';
        }
      }
      panel.innerHTML = h;
      pilih = 0;
      tandai();
      panel.hidden = false;
    }
    function tandai() {
      $$('.item', panel).forEach(function (a, i) { a.classList.toggle('pilih', i === pilih); });
    }
    function muat() {
      var kata = kotak.value.trim();
      segarkanTombol();
      if (kata.length < 2) { panel.hidden = true; return; }
      var ke = ++nomor;
      fetch(wadah.dataset.sumber + '?q=' + encodeURIComponent(kata), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (ke === nomor) gambar(d); });
    }
    kotak.addEventListener('input', function () { clearTimeout(jeda); jeda = setTimeout(muat, 150); });
    kotak.addEventListener('focus', function () { if (kotak.value.trim().length >= 2) muat(); });
    kotak.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { tutup(true); return; }
      if (!hasil.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); pilih = (pilih + 1) % hasil.length; tandai(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); pilih = (pilih - 1 + hasil.length) % hasil.length; tandai(); }
      if (e.key === 'Enter') { e.preventDefault(); location.href = hasil[pilih]; }
    });
    panel.addEventListener('mousemove', function (e) {
      var a = e.target.closest('.item');
      if (a) { pilih = parseInt(a.dataset.i, 10); tandai(); }
    });
    bersih.addEventListener('click', function () { tutup(true); });
    document.addEventListener('mousedown', function (e) { if (!wadah.contains(e.target)) tutup(false); });
    /* Pintasan papan tik: "/" di luar isian, atau Ctrl+K. */
    document.addEventListener('keydown', function (e) {
      var diKetikan = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName || '') || e.target.isContentEditable;
      if ((e.key === '/' && !diKetikan) || ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey))) {
        e.preventDefault();
        kotak.focus();
      }
    });
    segarkanTombol();
  }

  /* ================= preview berkas ================= */
  function pasangPreview() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-preview]');
      if (!a || e.ctrlKey || e.metaKey) return;
      e.preventDefault();
      e.stopPropagation();
      var b = JSON.parse(a.dataset.preview);
      var ext = ((b.nama || '').split('.').pop() || '').toLowerCase();
      var gambarIni = ['jpg', 'jpeg', 'png'].indexOf(ext) >= 0;
      var lembarKerja = ['xlsx', 'xls', 'csv'].indexOf(ext) >= 0;
      var garis = lembarKerja
        ? '<div class="garis p8"></div><div class="garis"></div><div class="garis p6"></div><div class="garis"></div><div class="garis p8"></div><div class="garis p4"></div>'
        : '<div class="garis p8"></div><div class="garis"></div><div class="garis p6"></div><div class="kotak"></div><div class="garis"></div><div class="garis p8"></div><div class="garis p4"></div>';
      var tirai = document.createElement('div');
      tirai.className = 'tirai';
      tirai.setAttribute('role', 'dialog');
      tirai.setAttribute('aria-modal', 'true');
      tirai.innerHTML =
        '<div class="lembar">' +
          '<div class="kep">' +
            '<div style="min-width:0;flex:1"><div class="mono" style="font-size:12.5px;word-break:break-all">' + esc(b.nama) + '</div>' +
            '<div class="lbl" style="margin-top:2px">' + esc(b.jenis || '') + (b.oleh ? ' · diunggah ' + esc(b.oleh) : '') + (b.tanggal ? ' · ' + esc(b.tanggal) : '') + '</div></div>' +
            '<button class="btn btn-s" type="button" data-tutup>Tutup</button>' +
          '</div>' +
          '<div class="bdn"><div class="halaman"><div class="cap-air"><span>CONTOH<br>BUKAN DOKUMEN ASLI</span></div>' +
            '<div class="lbl" style="margin-bottom:14px">' + (gambarIni ? 'Pindaian' : lembarKerja ? 'Lembar kerja' : 'Dokumen') + ' · ' + esc(ext.toUpperCase()) + '</div>' +
            '<div style="font-size:13px;font-weight:600;margin-bottom:16px">' + esc(b.jenis || '') + '</div>' + garis +
          '</div><div class="lbl" style="text-align:center;margin-top:14px;line-height:1.6">halaman 1 dari 1</div></div>' +
          '<div class="kak"><div class="lbl" style="flex:1;min-width:120px;display:flex;align-items:center;gap:7px;flex-wrap:wrap">Preview berkas' +
            (b.link ? '<span class="mono" style="font-size:11px;color:var(--ink-3);word-break:break-all">' + esc(b.link) + '</span>' : '') + '</div>' +
            /* Hanya alamat web yang dijadikan link (27 Sep) — padanan
               LinkAman::sah(); `javascript:` dan sejenisnya tidak pernah
               masuk href. */
            (b.link && linkSah(b.link) ? '<a class="btn btn-s" href="' + esc(b.link) + '" target="_blank" rel="noopener noreferrer">Buka link</a>'
                      : b.link ? '<span class="btn btn-s" aria-disabled="true" title="Link ini bukan alamat web">Buka link</span>'
                      : '<a class="btn btn-s" href="' + esc(a.getAttribute('href')) + '">Buka berkas</a>') +
          '</div>' +
        '</div>';
      function tutup() { if (tirai.parentNode) tirai.parentNode.removeChild(tirai); document.removeEventListener('keydown', escTutup); }
      function escTutup(ev) { if (ev.key === 'Escape') tutup(); }
      tirai.addEventListener('click', function (ev) {
        if (ev.target === tirai || ev.target.closest('[data-tutup]')) tutup();
      });
      document.addEventListener('keydown', escTutup);
      document.body.appendChild(tirai);
    });
  }

  /* ================= penegasan sebelum tindakan yang tidak bisa ditarik ================= */
  /* Tombol kirim bertanda data-pastikan menampilkan lembar tanya lebih dulu.
     Tanpa skrip formulirnya langsung terkirim — penjaganya tetap di server. */
  function pasangPastikan() {
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('[data-pastikan]');
      if (!tombol || tombol.dataset.sudah === '1') return;
      var form = tombol.form || tombol.closest('form');
      if (!form) return;
      if (!form.reportValidity()) return;
      e.preventDefault();
      var isi = JSON.parse(tombol.dataset.pastikan);
      var nada = isi.nada || 'biru';
      var kelas = nada === 'merah' ? 'merah' : nada === 'hijau' ? 'hijau' : nada === 'kuning' ? 'kuning' : '';
      var tirai = document.createElement('div');
      /* Selapis di atas jendela lain: mengganti penanggung jawab ditanyakan dari
         dalam jendela pemilih IRM, dan penegasan yang tertimbun tidak bisa
         dijawab. */
      tirai.className = 'tirai tirai-tanya';
      tirai.innerHTML =
        '<div class="lembar tanya" role="dialog" aria-modal="true">' +
          '<div class="kep"><span class="ic-kotak besar ' + kelas + '">' + ikon('awas', 20) + '</span><b>' + esc(isi.judul) + '</b></div>' +
          '<div class="bdn"><p class="apa">' + esc(isi.ket) + '</p>' + (isi.rincian || '') +
            (isi.balik === false ? '' : '<div class="pesan warn" style="margin-top:16px;margin-bottom:0"><span>Setelah ditekan, tindakan ini tidak bisa ditarik sendiri.</span></div>') +
          '</div>' +
          '<div class="kak">' +
            '<button type="button" class="btn ' + (nada === 'hijau' ? 'btn-ok' : 'btn-p') + '" data-ya>' + ikon('cek', 15) + ' ' + esc(isi.tombol || 'Ya, lanjutkan') + '</button>' +
            '<button type="button" class="btn" data-batal>' + ikon('silang', 15) + ' Batal, periksa lagi</button>' +
          '</div>' +
        '</div>';
      function tutup() { if (tirai.parentNode) tirai.parentNode.removeChild(tirai); }
      tirai.addEventListener('click', function (ev) {
        if (ev.target === tirai || ev.target.closest('[data-batal]')) tutup();
        if (ev.target.closest('[data-ya]')) {
          tutup();
          tombol.dataset.sudah = '1';
          if (form.requestSubmit) form.requestSubmit(tombol); else tombol.click();
        }
      });
      document.body.appendChild(tirai);
    });
  }

  /* ================= pemilih penanggung jawab (Data master) ================= */
  /* Jendelanya digambar server (`?pj=`), dan pencariannya formulir biasa.
     Di sini cuma ditambahkan: mencari sambil mengetik, menutup dengan Escape
     atau dengan menekan latarnya, dan kursor langsung di kotak cari. */
  function pasangPilihPj() {
    var tirai = $('[data-tirai-pj]');
    if (!tirai) return;
    function tutup() { location.href = tirai.dataset.tutup; }
    tirai.addEventListener('click', function (e) { if (e.target === tirai) tutup(); });
    document.addEventListener('keydown', function (e) {
      /* Escape milik kotak penegasan dulu kalau sedang terbuka. */
      if (e.key === 'Escape' && !$('.tirai-tanya')) tutup();
    });

    var form = $('[data-cari-irm]', tirai);
    var kotak = $('input[name=q]', form);
    var hasil = $('[data-hasil-irm]', tirai);
    kotak.focus();
    kotak.setSelectionRange(kotak.value.length, kotak.value.length);

    var jeda, urut = 0;
    kotak.addEventListener('input', function () {
      clearTimeout(jeda);
      jeda = setTimeout(function () {
        var nomor = ++urut;
        fetch(form.dataset.sumber + '?q=' + encodeURIComponent(kotak.value), {
          credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
          .then(function (t) {
            /* Jawaban yang datang terlambat tidak menimpa pencarian yang lebih baru. */
            if (nomor !== urut) return;
            hasil.innerHTML = t;
            var pj = $('input[name=pj]', form).value;
            history.replaceState(null, '', form.action + '?pj=' + encodeURIComponent(pj)
              + (kotak.value.trim() ? '&q=' + encodeURIComponent(kotak.value.trim()) : ''));
          })
          .catch(function () { /* formulirnya tetap bisa dikirim dengan Enter */ });
      }, 180);
    });
  }

  /* ================= Ringkasan (dasbor) ================= */
  /* Padanan perilaku DasborUji.jsx. Halamannya satu formulir GET: tiap tombol
     mengirim perbuatannya bersama keadaan sekarang, server mengalihkan ke
     alamat bersihnya. Yang dikerjakan di sini cuma kenyamanan — tanpa berkas
     ini seluruh tombolnya tetap bekerja sebagai kiriman formulir biasa:

       - isi halaman diganti di tempat (tidak melompat ke atas), alamatnya
         ikut berganti, dan tombol Kembali browser mengembalikannya;
       - keping filter yang tidak muat disimpan di menu "+N lainnya";
       - menu melayang (daftar pilihan, Tambah filter, daftar pintasan);
       - keterangan melayang di atas batang, dan garis per bulan yang bisa
         dibaca bulan demi bulan dengan tetikus maupun panah;
       - bar hasil yang memberi bayangan saat menempel. */
  function pasangDasbor() {
    var form = $('form[data-dasbor]');
    if (!form) return;
    var JARAK = 6;
    var kotak = null;            // keterangan melayang
    var menu = null;             // { el, jangkar }
    var pengamatBar = null;
    var pengamatBaris = [];

    function sembunyikanJs(akar) {
      $$('[data-tanpa-js]', akar).forEach(function (x) { x.hidden = true; });
    }

    /* ---------- kirim & ganti di tempat ---------- */
    function alamatDari(pasangan) {
      var q = new URLSearchParams();
      new FormData(form).forEach(function (v, k) { q.append(k, v); });
      (pasangan || []).forEach(function (p) { q.append(p[0], p[1]); });
      return form.action + '?' + q.toString();
    }
    function pilihFokus(el) {
      if (!el || !form.contains(el)) return null;
      if (el.matches('button[name]')) {
        return 'button[name="' + el.name + '"][value="' + CSS.escape(el.value) + '"]';
      }
      if (el.matches('select[name]')) return 'select[name="' + el.name + '"]';
      return null;
    }
    /* Tanda "baru muncul" pada wadah grafik (data-tumbuh, lihat bagian gerak
       di dasbor-uji.css) — padanan `tandaiTumbuh` prototipe: dilepas sesudah
       1,4 detik. Waktu isinya diganti di tempat, wadah yang tadi sudah tampil
       (deret kartu, atau rincian kartu yang sama — data-wadah) langsung
       dilepas tandanya: grafiknya tidak tumbuh lagi dari nol, cuma berubah
       halus seperti di prototipe. */
    var jedaTumbuh = null;
    function wadahTampak() {
      return new Set($$('[data-wadah]', form).filter(tampak).map(function (el) { return el.getAttribute('data-wadah'); }));
    }
    function pasangTumbuh(lama) {
      $$('[data-tumbuh]', form).forEach(function (el) {
        if (lama && lama.has(el.getAttribute('data-wadah'))) el.removeAttribute('data-tumbuh');
      });
      clearTimeout(jedaTumbuh);
      jedaTumbuh = setTimeout(function () {
        $$('[data-tumbuh]', form).forEach(function (el) { el.removeAttribute('data-tumbuh'); });
      }, 1400);
    }
    function muat(alamat, o) {
      o = o || {};
      form.setAttribute('aria-busy', 'true');
      fetch(alamat, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text().then(function (t) { return { url: r.url, teks: t }; }); })
        .then(function (h) {
          /* Perbuatan yang berakhir di halaman lain (rincian rekomendasi dari
             pintasan yang isinya cuma satu) dibuka sebagai halaman biasa. */
          if (new URL(h.url).pathname !== new URL(form.action).pathname) { location.href = h.url; return; }
          var baru = new DOMParser().parseFromString(h.teks, 'text/html').querySelector('form[data-dasbor]');
          if (!baru) { location.href = h.url; return; }
          /* Diambil sebelum menunya ditutup: menu yang dibuka lagi sesudah ini
             tetap terhitung unsur yang sudah tampil. */
          var sebelum = potretIsi(form), wadahLama = wadahTampak();
          tutupMenu(false);
          sembunyikanKotak();
          form.innerHTML = baru.innerHTML;
          pasangTumbuh(wadahLama);
          var tunda = pasangSifatLama(form, sebelum);
          if (o.dorong) history.pushState({ dasbor: 1 }, '', h.url);
          else history.replaceState({ dasbor: 1 }, '', h.url);
          siapkan();
          if (o.fokus) {
            var f = $(o.fokus, form);
            if (f && tampak(f)) f.focus({ preventScroll: true });
          }
          if (o.menu) bukaLagi(o.menu);
          if (o.kartu) gulirKeRincian();
          /* Seluruh isinya unsur baru, jadi tanpa ini gerak masuknya (kartu
             bergiliran, batang terisi dari nol) berputar lagi di tiap ganti
             filter, dan batangnya melompat ke ukuran baru. Yang tadi sudah
             tampil dianggap tetap berdiri, seperti di prototipe: berubah halus
             dari keadaan lamanya. Yang baru muncul — rincian kartu yang baru
             dibuka, baris yang baru masuk filter — tetap bergerak masuk. */
          sambungIsi(form, sebelum, tunda);
        })
        .catch(function () { location.href = alamat; })
        .then(function () { form.removeAttribute('aria-busy'); });
    }
    function kirimUbah(nilai, menuTadi) {
      muat(alamatDari([['ubah', nilai]]), { dorong: true, menu: menuTadi });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var t = e.submitter;
      /* Membuka rincian rekomendasi: pindah halaman biasa. */
      if (t && t.name === 'lihat') { location.href = alamatDari([[t.name, t.value]]); return; }
      var pasangan = t && t.name ? [[t.name, t.value]] : [];
      var nilai = t && t.name === 'ubah' ? t.value : '';
      /* Tombol yang hilang bersama halamannya: fokus dipindah ke tombol
         pasangannya supaya pengguna papan ketik tidak kehilangan tempat. */
      var fokus = nilai === 'hal:tabel' ? 'button[value="hal:dasbor"]'
        : nilai === 'hal:dasbor' ? 'button[value="hal:tabel"]' : pilihFokus(t);
      var menuTadi = null;
      if (t && menu && menu.el.contains(t)) {
        /* Pilihan di dalam menu: menunya dibuka lagi sesudah halaman diganti,
           dengan pencarian, gulir, dan fokus yang sama. */
        var cari = $('[data-cari]', menu.el);
        menuTadi = { id: menu.el.id, cari: cari ? cari.value : '', fokus: fokus,
          gulir: ($('.isi-menu', menu.el) || {}).scrollTop || 0 };
        fokus = null;
      }
      muat(alamatDari(pasangan), { dorong: true, fokus: fokus, menu: menuTadi,
        kartu: /^kartu:/.test(nilai) });
    });
    window.addEventListener('popstate', function () {
      if (document.body.contains(form)) muat(location.href, { dorong: false });
    });

    /* Rentang grafik per bulan: berganti begitu dipilih. */
    form.addEventListener('change', function (e) {
      if (e.target.matches('select[data-kirim-otomatis]')) {
        muat(alamatDari([]), { dorong: true, fokus: 'select[name="' + e.target.name + '"]' });
      }
    });

    /* Rincian yang baru dibuka bisa jatuh di bawah layar (di ponsel kartunya
       dua kolom). Digulir hanya kalau sebagian besarnya tidak kelihatan. */
    function gulirKeRincian() {
      var el = $('#dsb-rincian', form);
      if (!el) return;
      var r = el.getBoundingClientRect();
      if (r.top <= window.innerHeight * 0.7) return;
      var bar = $('[data-dsb-hasil]', form);
      window.scrollTo({ top: window.scrollY + r.top - jarakLekat(0) - (bar ? bar.offsetHeight : 0) - 8, behavior: 'smooth' });
    }

    /* ---------- keterangan melayang ---------- */
    function tampilKotak(isi, x, y) {
      if (!kotak) {
        kotak = document.createElement('div');
        kotak.setAttribute('aria-hidden', 'true');
        document.body.appendChild(kotak);
      }
      kotak.className = 'petunjuk dsb-petunjuk' + (x > window.innerWidth - 300 ? ' kiri' : '');
      kotak.innerHTML = '<b>' + esc(isi.judul) + '</b>'
        + (isi.baris || []).map(function (b) {
          return '<span class="br"><i style="background:' + esc(b.warna) + '"></i><strong>' + esc(b.nilai)
            + '</strong><span>' + esc(b.nama) + '</span></span>';
        }).join('')
        + (isi.ket ? '<span class="bagi">' + esc(isi.ket) + '</span>' : '');
      kotak.style.left = x + 'px';
      kotak.style.top = y + 'px';
      kotak.hidden = false;
    }
    function sembunyikanKotak() { if (kotak) kotak.hidden = true; }
    function isiDari(el) {
      try { return JSON.parse(el.dataset.petunjuk); } catch (x) { return null; }
    }
    form.addEventListener('pointermove', function (e) {
      if (e.target.closest('[data-garis-tren]')) return;
      var el = e.target.closest('[data-petunjuk]');
      var isi = el && isiDari(el);
      if (!isi) { sembunyikanKotak(); return; }
      tampilKotak(isi, e.clientX, e.clientY);
    });
    form.addEventListener('pointerleave', sembunyikanKotak);
    form.addEventListener('focusin', function (e) {
      var el = e.target.closest('[data-petunjuk]');
      var isi = el && isiDari(el);
      if (!isi) return;
      var r = el.getBoundingClientRect();
      tampilKotak(isi, r.left + r.width / 2, r.top + Math.min(r.height / 2, 20));
    });
    form.addEventListener('focusout', function (e) {
      if (e.target.closest('[data-petunjuk]')) sembunyikanKotak();
    });
    window.addEventListener('scroll', sembunyikanKotak, true);

    /* ---------- garis per bulan ---------- */
    function pasangGaris(g) {
      var bulan = JSON.parse(g.dataset.bulan || '[]');
      var seri = JSON.parse(g.dataset.seri || '[]');
      var n = bulan.length;
      var plot = $('.plot', g);
      var silang = $('.silang', g);
      var aktif = null;
      function X(i) { return n > 1 ? (i / (n - 1)) * 100 : 50; }
      function tunjuk(i, y) {
        aktif = i;
        $$('.titik', g).forEach(function (t) { t.classList.toggle('aktif', +t.dataset.i === i); });
        $$('.sumbux > span', g).forEach(function (s) { s.classList.toggle('aktif', +s.dataset.i === i); });
        silang.hidden = false;
        silang.style.left = X(i) + '%';
        var r = plot.getBoundingClientRect();
        tampilKotak({
          judul: bulan[i],
          baris: seri.map(function (s) { return { warna: s.warna, nilai: s.nilai[i], nama: s.nama }; }),
        }, r.left + (X(i) / 100) * r.width, y === undefined ? r.top + r.height / 3 : y);
      }
      function lepas() {
        aktif = null;
        $$('.aktif', g).forEach(function (t) { t.classList.remove('aktif'); });
        silang.hidden = true;
        sembunyikanKotak();
      }
      g.addEventListener('pointermove', function (e) {
        var r = plot.getBoundingClientRect();
        tunjuk(Math.max(0, Math.min(n - 1, Math.round(((e.clientX - r.left) / r.width) * (n - 1)))), e.clientY);
      });
      g.addEventListener('pointerleave', lepas);
      g.addEventListener('blur', lepas);
      g.addEventListener('focus', function () { tunjuk(aktif === null ? n - 1 : aktif); });
      g.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); tunjuk(Math.max(0, (aktif === null ? n : aktif) - 1)); }
        if (e.key === 'ArrowRight') { e.preventDefault(); tunjuk(Math.min(n - 1, (aktif === null ? -1 : aktif) + 1)); }
        if (e.key === 'Escape') lepas();
      });
    }

    /* ---------- baris filter: yang muat, sisanya di "+N lainnya" ---------- */
    function muatBerapa(lebar, tersedia, lebarLain) {
      var total = lebar.reduce(function (a, w, i) { return a + w + (i ? JARAK : 0); }, 0);
      if (total <= tersedia) return lebar.length;
      var pakai = 0, n = 0;
      for (var i = 0; i < lebar.length; i++) {
        var tambah = (n ? JARAK : 0) + lebar[i];
        if (pakai + tambah + lebarLain > tersedia) break;
        pakai += tambah;
        n++;
      }
      return n;
    }
    function ukurPemilah(p) {
      var baris = $('[data-keping-baris]', p), ukur = $('.dsb-ukur', p);
      if (!baris || !ukur || !tampak(p)) return;
      var keping = $$('[data-keping]', baris);
      var lebar = $$('[data-keping]', ukur).map(function (e) { return e.getBoundingClientRect().width; });
      var semua = $('[data-semua]', ukur).getBoundingClientRect().width;
      var lain = $('[data-lain]', ukur).getBoundingClientRect().width;
      var buangEl = $('[data-buang]', ukur);
      var buang = buangEl ? buangEl.getBoundingClientRect().width + JARAK : 0;
      var n = muatBerapa(lebar, baris.clientWidth - semua - JARAK - buang, lain + JARAK);
      keping.forEach(function (k, i) { k.hidden = i >= n; });
      var jumlah = +p.dataset.jumlah;
      var sisa = jumlah - Math.min(n, keping.length);
      var tombol = $('[data-buka-lain]', baris);
      tombol.hidden = sisa <= 0;
      $('[data-sisa]', tombol).textContent = '+' + sisa + ' lainnya';
      var tampakKunci = keping.slice(0, n).map(function (k) { return JSON.parse(k.dataset.keping); });
      var sisaDipilih = JSON.parse(p.dataset.dipilih || '[]')
        .filter(function (x) { return tampakKunci.indexOf(x) < 0; }).length;
      var lencana = $('[data-sisa-dipilih]', tombol);
      lencana.hidden = !sisaDipilih;
      lencana.textContent = sisaDipilih;
      tombol.classList.toggle('ada', sisaDipilih > 0);
      tombol.title = sisa + ' ' + (p.dataset.satuan || 'pilihan') + ' lain — buka daftar lengkap'
        + (jumlah > 7 ? ' yang bisa dicari' : '');
    }

    /* ---------- menu melayang ---------- */
    /* Di bawah tombolnya, atau di atasnya kalau ruang bawah sempit. Tertutup
       dengan klik di luar, Esc, menggulir halaman, atau lebar jendela yang
       berubah — papan ketik ponsel cuma mengubah tinggi. */
    function tempatkan(el, jangkar) {
      var r = jangkar.getBoundingClientRect();
      var w = Math.min(+(el.dataset.lebar || 340), window.innerWidth - 24);
      var left = Math.round(Math.max(12, Math.min(r.left, window.innerWidth - w - 12)));
      var bawah = window.innerHeight - r.bottom - 16;
      var atas = r.top - jarakLekat(0) - 16;
      el.style.left = left + 'px';
      el.style.width = w + 'px';
      if (bawah < 240 && atas > bawah) {
        el.style.top = '';
        el.style.bottom = Math.round(window.innerHeight - r.top + 6) + 'px';
        el.style.maxHeight = Math.round(Math.min(460, atas)) + 'px';
      } else {
        el.style.bottom = '';
        el.style.top = Math.round(r.bottom + 6) + 'px';
        el.style.maxHeight = Math.round(Math.min(460, Math.max(180, bawah))) + 'px';
      }
    }
    var lebarAwal = window.innerWidth;
    function bukaMenu(el, jangkar, fokusAwal) {
      tutupMenu(false);
      menu = { el: el, jangkar: jangkar };
      lebarAwal = window.innerWidth;
      el.hidden = false;
      tempatkan(el, jangkar);
      if (jangkar.hasAttribute('aria-expanded')) jangkar.setAttribute('aria-expanded', 'true');
      if (fokusAwal !== false) {
        var awal = $('input, button:not(:disabled)', el);
        if (awal) awal.focus({ preventScroll: true });
      }
    }
    function tutupMenu(fokus) {
      if (!menu) return;
      var m = menu;
      menu = null;
      m.el.hidden = true;
      /* Seperti prototipe, yang melepas menunya saat ditutup: dibuka lagi,
         pencariannya mulai dari kosong. */
      var cari = $('[data-cari]', m.el);
      if (cari && cari.value) { cari.value = ''; filterMenu(m.el); }
      if (m.jangkar.hasAttribute('aria-expanded')) m.jangkar.setAttribute('aria-expanded', 'false');
      /* Daftar pintasan: keadaannya ikut dilepas dari alamat. */
      if (m.el.id === 'dsb-pintas') {
        var u = new URL(location.href);
        u.searchParams.delete('pintas');
        history.replaceState(history.state, '', u.toString());
      }
      if (fokus && m.jangkar.isConnected) m.jangkar.focus({ preventScroll: true });
    }
    function bukaLagi(tadi) {
      var el = document.getElementById(tadi.id);
      var jangkar = el && $('[aria-controls="' + tadi.id + '"]', form);
      if (!el || !jangkar || !tampak(jangkar)) return;
      bukaMenu(el, jangkar, false);
      var cari = $('[data-cari]', el);
      if (cari && tadi.cari) { cari.value = tadi.cari; filterMenu(el); }
      var isi = $('.isi-menu', el);
      if (isi) isi.scrollTop = tadi.gulir;
      var f = tadi.fokus && $(tadi.fokus, el);
      if (!f || f.hidden || f.disabled) f = $('input, button:not(:disabled)', el);
      if (f) f.focus({ preventScroll: true });
    }
    document.addEventListener('pointerdown', function (e) {
      if (!menu) return;
      if (menu.jangkar.contains(e.target) || menu.el.contains(e.target)) return;
      tutupMenu(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu) { e.preventDefault(); tutupMenu(true); }
    });
    window.addEventListener('scroll', function (e) {
      if (!menu) return;
      if (menu.el.contains(e.target) || menu.el.contains(document.activeElement)) return;
      tutupMenu(false);
    }, true);
    window.addEventListener('resize', function () {
      if (menu && window.innerWidth !== lebarAwal) tutupMenu(false);
    });
    form.addEventListener('focusout', function (e) {
      if (!menu || !menu.el.contains(e.target)) return;
      var t = e.relatedTarget;
      if (!t || menu.el.contains(t) || t === menu.jangkar) return;
      tutupMenu(false);
    });
    form.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-lain], [data-buka-menu]');
      if (b) {
        var el = document.getElementById(b.getAttribute('aria-controls'));
        if (!el) return;
        if (menu && menu.el === el) tutupMenu(false);
        else bukaMenu(el, b);
        return;
      }
      /* Baris tabel "Sisa nilai terbesar": seluruh barisnya bisa diklik. */
      var tr = e.target.closest('tr[data-baris-klik]');
      if (tr && !e.target.closest('button')) {
        var t = $('button[name="lihat"]', tr);
        if (t) t.click();
      }
    });

    /* Daftar pilihan: cari, pilih semua, kosongkan, per kelompok. */
    function kunciOpsi(o) { return JSON.parse(o.dataset.opsi); }
    function dipilihDi(el) {
      return JSON.parse(el.closest('[data-pemilah]').dataset.dipilih || '[]');
    }
    function filterMenu(el) {
      var cari = $('[data-cari]', el);
      var q = cari ? cari.value.trim().toLowerCase() : '';
      var ada = 0;
      $$('.opsi[data-opsi]', el).forEach(function (o) {
        var cocok = !q || (o.dataset.teks || '').indexOf(q) >= 0;
        o.hidden = !cocok;
        if (cocok) ada++;
      });
      $$('[data-grup]', el).forEach(function (g) {
        g.hidden = !$$('.opsi[data-opsi]', g).some(function (o) { return !o.hidden; });
      });
      var kosong = $('[data-kosong-menu]', el);
      if (kosong) { kosong.hidden = ada > 0; $('span', kosong).textContent = q; }
      var pilih = dipilihDi(el);
      var cocokSemua = $$('.opsi[data-opsi]', el).filter(function (o) { return !o.hidden; });
      var semuaDi = cocokSemua.length > 0 && cocokSemua.every(function (o) { return pilih.indexOf(kunciOpsi(o)) >= 0; });
      var bs = $('[data-pilih-semua]', el), bk = $('[data-kosongkan]', el);
      if (bs) { bs.textContent = q ? 'Pilih yang cocok' : 'Pilih semua'; bs.disabled = semuaDi || !cocokSemua.length; }
      if (bk) { bk.textContent = q ? 'Lepas yang cocok' : 'Kosongkan'; bk.disabled = !pilih.length; }
    }
    function setel(el, baru, fokus) {
      var p = el.closest('[data-pemilah]');
      var cari = $('[data-cari]', el);
      kirimUbah(p.dataset.setel + ':' + JSON.stringify(baru),
        { id: el.id, cari: cari ? cari.value : '', fokus: fokus, gulir: ($('.isi-menu', el) || {}).scrollTop || 0 });
    }
    form.addEventListener('input', function (e) {
      if (e.target.matches('[data-cari]')) filterMenu(e.target.closest('[data-menu]'));
    });
    form.addEventListener('keydown', function (e) {
      /* Enter di kotak cari tidak boleh mengirim formulir lewat tombol pertamanya. */
      if (e.key === 'Enter' && e.target.matches('[data-cari]')) e.preventDefault();
    });
    form.addEventListener('click', function (e) {
      var el = e.target.closest('[data-menu]');
      if (!el) return;
      var tampakOpsi = $$('.opsi[data-opsi]', el).filter(function (o) { return !o.hidden; }).map(kunciOpsi);
      var pilih = dipilihDi(el);
      var cari = $('[data-cari]', el);
      var q = cari ? cari.value.trim() : '';
      if (e.target.closest('[data-pilih-semua]')) {
        setel(el, pilih.concat(tampakOpsi.filter(function (k) { return pilih.indexOf(k) < 0; })), '[data-pilih-semua]');
      } else if (e.target.closest('[data-kosongkan]')) {
        setel(el, q ? pilih.filter(function (k) { return tampakOpsi.indexOf(k) < 0; }) : [], '[data-kosongkan]');
      } else if (e.target.closest('[data-pilih-grup]')) {
        var isi = $$('.opsi[data-opsi]', e.target.closest('[data-grup]')).map(kunciOpsi);
        var semuaDi = isi.every(function (k) { return pilih.indexOf(k) >= 0; });
        setel(el, semuaDi ? pilih.filter(function (k) { return isi.indexOf(k) < 0; })
          : pilih.concat(isi.filter(function (k) { return pilih.indexOf(k) < 0; })), null);
      }
    });

    /* ---------- menyiapkan isi halaman (awal, dan tiap kali diganti) ---------- */
    function siapkan() {
      sembunyikanJs(form);
      $$('[data-garis-tren]', form).forEach(pasangGaris);

      pengamatBaris.forEach(function (o) { o.disconnect(); });
      pengamatBaris = [];
      $$('[data-pemilah]', form).forEach(function (p) {
        ukurPemilah(p);
        if (window.ResizeObserver) {
          var o = new ResizeObserver(function () { ukurPemilah(p); });
          o.observe($('[data-keping-baris]', p));
          pengamatBaris.push(o);
        }
      });
      $$('[data-menu]', form).forEach(function (el) { if (el.querySelector('[data-cari]')) filterMenu(el); });

      /* Bar hasil memberi bayangan hanya saat melayang di atas grafik. */
      if (pengamatBar) pengamatBar.disconnect();
      var bar = $('[data-dsb-hasil]', form);
      if (bar && window.IntersectionObserver) {
        var jarak = jarakLekat(0);
        pengamatBar = new IntersectionObserver(function (isi) {
          var e = isi[0];
          bar.classList.toggle('nempel', e.intersectionRatio < 1 && e.boundingClientRect.top <= jarak + 2);
        }, { threshold: [1], rootMargin: '-' + (Math.round(jarak) + 1) + 'px 0px 0px 0px' });
        pengamatBar.observe(bar);
      }

      /* Daftar pintasan yang dibuka server: ditempatkan di dekat selnya. */
      var pintas = $('[data-buka-otomatis]', form);
      if (pintas) {
        var jangkar = $('button[value="' + CSS.escape(pintas.dataset.jangkar) + '"]', form);
        pintas.hidden = true;
        if (jangkar) bukaMenu(pintas, jangkar);
      }
    }

    window.addEventListener('resize', function () {
      $$('[data-pemilah]', form).forEach(ukurPemilah);
    });
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function () { $$('[data-pemilah]', form).forEach(ukurPemilah); });
    }
    history.replaceState({ dasbor: 1 }, '', location.href);
    siapkan();
    pasangTumbuh(null);
  }

  /* ================= formulir Catat laporan baru ================= */
  /* Padanan `FormBaru` prototipe (24 Sep): satu halaman seperti mengisi
     dokumen, dengan temuan, rekomendasi, dan tindak lanjut berjajar ke samping
     sebagai tab. Seluruh panel dirender server; skrip ini memilih mana yang
     tampil tanpa mengirim, menyalakan judul dan tanda lengkap selagi diketik,
     dan mengembalikan posisi gulir sesudah halaman dimuat ulang. Semuanya
     kenyamanan: tanpa berkas ini tombol tabnya mengirim formulir dan server
     yang memilihkan — isian yang tersembunyi pun ikut terkirim, karena
     disembunyikan, bukan dibuang. */

  /* Tab dan kepala rekomendasi memuat inti uraiannya, bukan pembukanya —
     pola yang sama dengan LaporanBaruController::PEMBUKA_URAIAN. */
  var PEMBUKA_URAIAN = /^(?:Menteri (?:Pekerjaan Umum(?: dan Perumahan Rakyat)?|PUPR|PU)|Kepala (?:BPSDM|Badan)|Sekretaris Badan)\s+agar\s+(?:memerintahkan\s+(?:Kepala BPSDM|Sekretaris Badan|(?:para )?kepala balai)\s+(?:untuk\s+)?)?/i;
  function intiUraian(t) {
    var s = t.replace(PEMBUKA_URAIAN, '');
    return !s || s === t ? t : s.charAt(0).toUpperCase() + s.slice(1);
  }
  /* CheckCircle2 dan Circle hanya berbeda satu garis centang. */
  var IKON_CENTANG = '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>';
  var IKON_LINGKAR = '<circle cx="12" cy="12" r="10"/>';

  function pasangFormBaru() {
    var form = $('[data-form-baru]');
    if (!form) return;
    var KUNCI_GULIR = 'simtlhp.formBaru.gulir';

    /* ---------- gulir ---------- */
    /* Bagian yang baru dibuka, ditambahkan, atau ditunjuk digulir ke bawah
       batang atas dan deret tab temuan yang menempel — padanan `gulirKe`.
       Tanpa `paksa`, hanya kalau bagian itu tertutup atau terlalu jauh di
       bawah. `fokus` sekalian menaruh kursor di isian wajib pertama yang
       masih kosong. */
    function gulirKe(el, paksa, fokus) {
      var batas = jarakLekat(0);
      var wadah = el.closest('.fb-temuan');
      var deret = wadah && $('.fb-tab-tem', wadah);
      if (deret && getComputedStyle(deret).position === 'sticky') batas += deret.getBoundingClientRect().height;
      var atas = el.getBoundingClientRect().top;
      if (paksa || atas < batas || atas > window.innerHeight * 0.6) {
        window.scrollTo({ top: Math.max(0, window.scrollY + atas - batas - 12), behavior: 'smooth' });
      }
      if (!fokus) return;
      /* Panel yang tidak terpilih ikut dirender, jadi yang dicari hanya
         yang tampak. Kelompok wajib (keping satuan kerja): kursor ke
         isiannya, bukan ke ikon Info di labelnya. */
      var kosong = $$('[aria-required="true"], [data-wajib="kosong"]', el).filter(function (x) {
        return tampak(x) && (x.dataset.wajib === 'kosong' || !String(x.value || '').trim());
      })[0];
      var ke = kosong && (kosong.matches('input, select, textarea')
        ? kosong : $('.fb-isian input:not([type=hidden]), .fb-isian button', kosong));
      if (ke) ke.focus({ preventScroll: true });
    }

    /* Bagian yang dituju server — lewat jangkar alamat pada pemuatan biasa,
       lewat data-gulir pada jawaban yang ditukar di tempat — digulir ke sana.
       Yang ditahan karena isiannya kurang (data-fokus) sekalian diberi kursor
       di isian kosong pertamanya. */
    function arahkan(sasaran) {
      var tunjuk = form.hasAttribute('data-fokus');
      if (sasaran) {
        gulirKe(sasaran, tunjuk, tunjuk);
      } else if (tunjuk) {
        var aktif = $('[data-panel-tem]:not([hidden])', form) || $('#fb-surat', form);
        if (aktif) gulirKe(aktif, false, true);
      }
    }

    /* Tanpa skrip hampir tiap tombol memuat ulang halaman, dan halaman yang
       selalu kembali ke puncak memaksa pengisinya mencari lagi tempatnya.
       Posisinya dibawa ke pemuatan berikutnya — kecuali langkahnya berganti:
       pindah ke tinjauan dan kembali selalu mulai dari atas. */
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    window.addEventListener('pagehide', function () {
      try {
        sessionStorage.setItem(KUNCI_GULIR, JSON.stringify({
          langkah: form.dataset.langkah, y: window.scrollY, t: Date.now() }));
      } catch (e) { /* penyimpanan dimatikan: mulai dari atas saja */ }
    });
    var lama = null;
    try {
      lama = JSON.parse(sessionStorage.getItem(KUNCI_GULIR) || 'null');
      sessionStorage.removeItem(KUNCI_GULIR);
    } catch (e) { lama = null; }
    var sasaran = location.hash ? document.getElementById(location.hash.slice(1)) : null;
    if (sasaran && !form.contains(sasaran)) sasaran = null;
    if (lama && lama.langkah === form.dataset.langkah && Date.now() - lama.t < 60000) window.scrollTo(0, lama.y);
    else window.scrollTo(0, 0);
    arahkan(sasaran);
    /* Alamatnya sudah menunjuk bagiannya; sesudah digulir, jangkarnya dilepas
       supaya menyegarkan halaman tidak melompat ke sana lagi. */
    if (sasaran) history.replaceState(history.state, '', location.pathname + location.search);

    /* ---------- tab ---------- */
    function tampilkan(tab) {
      $$('[role="tab"]', tab.closest('[role="tablist"]')).forEach(function (t) {
        var aktif = t === tab;
        t.classList.toggle('aktif', aktif);
        t.setAttribute('aria-selected', aktif ? 'true' : 'false');
        t.tabIndex = aktif ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !aktif;
      });
    }

    /* Pilihan tab dibawa masukan tersembunyi ui[…], jadi kiriman berikutnya
       mendarat di tab yang sama. */
    function pilihTab(tab) {
      var v = tab.value.split(':');
      var panel = document.getElementById(tab.getAttribute('aria-controls'));
      if (v[0] === 'temuan') {
        if (tab.classList.contains('aktif')) return;
        tampilkan(tab);
        $('[data-ui-aktif]', form).value = v[1];
        /* Berpindah temuan melepas pilihan rekomendasi yang terbuka: di
           temuan tujuan, yang pertama yang tampil. */
        $('[data-ui-buka]', form).value = '';
        var rek1 = panel && $('[id^="fb-tab-rek-"]', panel);
        if (rek1) tampilkan(rek1);
        if (panel) gulirKe(panel, false, false);
        return;
      }
      tampilkan(tab);
      if (v[0] === 'rek') {
        $('[data-ui-buka]', form).value = v[1];
      } else if (v[0] === 'tl') {
        var ui = $('[data-ui-tl]', tab.closest('.fb-sub-tl'));
        if (ui) ui.value = v[2];
      }
    }

    /* Kepala bagian surat: dibuka-tutup di tempat. */
    function bukaSurat(buka) {
      var bag = $('#fb-surat', form), alih = bag && $('[data-alih-surat]', bag);
      if (!alih) return;
      bag.classList.toggle('buka', buka);
      alih.closest('.fb-kep').classList.toggle('buka', buka);
      alih.setAttribute('aria-expanded', buka ? 'true' : 'false');
      $('#fb-surat-isi', bag).hidden = !buka;
      $('[data-ui-surat]', bag).value = buka ? '1' : '0';
    }

    form.addEventListener('click', function (e) {
      var tab = e.target.closest('[role="tab"][data-tab]');
      if (tab) {
        e.preventDefault();
        pilihTab(tab);
        return;
      }
      if (e.target.closest('[data-alih-surat]')) {
        e.preventDefault();
        bukaSurat(!$('#fb-surat', form).classList.contains('buka'));
        return;
      }
      /* Naik-turun jumlah angsuran, di tempat — aturannya sama dengan
         `angsur` di LaporanBaruController::terapkan: dari kosong langsung dua
         kali (sekali berarti dibayar sekaligus), paling banyak 24. */
      var ang = e.target.closest('[data-angsur-naik], [data-angsur-turun]');
      if (ang) {
        e.preventDefault();
        var isi = $('[data-angsur]', ang.closest('[data-angsur-rek]'));
        var kini = angka(isi.value), naik = ang.hasAttribute('data-angsur-naik');
        var baru = kini === 0 ? (naik ? 2 : 0) : kini + (naik ? 1 : -1);
        isi.value = baru < 2 ? '' : String(Math.min(baru, 24));
        periksa();
      }
    });

    form.addEventListener('keydown', function (e) {
      var tab = e.target.closest('[role="tab"][data-tab]');
      if (tab) {
        /* Panah kiri-kanan, Home, dan End berpindah tab — cara baku deret
           tab bagi pengguna papan ketik — lalu fokus ikut ke tab tujuan. */
        var arah = { ArrowRight: 1, ArrowLeft: -1, Home: 'awal', End: 'akhir' }[e.key];
        if (arah === undefined) return;
        e.preventDefault();
        var semua = $$('[role="tab"]', tab.closest('[role="tablist"]'));
        var n = semua.length, i = semua.indexOf(tab);
        var j = arah === 'awal' ? 0 : arah === 'akhir' ? n - 1 : (i + arah + n) % n;
        pilihTab(semua[j]);
        semua[j].focus();
        return;
      }
      /* Enter di isian satu baris tidak mengirim apa pun, seperti di
         prototipe. Tanpa ini browser memakai tombol kirim pertama. */
      if (e.key === 'Enter' && e.target.matches('input')) e.preventDefault();
    });

    /* ---------- judul yang ikut diketik ---------- */
    function aturJudul(el) {
      var p = el.dataset.ikutJudul.split(':');
      var v = el.value.trim();
      var teks = p[0] === 'rek' ? intiUraian(v) : v;
      var tab = null, kep = null;
      if (p[0] === 'tem') {
        tab = document.getElementById('fb-tab-tem-' + p[1]);
        kep = $('#fb-tem-' + p[1] + ' > .fb-kep [data-kep-judul]');
      } else if (p[0] === 'rek') {
        tab = document.getElementById('fb-tab-rek-' + p[1]);
        kep = $('#fb-rek-' + p[1] + ' > .fb-kep [data-kep-judul]');
      } else if (p[0] === 'tl') {
        tab = document.getElementById('fb-tab-tl-' + p[1] + '-' + p[2]);
      }
      if (tab) {
        var s = $('[data-tab-teks]', tab);
        s.textContent = teks || s.dataset.kosong;
        s.classList.toggle('fb-kosong', !teks);
        tab.title = (p[0] === 'rek' ? v : teks) || s.dataset.kosong;
      }
      if (kep) {
        kep.textContent = teks || kep.dataset.kosong;
        kep.classList.toggle('fb-kosong', !teks);
        if (teks) kep.title = teks; else kep.removeAttribute('title');
      }
    }

    /* ---------- tanda lengkap ---------- */
    /* Aturannya sama dengan tindakanOk, rekOk, temOk, dan kurangIsi di
       LaporanBaruController. Server tetap yang memutus — ini cuma supaya
       tanda di tab dan bilah bawah tidak basi selagi diisi. */
    function nilaiDi(akar, akhiran) {
      var el = $('[name$="' + akhiran + '"]', akar);
      return el ? String(el.value || '').trim() : '';
    }
    function nilaiSurat(k) {
      var el = form.elements['surat[' + k + ']'];
      return el ? String(el.value || '').trim() : '';
    }
    function dicentang(akar) { return $$('input[data-pilih-satker]:checked', akar); }
    function satkerTem(p) { return $$('input[name="tem[' + p.dataset.panelTem + '][satker][]"]:checked', p); }
    function okTl(x) {
      return nilaiDi(x, '[bentuk]') !== '' && dicentang(x).length > 0 && nilaiDi(x, '[tgl_renaksi]') !== '';
    }
    function okRek(r) {
      var tl = $$('[data-panel-tl]', r);
      return nilaiDi(r, '[uraian]') !== '' && tl.some(function (x) { return dicentang(x).length > 0; })
        && tl.every(okTl);
    }
    function okTem(p) {
      return nilaiDi(p, '[judul]') !== '' && nilaiDi(p, '[sebab]') !== '' && nilaiDi(p, '[akibat]') !== ''
        && satkerTem(p).length > 0 && nilaiDi(p, '[kategori]') !== ''
        && $$('[data-panel-rek]', p).every(okRek);
    }
    function salahTanggal() {
      var a = nilaiSurat('tgl_surat'), b = nilaiSurat('tgl_terima');
      var ini = (form.elements['surat[tgl_surat]'] || {}).max || '';
      if (!a || !b) return '';
      if (ini && a > ini) return 'tanggal surat masih di depan hari ini';
      if (ini && b > ini) return 'tanggal diterima masih di depan hari ini';
      if (b < a) return 'tanggal diterima mendahului tanggal suratnya';
      return '';
    }
    function kurangIsi() {
      var k = [];
      if (!nilaiSurat('nomor')) k.push('nomor surat');
      if (!nilaiSurat('tgl_surat')) k.push('tanggal surat');
      if (!nilaiSurat('tgl_terima')) k.push('tanggal diterima');
      var salah = salahTanggal();
      if (salah) return 'Surat laporan perlu dibetulkan: ' + salah;
      if (k.length) return 'Surat laporan belum lengkap: ' + k.join(', ');
      var tem = $$('[data-panel-tem]', form);
      for (var i = 0; i < tem.length; i++) {
        var p = tem[i];
        if (okTem(p)) continue;
        var kk = [];
        if (!nilaiDi(p, '[judul]')) kk.push('judul');
        if (!nilaiDi(p, '[kategori]')) kk.push('kategori temuan');
        if (!satkerTem(p).length) kk.push('satuan kerja terperiksa');
        if (!nilaiDi(p, '[sebab]')) kk.push('sebab');
        if (!nilaiDi(p, '[akibat]')) kk.push('akibat');
        var rek = $$('[data-panel-rek]', p);
        for (var j = 0; j < rek.length; j++) {
          var r = rek[j];
          if (okRek(r)) continue;
          var h = j < 26 ? String.fromCharCode(97 + j) : String(j + 1);
          var tl = $$('[data-panel-tl]', r), sebelum = kk.length;
          if (!nilaiDi(r, '[uraian]')) kk.push('uraian rekomendasi ' + h);
          if (tl.some(function (x) { return !nilaiDi(x, '[bentuk]'); })) kk.push('bentuk tindak lanjut di rekomendasi ' + h);
          if (!tl.length || tl.some(function (x) { return !dicentang(x).length; })) kk.push('satuan kerja di rekomendasi ' + h);
          if (tl.some(function (x) { return !nilaiDi(x, '[tgl_renaksi]'); })) kk.push('tanggal rencana aksi di rekomendasi ' + h);
          if (kk.length === sebelum) kk.push('rekomendasi ' + h);
          break;
        }
        return 'Temuan ' + (i + 1) + ' belum lengkap: ' + kk.join(', ');
      }
      return '';
    }

    function tandaTab(tab, ok) {
      if (!tab) return;
      var ikon = $('.fb-tab-ok, .fb-tab-kurang', tab);
      if (ikon) {
        ikon.innerHTML = ok ? IKON_CENTANG : IKON_LINGKAR;
        ikon.setAttribute('class', ok ? 'fb-tab-ok' : 'fb-tab-kurang');
      }
      var sr = $('.fb-sr', tab);
      if (sr) sr.textContent = ok ? ', lengkap' : ', belum lengkap';
    }
    function tandaKep(kep, ok) {
      var st = kep && $('.fb-status', kep);
      if (!st) return;
      st.classList.toggle('ok', ok);
      var ikon = $('svg', st);
      if (ikon) ikon.innerHTML = ok ? IKON_CENTANG : IKON_LINGKAR;
      var t = $('span', st);
      if (t) t.textContent = ok ? 'Lengkap' : 'Belum lengkap';
    }

    /* Nilai rekomendasi dijumlah dari bagian tiap satuan kerja yang
       dicentang; rencana angsuran baru berarti kalau ada yang ditagih. */
    function aturNilaiRek(r, nilai) {
      var b = $('[data-nilai-rek]', r);
      if (b) {
        b.textContent = nilai > 0 ? rp(nilai) : 'Rp 0';
        $('[data-nilai-kosong]', r).hidden = nilai > 0;
      }
      var ang = $('[data-angsur-rek]', r);
      if (!ang) return;
      ang.hidden = !(nilai > 0);
      var n = angka($('[data-angsur]', ang).value);
      $('[data-angsur-ket]', ang).textContent = n > 1
        ? 'kali · sekitar ' + rp(Math.round(nilai / n)) + ' per angsuran'
        : 'kosongkan bila dibayar sekaligus';
      $('[data-angsur-naik]', ang).disabled = n >= 24;
      $('[data-angsur-turun]', ang).disabled = !n;
      var kunci = $('[data-angsur-kunci]', ang);
      kunci.disabled = !n;
      if (!n) kunci.checked = false;
    }

    function periksa() {
      if (form.dataset.langkah !== '1') return;
      var tem = $$('[data-panel-tem]', form);
      var nRek = 0, nPen = 0, sat = {};
      var surat = $('#fb-surat > .fb-kep', form);
      tandaKep(surat, nilaiSurat('nomor') !== '' && !!nilaiSurat('tgl_surat')
        && !!nilaiSurat('tgl_terima') && !salahTanggal());
      tem.forEach(function (p) {
        $$('[data-panel-rek]', p).forEach(function (r) {
          var tl = $$('[data-panel-tl]', r), pen = 0, nilai = 0;
          nRek++;
          tl.forEach(function (x) {
            var pilih = dicentang(x);
            pen += pilih.length;
            pilih.forEach(function (c) {
              sat[c.value] = true;
              var isi = $('[data-nilai-satker="' + c.value + '"] input', x);
              if (isi) nilai += angka(isi.value);
            });
            var grup = $('[data-wajib]', x);
            if (grup) grup.dataset.wajib = pilih.length ? 'isi' : 'kosong';
            tandaTab(document.getElementById('fb-tab-tl-' + x.dataset.panelTl.replace(':', '-')), okTl(x));
          });
          nPen += pen;
          var ok = okRek(r);
          tandaTab(document.getElementById('fb-tab-rek-' + r.dataset.panelRek), ok);
          tandaKep($(':scope > .fb-kep', r), ok);
          var ket = $('.fb-sub-tl > .fb-subkep > .fb-ket', r);
          if (ket) ket.textContent = tl.length + ' tindak lanjut · ' + pen + ' penugasan';
          aturNilaiRek(r, nilai);
        });
        var grupTem = $(':scope > .fb-isi > [data-wajib]', p);
        if (grupTem) grupTem.dataset.wajib = satkerTem(p).length ? 'isi' : 'kosong';
        var ok = okTem(p);
        tandaTab(document.getElementById('fb-tab-tem-' + p.dataset.panelTem), ok);
        tandaKep($(':scope > .fb-kep', p), ok);
      });
      var daftar = $('.fb-daftar-kep > span', form);
      if (daftar) daftar.textContent = tem.length + ' temuan · ' + nRek + ' rekomendasi · ' + nPen + ' penugasan';
      var kurang = $('[data-kurang]', form), lengkap = $('[data-lengkap]', form);
      if (kurang && lengkap) {
        var k = kurangIsi();
        kurang.hidden = !k;
        lengkap.hidden = !!k;
        $('[data-kurang-teks]', kurang).textContent = k;
        $('[data-lengkap-teks]', lengkap).textContent = tem.length + ' temuan, ' + nRek + ' rekomendasi, '
          + Object.keys(sat).length + ' satuan kerja';
      }
    }

    /* ---------- satuan kerja tindak lanjut ---------- */
    /* Baris nilai muncul untuk satuan kerja yang dicentang saja, dan hanya
       kalau rekomendasinya memang menuntut uang. */
    function aturNilai(tindak) {
      var kotak = $('[data-nilai-tindak]', tindak);
      if (!kotak) return;
      var dipilih = 0;
      $$('[data-pilih-satker]', tindak).forEach(function (c) {
        var baris = $('[data-nilai-satker="' + c.value + '"]', kotak);
        if (baris) baris.hidden = !c.checked;
        if (c.checked) dipilih++;
      });
      var sifat = $('[data-sifat]', tindak.closest('[data-blok-rek]'));
      /* Keterangan "menuntut penyetoran" dari data master yang menentukan,
         bukan nama sifatnya. */
      var opsi = sifat && sifat.options[sifat.selectedIndex];
      var uang = !opsi || opsi.dataset.uang === '1';
      kotak.hidden = !dipilih || !uang;
    }

    /* Siapa yang diberi tahu begitu laporannya disimpan — padanan PenerimaPemberitahuan.
       Penanggung jawab tiap satuan kerja dibawa kepingnya sendiri (data-pj). */
    function aturPenerima(tindak) {
      var kotak = $('[data-penerima]', tindak);
      if (!kotak) return;
      var ada = [], tanpa = [];
      $$('[data-pilih-satker]', tindak).forEach(function (c) {
        if (c.checked) (c.dataset.pj ? ada : tanpa).push(c);
      });
      var isi = '';
      if (ada.length) {
        isi += 'Pemberitahuan dikirim lewat aplikasi dan email ke: ' + ada.map(function (c) {
          return '<b>' + esc(c.dataset.pj) + '</b> (' + esc(c.dataset.pendek) + ')';
        }).join(', ') + '.';
      }
      if (tanpa.length) {
        isi += '<span class="tanpa">' + (ada.length ? ' ' : '') + tanpa.map(function (c) {
          return esc(c.dataset.pendek);
        }).join(', ') + ' belum punya penanggung jawab — pilih di Data master agar pemberitahuannya terkirim.</span>';
      }
      $('[data-penerima-isi]', kotak).innerHTML = isi;
      kotak.hidden = !isi;
    }

    form.addEventListener('input', function (e) {
      if (e.target.matches('[data-ikut-judul]')) aturJudul(e.target);
      periksa();
    });
    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-pilih-satker]')) {
        var tindak = e.target.closest('[data-tindak]');
        if (tindak) { aturNilai(tindak); aturPenerima(tindak); }
      }
      if (e.target.matches('[data-sifat]')) {
        $$('[data-tindak]', e.target.closest('[data-blok-rek]')).forEach(aturNilai);
      }
      periksa();
      /* Pilihan yang mengubah bagian lain formulir — satuan kerja temuan,
         sumber laporan, tanggal diterima, sifat — dikirim ke server. */
      if (e.target.matches('[data-kirim]')) kirimNanti(e.target);
    });

    /* Pemilih satuan kerja yang panjang: daftar sisanya di balik pencarian. */
    function saringPilih(wadah, kata, buka) {
      var daftar = $('.daftarpilih', wadah), b = $('[data-buka-daftar-satker]', wadah);
      if (!daftar) return;
      kata = String(kata || '').trim().toLowerCase();
      var n = 0;
      $$('.kepingpilih', daftar).forEach(function (k) {
        var cocok = !kata || (k.dataset.nama || '').indexOf(kata) >= 0;
        k.hidden = !cocok;
        if (cocok) n++;
      });
      $('[data-tak-cocok]', daftar).hidden = n > 0;
      if (kata || buka) daftar.hidden = false;
      if (b) b.textContent = daftar.hidden ? b.dataset.buka : b.dataset.tutup;
    }
    form.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-daftar-satker]');
      if (!b) return;
      var daftar = $('.daftarpilih', b.closest('[data-pilih-cari]'));
      daftar.hidden = !daftar.hidden;
      b.textContent = daftar.hidden ? b.dataset.buka : b.dataset.tutup;
    });
    form.addEventListener('input', function (e) {
      if (e.target.matches('[data-cari-satker]')) saringPilih(e.target.closest('[data-pilih-cari]'), e.target.value, false);
    });

    /* ---------- kirim tanpa memuat ulang (29 Sep) ---------- */
    /* Hizkia: "pada hal hal kecil saja butuh loading dulu, misal pada saat
       catat laporan baru saat mengklik, menghapus satuan kerja itu dia
       loading dan ke refresh lagi". Tombol dan pilihan yang memang perlu
       server — menambah dan menghapus, satuan kerja temuan, sumber laporan,
       tanggal diterima, sifat, Tunjukkan, pindah langkah — dikirim di
       belakang layar. Server menyimpan drafnya lalu menjawab dengan formulir
       yang baru (LaporanBaruController::keFormulir), dan isinya ditukar di
       tempat: halaman tidak dimuat ulang; posisi gulir, fokus, dan pencarian
       satuan kerja tetap; yang diubah selagi menunggu jawaban dipasang lagi.
       Yang meninggalkan formulir (Kembali ke beranda, Simpan draft, Ajukan
       laporan) tetap berpindah halaman seperti biasa. */
    var PINDAH_HALAMAN = ['tinggalkan', 'simpan-draf', 'ajukan'];
    var kiriman = null;       // yang sedang di jalan: { aksi }
    var antre;                // undefined: kosong · null: isian saja · { name, value }: tombol
    var jedaKirim = null, tanggalTertunda = null, pergi = false, menukar = false;

    /* Kunci tiap isian: namanya, ditambah nilainya untuk kotak centang dan
       urutannya untuk nama kembar (daftar dokumen). */
    function tiapIsian(fn) {
      var urut = {};
      $$('input[name], select[name], textarea[name]', form).forEach(function (el) {
        if (el.name === '_token' || /^(submit|button|file)$/.test(el.type)) return;
        var centang = el.type === 'checkbox' || el.type === 'radio';
        urut[el.name] = (urut[el.name] || 0) + 1;
        fn(centang ? el.name + '=' + el.value : el.name + '#' + urut[el.name], el, centang);
      });
    }
    function potretIsian() {
      var hasil = {};
      tiapIsian(function (k, el, centang) { hasil[k] = centang ? el.checked : el.value; });
      return hasil;
    }
    function petaIsian() {
      var peta = {};
      tiapIsian(function (k, el) { peta[k] = el; });
      return peta;
    }

    function namaPilih(w) { var c = w && $('input[data-pilih-satker]', w); return c ? c.name : ''; }
    function pilihBernama(nama) {
      return $$('[data-pilih-cari]', form).filter(function (w) { return namaPilih(w) === nama; })[0] || null;
    }
    function keadaanPilih() {
      return $$('[data-pilih-cari]', form).map(function (w) {
        var cari = $('[data-cari-satker]', w), daftar = $('.daftarpilih', w);
        return { nama: namaPilih(w), kata: cari ? cari.value : '', buka: !!daftar && !daftar.hidden };
      }).filter(function (s) { return s.nama && (s.kata || s.buka); });
    }
    function pulihkanPilih(daftar) {
      daftar.forEach(function (s) {
        var w = pilihBernama(s.nama);
        if (!w) return;
        var cari = $('[data-cari-satker]', w);
        if (cari) cari.value = s.kata;
        saringPilih(w, s.kata, s.buka);
      });
    }

    /* Unsur yang sedang difokus, dikenali dari nama atau nilainya — bukan
       dari unsurnya sendiri, yang ikut terganti. */
    function kunciFokus(el) {
      var hasil = null;
      if (el.matches('input[name], select[name], textarea[name]')) {
        tiapIsian(function (k, x) { if (x === el) hasil = 'isian:' + k; });
      } else if (el.matches('button[name]')) {
        hasil = 'tombol:' + el.name + '=' + el.value;
      } else if (el.matches('[data-cari-satker], [data-buka-daftar-satker]')) {
        hasil = (el.matches('[data-cari-satker]') ? 'cari:' : 'daftar:') + namaPilih(el.closest('[data-pilih-cari]'));
      } else if (el.id) {
        hasil = 'id:' + el.id;
      }
      return hasil;
    }
    function cariFokus(kunci) {
      if (!kunci) return null;
      var i = kunci.indexOf(':'), jenis = kunci.slice(0, i), isi = kunci.slice(i + 1);
      if (jenis === 'isian') return petaIsian()[isi] || null;
      if (jenis === 'tombol') {
        var j = isi.indexOf('='), n = isi.slice(0, j), v = isi.slice(j + 1);
        return $$('button[name]', form).filter(function (b) { return b.name === n && b.value === v; })[0] || null;
      }
      if (jenis === 'cari' || jenis === 'daftar') {
        var w = pilihBernama(isi);
        return w && $(jenis === 'cari' ? '[data-cari-satker]' : '[data-buka-daftar-satker]', w);
      }
      return jenis === 'id' ? document.getElementById(isi) : null;
    }
    /* Tombol yang hilang bersama bagiannya (Hapus …): fokus pindah ke tab
       yang kini terpilih di deret yang sama, supaya pengguna papan ketik
       tidak kehilangan tempat. Keping satuan kerja yang baru dilepas kembali
       ke daftar pemilih yang tertutup: fokus ke kotak cari pemilih itu. */
    function gantiFokus(aksi, hilang) {
      var pemilih = hilang && hilang.closest('[data-pilih-cari]');
      if (pemilih) return $('[data-cari-satker]', pemilih);
      var v = aksi && aksi.value ? String(aksi.value).split(':') : [];
      if (v[0] === 'hapus-temuan') return $('[id^="fb-tab-tem-"][aria-selected="true"]', form);
      if (v[0] === 'hapus-rek') return $('#fb-tem-' + v[1] + ' [id^="fb-tab-rek-"][aria-selected="true"]', form);
      if (v[0] === 'hapus-tindakan') return $('#fb-rek-' + v[2] + ' [id^="fb-tab-tl-"][aria-selected="true"]', form);
      if (v[0] === 'hapus-dok') {
        var tl = document.getElementById('fb-tl-' + v[2] + '-' + v[3]);
        var sisa = tl ? $$('.fb-dok-brs input', tl) : [];
        return sisa[Math.min(+v[4] || 0, sisa.length - 1)] || (tl && $('.fb-dok > .fb-link', tl));
      }
      return null;
    }

    /* Tab yang dipilih selagi menunggu jawaban dipilih lagi di formulir baru. */
    function pasangUi(el) {
      var n = el.name, v = el.value, tab = null, m;
      if (n === 'ui[surat]') { bukaSurat(v === '1'); return; }
      if (n === 'ui[aktif]') tab = document.getElementById('fb-tab-tem-' + v);
      else if (n === 'ui[buka]') tab = v ? document.getElementById('fb-tab-rek-' + v) : null;
      else if ((m = /^ui\[tl\]\[(.+)\]$/.exec(n))) tab = document.getElementById('fb-tab-tl-' + m[1] + '-' + v);
      if (tab && form.contains(tab) && !tab.classList.contains('aktif')) pilihTab(tab);
    }
    /* Yang diubah selagi menunggu jawaban dipasang lagi. Kalau di antaranya
       ada pilihan yang perlu server, formulirnya dikirim sekali lagi. */
    function pasangLagi(berubah, kini, ketik) {
      var peta = petaIsian(), perlu = false;
      berubah.forEach(function (k) {
        var el = peta[k];
        if (!el) return;
        if (el === ketik) {
          /* Isian yang sedang diketik sudah membawa nilainya sendiri. */
          if (el.matches('[data-kirim]')) perlu = true;
          return;
        }
        if (el.type === 'checkbox' || el.type === 'radio') el.checked = kini[k];
        else el.value = kini[k];
        if (el.matches('[data-kirim]')) perlu = true;
        if (el.matches('[data-ikut-judul]')) aturJudul(el);
        if (el.type === 'hidden' && el.name.indexOf('ui[') === 0) pasangUi(el);
      });
      return perlu;
    }

    /* Pesan di atas halaman (mis. "Surat laporan belum lengkap") mengikuti
       jawaban terakhir, sama seperti sesudah halaman dimuat ulang. */
    function gantiPesan(baru) {
      var lama = $('.pesanbingkai');
      if (baru) baru = document.importNode(baru, true);
      if (lama && baru) lama.parentNode.replaceChild(baru, lama);
      else if (lama) lama.parentNode.removeChild(lama);
      else if (baru) { var w = form.closest('.body') || form; w.parentNode.insertBefore(baru, w); }
    }

    function tukar(baru, dok, potret, aksi) {
      var langkahLama = form.dataset.langkah;
      var kini = potretIsian();
      var berubah = Object.keys(kini).filter(function (k) { return k in potret && kini[k] !== potret[k]; });
      var aktif = document.activeElement, fokus = null;
      if (aktif && aktif !== form && form.contains(aktif)) {
        fokus = { kunci: kunciFokus(aktif), awal: null, akhir: null, gulir: aktif.scrollTop || 0 };
        try {
          if (typeof aktif.selectionStart === 'number') { fokus.awal = aktif.selectionStart; fokus.akhir = aktif.selectionEnd; }
        } catch (e) { /* isian tanpa kursor teks, mis. tanggal */ }
      }
      var pilih = keadaanPilih(), y = window.scrollY;
      /* Isian teks atau tanggal yang sedang diketik tidak ikut ditukar:
         unsurnya sendiri dipindah ke formulir yang baru dengan atribut dari
         jawaban, jadi kursor, bagian tanggal yang sedang diisi, dan riwayat
         Ctrl+Z-nya tetap. */
      var ketik = fokus && fokus.kunci && fokus.kunci.indexOf('isian:') === 0
        && aktif.matches('textarea, input:not([type=checkbox]):not([type=radio]):not([type=hidden])') ? aktif : null;

      menukar = true;
      /* Atribut formulirnya (langkah, fokus, gulir) mengikuti jawaban. */
      Array.prototype.slice.call(form.attributes).forEach(function (a) {
        if (!baru.hasAttribute(a.name) && a.name !== 'aria-busy') form.removeAttribute(a.name);
      });
      Array.prototype.slice.call(baru.attributes).forEach(function (a) { form.setAttribute(a.name, a.value); });
      form.innerHTML = baru.innerHTML;
      if (ketik) {
        var ganti = cariFokus(fokus.kunci);
        if (ganti && ganti.tagName === ketik.tagName && ganti.type === ketik.type) {
          Array.prototype.slice.call(ketik.attributes).forEach(function (a) {
            if (!ganti.hasAttribute(a.name)) ketik.removeAttribute(a.name);
          });
          Array.prototype.slice.call(ganti.attributes).forEach(function (a) {
            if (a.name !== 'value') ketik.setAttribute(a.name, a.value);
          });
          /* Yang tidak diubah selagi menunggu memakai nilai dari server —
             mis. rencana aksi yang baru terisi dari tanggal diterima. */
          if (berubah.indexOf(fokus.kunci.slice(6)) < 0 && ketik.value !== ganti.value) ketik.value = ganti.value;
          ganti.parentNode.replaceChild(ketik, ganti);
        } else {
          ketik = null;
        }
      }
      $$('[data-tanpa-js]', form).forEach(function (x) { x.hidden = true; });
      gantiPesan(dok.querySelector('.pesanbingkai'));

      var perlu = pasangLagi(berubah, kini, ketik);
      pulihkanPilih(pilih);
      if (berubah.length) {
        $$('[data-tindak]', form).forEach(function (t) { aturNilai(t); aturPenerima(t); });
        periksa();
      }

      /* Pindah langkah dan formulir yang dikosongkan mulai dari atas, seperti
         halaman baru; selebihnya layar tidak bergeser sedikit pun. */
      var dariAtas = form.dataset.langkah !== langkahLama || (aksi && aksi.value === 'kosongkan');
      window.scrollTo(0, dariAtas ? 0 : y);
      if (fokus) {
        var el = cariFokus(fokus.kunci);
        if (!el || !tampak(el) || el.disabled) el = gantiFokus(aksi, el);
        if (el) {
          el.focus({ preventScroll: true });
          if (fokus.awal != null && el.setSelectionRange) {
            try { el.setSelectionRange(fokus.awal, fokus.akhir); } catch (e) { /* bukan isian teks */ }
          }
          if (fokus.gulir) el.scrollTop = fokus.gulir;
        }
      }
      menukar = false;
      var sasaran = form.dataset.gulir ? document.getElementById(form.dataset.gulir) : null;
      arahkan(sasaran && form.contains(sasaran) ? sasaran : null);
      /* Tanggal yang masih diketik menunggu ditinggalkan dulu, seperti biasa. */
      if (perlu) kirimNanti(ketik && ketik.matches('[data-kirim]') ? ketik : null);
    }

    function kirimLatar(aksi) {
      clearTimeout(jedaKirim);
      jedaKirim = null;
      tanggalTertunda = null;
      if (pergi) return;
      if (!window.fetch || !window.DOMParser) { pergi = true; form.submit(); return; }
      if (kiriman) {
        /* Tombol yang tertekan dua kali tidak dikerjakan dua kali. Tombol
           menang atas kiriman isian saja — ia pun membawa seluruh isian. */
        var sama = aksi && kiriman.aksi && aksi.name === kiriman.aksi.name && aksi.value === kiriman.aksi.value;
        if (!sama && (aksi || antre === undefined)) antre = aksi;
        return;
      }
      var data = new FormData(form);
      if (aksi && aksi.name) data.append(aksi.name, aksi.value);
      var potret = potretIsian(), tiba = false;
      kiriman = { aksi: aksi };
      form.setAttribute('aria-busy', 'true');
      fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } })
        .then(function (r) {
          tiba = true;
          /* Yang bukan formulir ini — sesi berakhir, server bermasalah —
             dibuka sebagai halaman biasa, seperti tanpa skrip. */
          if (!r.ok || new URL(r.url, location.href).pathname !== new URL(form.action, location.href).pathname) {
            pergi = true;
            location.href = r.ok ? r.url : location.pathname;
            return null;
          }
          return r.text();
        })
        .then(function (teks) {
          if (teks == null || pergi) return;
          var dok = new DOMParser().parseFromString(teks, 'text/html');
          var baru = dok.querySelector('form[data-form-baru]');
          if (!baru) { pergi = true; location.href = location.pathname; return; }
          tukar(baru, dok, potret, aksi);
        })
        .catch(function () {
          /* Jawabannya sudah tiba (drafnya tersimpan) tapi tidak bisa
             dipasang: tampilkan keadaan tersimpannya. Belum tiba sama sekali:
             isiannya tetap di layar dan bisa dicoba lagi. */
          if (tiba) { pergi = true; location.href = location.pathname; return; }
          antre = undefined;
          kabarTampilan('Tidak tersambung ke server, jadi perubahan terakhir belum tersimpan. Periksa sambungan, lalu coba lagi.', true);
        })
        .then(function () {
          kiriman = null;
          if (pergi) return;
          form.removeAttribute('aria-busy');
          if (antre !== undefined) { var a = antre; antre = undefined; kirimLatar(a); }
        });
    }

    /* Centang dan pilihan yang berturut-turut cukup dikirim sekali. Tanggal
       baru dikirim sesudah isiannya ditinggalkan: tanggal yang diketik
       berganti di tiap angka, dan menukar isian yang sedang diketik membuang
       tempat kursornya. */
    function kirimNanti(el) {
      clearTimeout(jedaKirim);
      if (el && el.type === 'date' && document.activeElement === el) { tanggalTertunda = el; return; }
      jedaKirim = setTimeout(function () { kirimLatar(null); }, 300);
    }
    form.addEventListener('focusout', function (e) {
      /* Isian yang sesaat terlepas selagi isi formulir ditukar tidak
         dihitung ditinggalkan. */
      if (menukar || !tanggalTertunda || e.target !== tanggalTertunda) return;
      tanggalTertunda = null;
      /* Jeda sebentar: kalau yang ditekan sesudahnya tombol, kirimannya
         sudah membawa tanggal ini. */
      clearTimeout(jedaKirim);
      jedaKirim = setTimeout(function () { kirimLatar(null); }, 250);
    });

    form.addEventListener('submit', function (e) {
      var t = e.submitter;
      var aksi = t && t.name ? { name: t.name, value: t.value } : null;
      if (!window.fetch || !window.DOMParser || (aksi && PINDAH_HALAMAN.indexOf(aksi.value) >= 0)) {
        /* Berpindah halaman: kiriman yang masih tertunda tidak perlu lagi. */
        clearTimeout(jedaKirim);
        pergi = true;
        return;
      }
      e.preventDefault();
      kirimLatar(aksi);
    });
  }

  /* ================= penanda saat mendarat ================= */
  /* `?sorot=blok` atau jangkar #blok: bagian yang dituju digulir ke bawah
     batang atas dan dibingkai sekejap, supaya mata tahu bagian mana yang
     dimaksud di antara sekian kartu yang bentuknya mirip. */
  /* Dua alamat lama tidak punya tempatnya sendiri lagi: kartu Urusan SIPTL
     lebur ke tabel tindak lanjut (17 Sep), jadi pemberitahuan yang menunjuknya diantar
     ke tabel itu. */
  var ALIAS_BAGIAN = { 'r-siptl': 'r-tindaklanjut', 'r-bpk': 'r-tindaklanjut',
    'r-riwayat': 'r-tindaklanjut', 'r-perkembangan': 'r-tindaklanjut' };

  /* Pemberitahuan tentang satu satuan kerja membuka tiket dan baris satuan kerja itu.
     Pemberitahuan yang dulu menunjuk kartu riwayat membuka tab Riwayat baris itu —
     kartunya jadi tab di baris tabel tindak lanjut (21 Sep). */
  function bukaBarisSatker(satker, tindakan, tab) {
    var baris = $$('tr[data-baris-tl]').filter(function (b) {
      return b.dataset.satker === satker && (!tindakan || b.dataset.tindakan === tindakan);
    })[0];
    if (!baris) return null;
    var tombol = $('[data-buka-tiket]', baris.closest('[data-tiket]'));
    if (tombol && tombol.getAttribute('aria-expanded') !== 'true') tombol.click();
    if (!baris.classList.contains('buka')) bukaBarisTl(baris, tab || 'bukti');
    else if (tab) gantiTabTl(baris, tab);
    return baris;
  }

  function pasangSorot() {
    var p = new URLSearchParams(location.search);
    var asal = p.get('sorot') || (location.hash ? location.hash.slice(1) : '');
    if (!asal) return;
    var id = ALIAS_BAGIAN[asal] || asal;
    var el = document.getElementById(id);
    if (!el) return;
    /* Formulir Catat laporan baru menggulir bagiannya sendiri (gulirKe),
       tanpa bingkai sorot — seperti di prototipe. Jendela (#arsip, #panduan)
       bukan bagian halaman yang bisa digulir. */
    if (el.closest('[data-form-baru]') || el.classList.contains('tirai')) return;
    /* Bagian yang ditunjuk kerap sedang terlipat: dibuka dulu. */
    if (el.matches('[data-lipat]')) aturLipat(el, true);
    if (id === 'r-kepala') aturBaca($('[data-baca]', el), true);
    if (id === 'r-tindaklanjut' && p.get('satker')) {
      var baris = bukaBarisSatker(p.get('satker'), p.get('tindakan'),
        p.get('tab') || (asal === 'r-riwayat' || asal === 'r-perkembangan' ? 'riwayat' : 'bukti'));
      /* Yang disorot barisnya sendiri, bukan seluruh tabel tindak lanjut
         (26 Sep) — padanan `cariTepat` di useSorotBagian prototipe. */
      if (baris) { antarKe(null, function () { return [baris]; }, 240); return; }
    }
    var nada = p.get('nada') || '';
    var kartu = p.get('kartu') ? document.getElementById(p.get('kartu')) : null;
    var sasaran = kartu || el;
    setTimeout(function () {
      var tujuan = Math.max(0, window.scrollY + sasaran.getBoundingClientRect().top - jarakLekat(sasaran === el ? 16 : 78));
      window.scrollTo({ top: tujuan, behavior: 'smooth' });
    }, 260);
    sasaran.classList.add('sorot');
    if (nada) sasaran.classList.add(nada);
    setTimeout(function () { sasaran.classList.remove('sorot'); if (nada) sasaran.classList.remove(nada); }, 4000);
  }

  /* ================= pintasan di halaman (26 Sep) ================= */
  /* Padanan pintasan prototipe. Rincian laporan: nama satuan kerja membuka
     blok temuannya lalu menyorot baris rekomendasi satuan kerja itu; "N
     temuan" menyorot blok-blok temuannya; "N rekomendasi" membuka semua blok
     lalu menyorot seluruh barisnya. Titipan lewat alamat: ?temuan=&rek= di
     rincian laporan ("Lihat laporan lengkap", "Kembali", pencarian), ?tuju=
     di daftar Rekomendasi dan Daftar laporan ("Kembali", sesudah tindakan). */
  function pasangPintasan() {
    function blokTemuan(uji) { return $$('.body .temblok').filter(uji); }
    function kepBlok(b) { return $(':scope > .kep', b); }
    function menyebut(el, x) { return (el.getAttribute('data-satker') || '').split('|').indexOf(String(x)) >= 0; }
    function semua() { return true; }
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-ke-satker], [data-ke-temuan], [data-ke-rek]');
      if (!t) return;
      e.preventDefault();
      if (t.hasAttribute('data-ke-satker')) {
        var x = t.getAttribute('data-ke-satker');
        antarKe(function () { return bukaLewat(blokTemuan(function (b) { return menyebut(b, x); }).map(kepBlok)); },
          function () {
            var baris = $$('.body .temblok tr[data-rek]').filter(function (tr) { return menyebut(tr, x); });
            return baris.length ? baris : blokTemuan(function (b) { return menyebut(b, x); });
          });
      } else if (t.hasAttribute('data-ke-temuan')) {
        antarKe(null, function () { return blokTemuan(semua); });
      } else {
        antarKe(function () { return bukaLewat(blokTemuan(semua).map(kepBlok)); },
          function () { return $$('.body .temblok tr[data-rek]'); });
      }
    });

    var p = new URLSearchParams(location.search);
    var temuan = p.get('temuan'), rek = p.get('rek'), tuju = p.get('tuju');
    if ((temuan || rek) && $('.body .temblok')) {
      if (!temuan && rek) {
        var baris0 = $('.body tr[data-rek="' + rek + '"]');
        var blok0 = baris0 && baris0.closest('.temblok');
        temuan = blok0 ? blok0.getAttribute('data-temuan') : null;
      }
      var blok = function () { return blokTemuan(function (b) { return b.getAttribute('data-temuan') === temuan; }); };
      antarKe(function () { return bukaLewat(blok().map(kepBlok)); }, function () {
        var baris = rek ? $$('.body tr[data-rek="' + rek + '"]') : [];
        return baris.length ? baris : blok();
      }, 360);
    }
    if (tuju) {
      antarKe(null, function () {
        return $$('.daftar-rek tr[data-rek="' + tuju + '"], .daftar-lap tr[data-lap="' + tuju + '"]');
      }, 380);
    }
  }

  /* ================= halaman rincian ================= */

  function gulirDanSorot(el, nada) {
    if (!el) return;
    var tujuan = Math.max(0, window.scrollY + el.getBoundingClientRect().top - jarakLekat(16));
    window.scrollTo({ top: tujuan, behavior: 'smooth' });
    el.classList.add('sorot');
    if (nada) el.classList.add(nada);
    setTimeout(function () { el.classList.remove('sorot'); if (nada) el.classList.remove(nada); }, 4200);
  }

  /* Tiket tiap bentuk tindak lanjut: satu terbuka pada satu waktu, dan tidak
     ada yang terbuka sejak awal. */
  function pasangTiket() {
    var semua = $$('[data-tiket]');
    function atur(t, buka) {
      var tombol = $('[data-buka-tiket]', t);
      $('[data-isi-tiket]', t).hidden = !buka;
      tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
      t.classList.toggle('tiket-terbuka', buka);
      $('[data-ajak-buka]', t).hidden = buka;
      $('[data-ajak-tutup]', t).hidden = !buka;
      if (buka) {
        var tabel = $('[data-tabel-tl]', t);
        if (tabel && tabel.dataset.satu === '1') {
          var baris = $('tr[data-baris-tl]', tabel);
          if (baris && !baris.classList.contains('buka')) bukaBarisTl(baris, 'bukti');
        }
      }
    }
    semua.forEach(function (t) { atur(t, false); });
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('[data-buka-tiket]');
      if (!tombol) return;
      var t = tombol.closest('[data-tiket]');
      var buka = tombol.getAttribute('aria-expanded') !== 'true';
      semua.forEach(function (lain) { if (lain !== t) atur(lain, false); });
      atur(t, buka);
    });
  }

  function detailTl(baris) { return document.querySelector('tr[data-rinci-tl="' + baris.dataset.barisTl + '"]'); }

  function aturTombolTl(baris, buka, tab) {
    var adaAksi = baris.dataset.adaAksi === '1';
    var tutupKah = buka && (!adaAksi || tab === 'kerja');
    var k = $('[data-kerjakan]', baris);
    $('[data-label-tutup]', k).hidden = !tutupKah;
    $('[data-label-buka]', k).hidden = tutupKah;
    k.setAttribute('aria-expanded', buka ? 'true' : 'false');
    $('[data-buka-baris]', baris).setAttribute('aria-expanded', buka ? 'true' : 'false');
  }

  function gantiTabTl(baris, tab) {
    var d = detailTl(baris);
    baris.dataset.tab = tab;
    $$('[data-tab-tl]', d).forEach(function (b) { b.setAttribute('aria-selected', b.dataset.tabTl === tab ? 'true' : 'false'); });
    $$('[data-panel-tl]', d).forEach(function (p) { p.hidden = p.dataset.panelTl !== tab; });
    aturTombolTl(baris, true, tab);
  }

  function tutupBarisTl(baris) {
    baris.classList.remove('buka');
    detailTl(baris).hidden = true;
    aturTombolTl(baris, false, baris.dataset.tab || 'bukti');
  }

  function bukaBarisTl(baris, tab) {
    var tabel = baris.closest('table');
    $$('tr[data-baris-tl]', tabel).forEach(function (lain) { if (lain !== baris && lain.classList.contains('buka')) tutupBarisTl(lain); });
    baris.classList.add('buka');
    detailTl(baris).hidden = false;
    gantiTabTl(baris, tab);
  }

  function pasangTabelTl() {
    $$('tr[data-baris-tl]').forEach(function (baris) { tutupBarisTl(baris); });
    document.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-tab-tl]');
      if (tab) {
        var d = tab.closest('tr[data-rinci-tl]');
        var baris = document.querySelector('tr[data-baris-tl="' + d.dataset.rinciTl + '"]');
        gantiTabTl(baris, tab.dataset.tabTl);
        return;
      }
      /* Kaki rincian (27 Sep): tutup, lalu fokus kembali ke nama barisnya —
         barisnya tetap di depan mata, bukan tertinggal di bawah. */
      var tutupRinci = e.target.closest('[data-tutup-rinci]');
      if (tutupRinci) {
        var dr = tutupRinci.closest('tr[data-rinci-tl]');
        var br = document.querySelector('tr[data-baris-tl="' + dr.dataset.rinciTl + '"]');
        tutupBarisTl(br);
        $('[data-buka-baris]', br).focus();
        return;
      }
      var kerjakan = e.target.closest('[data-kerjakan]');
      if (kerjakan) {
        var b = kerjakan.closest('tr[data-baris-tl]');
        var adaAksi = b.dataset.adaAksi === '1';
        var terbuka = b.classList.contains('buka');
        if (terbuka && (!adaAksi || b.dataset.tab === 'kerja')) tutupBarisTl(b);
        else bukaBarisTl(b, adaAksi ? 'kerja' : 'bukti');
        return;
      }
      var baris2 = e.target.closest('tr[data-baris-tl]');
      if (baris2 && (e.target.closest('[data-buka-baris]') || !e.target.closest('a, button, input, select, textarea, label'))) {
        if (baris2.classList.contains('buka')) tutupBarisTl(baris2); else bukaBarisTl(baris2, 'bukti');
        return;
      }
      var tunjuk = e.target.closest('[data-tunjuk]');
      if (tunjuk) {
        e.preventDefault();
        gulirDanSorot(document.getElementById(tunjuk.dataset.tunjuk), '');
      }
    });
  }

  /* Pemilih periode di tab Riwayat (26 Sep) — padanan `RiwayatBaris`: satu
     periode tampil sekaligus, yang sedang berjalan lebih dulu. Panah kiri dan
     kanan, Home, dan End berpindah di dalam deret tab mana pun (periode, dan
     Bukti · Kerjakan · Riwayat). */
  function pasangPeriodeRiwayat() {
    function pilihPeriode(tombol) {
      var deret = tombol.closest('[data-pilih-periode]');
      var kartu = deret.closest('.rw-riwayat');
      var k = tombol.dataset.periode;
      $$('[data-periode]', deret).forEach(function (b) {
        var ini = b === tombol;
        b.setAttribute('aria-selected', ini ? 'true' : 'false');
        b.tabIndex = ini ? 0 : -1;
      });
      $$('[data-isi-periode]', kartu).forEach(function (isi) { isi.hidden = isi.dataset.isiPeriode !== k; });
    }
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-pilih-periode] [data-periode]');
      if (t) pilihPeriode(t);
    });
    document.addEventListener('keydown', function (e) {
      if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(e.key) < 0) return;
      var t = e.target.closest('[role="tablist"] > [role="tab"]');
      if (!t) return;
      var semua = $$('[role="tab"]', t.parentElement);
      var i = semua.indexOf(t);
      var ke = e.key === 'Home' ? 0 : e.key === 'End' ? semua.length - 1
        : (i + (e.key === 'ArrowRight' ? 1 : -1) + semua.length) % semua.length;
      e.preventDefault();
      semua[ke].focus();
      semua[ke].click();
    });
  }

  /* Di sini dulu berdiri `pilihRiwayat` dan `pasangRiwayat`: penukar kartu
     riwayat menurut baris yang dibuka dan tombol pemilihnya. Sejak 21 Sep
     riwayatnya tab "Riwayat" di tiap baris, jadi cukup `gantiTabTl`. */

  /* Di sini dulu berdiri `aktifkanTab` dan `pasangTabRincian`: tab Arsip
     rekomendasi dan Riwayat aktivitas. Keduanya jadi kartu berlipat 17 Sep. */

  /* Keterangan di bilah tombol panel kerja — padanan `ket` dan `lanjut` pada
     PanelKerja (25 Sep): segitiga kuning untuk yang masih kurang, panah untuk
     yang terjadi sesudah tombolnya ditekan. Tanpa keduanya kalimatnya
     disembunyikan. */
  function aturKetKerja(wadah, ket, lanjut) {
    var el = $('[data-ket-kerja]', wadah);
    if (!el) return;
    var teks = ket || lanjut || '';
    el.hidden = !teks;
    el.className = 'kerja-ket' + (ket ? '' : ' lanjut');
    el.innerHTML = ikon(ket ? 'awas' : 'panah', 15) + '<span>' + esc(teks) + '</span>';
  }

  /* Atribut `hidden` untuk unsur HTML maupun ikon SVG — unsur SVG tidak punya
     sifat `.hidden`, jadi atributnya dipasang langsung. */
  function tampilkan(el, ya) { if (el) el.toggleAttribute('hidden', !ya); }

  /* Isian yang boleh dikosongkan tidak ikut memenuhi panel sejak awal —
     padanan `Opsional` (25 Sep): satu link di lajur isian, isinya baris
     biasa tepat di bawahnya. Yang tertutup menyebut isinya: berapa isian, atau
     bahwa ada yang sudah terisi. */
  function aturOpsional(baris) {
    var tombol = $('[data-buka-opsional]', baris), isi = baris.nextElementSibling;
    var buka = tombol.getAttribute('aria-expanded') === 'true';
    isi.hidden = !buka;
    tampilkan($('[data-ikon-tutup]', tombol), !buka);
    tampilkan($('[data-ikon-buka]', tombol), buka);
    var ket = $('[data-opsional-ket]', baris);
    if (ket) {
      var terisi = $$('input, textarea, select', isi).some(function (i) { return String(i.value).trim(); });
      ket.hidden = buka;
      ket.textContent = terisi ? 'sudah terisi' : ket.dataset.jumlah + ' isian';
    }
  }
  function pasangOpsional() {
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('[data-buka-opsional]');
      if (!tombol) return;
      tombol.setAttribute('aria-expanded', tombol.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
      aturOpsional(tombol.closest('[data-opsional]'));
    });
    document.addEventListener('input', function (e) {
      var f = e.target.closest('form');
      if (f) $$('[data-opsional]', f).forEach(aturOpsional);
    });
  }

  /* Daftar isian satu baris per butir. Nomor pada nama isian dan tombol
     hapusnya ditulis ulang tiap kali barisnya bertambah atau berkurang; tombol
     hapus baru ada kalau barisnya lebih dari satu. */
  function aturDaftarIsian(d) {
    var baris = $$('[data-baris-isian]', d), sebut = d.dataset.sebut || 'dokumen';
    var Sebut = sebut.charAt(0).toUpperCase() + sebut.slice(1);
    baris.forEach(function (b, i) {
      $('input', b).setAttribute('aria-label', Sebut + ' ' + (i + 1));
      var h = $('[data-hapus-isian]', b);
      h.setAttribute('aria-label', 'Hapus ' + sebut + ' ' + (i + 1));
      h.hidden = baris.length < 2;
    });
  }
  function pasangDaftarIsian() {
    document.addEventListener('click', function (e) {
      var tambah = e.target.closest('[data-tambah-isian]');
      if (tambah) {
        var d = tambah.closest('[data-daftar-isian]');
        var baru = $('[data-baris-isian]', d).cloneNode(true);
        $('input', baru).value = '';
        d.insertBefore(baru, tambah);
        aturDaftarIsian(d);
        $('input', baru).focus();
        d.dispatchEvent(new Event('input', { bubbles: true }));
        return;
      }
      var hapus = e.target.closest('[data-hapus-isian]');
      if (hapus) {
        var d2 = hapus.closest('[data-daftar-isian]');
        hapus.closest('[data-baris-isian]').remove();
        aturDaftarIsian(d2);
        $('input', d2).focus();
        d2.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  }

  /* Penghitung huruf isian yang dibatasi 500 huruf. */
  function hitungHuruf(el) {
    var n = el.parentElement.querySelector('[data-jumlah-huruf]');
    if (n) n.textContent = el.value.length + '/500';
  }

  /* Pekerjaan SIPTL satu baris, di tab Kerjakan-nya — padanan PanelSiptlBaris.
     Dulu kotak sunting di bawah tabel kartu Urusan SIPTL, dibuka lewat menu
     Aksi (`pasangMenuAksi`, `pasangSuntingSiptl`); kartunya lebur ke tabel
     tindak lanjut 17 Sep, dan jadi panel kerja 25 Sep. */
  function pasangPanelSiptl() {
    var INFO_CATATAN = {
      BS: 'Kalimat ini yang menjelaskan kenapa berkasnya dikembalikan.',
      SS: 'Salin apa yang tertulis di SIPTL untuk satuan kerja ini.',
    };
    function aturSiptl(f) {
      var hari = f.dataset.hari, naik = f.dataset.siapNaik === '1';
      var tombol = $('[data-simpan-siptl]', f), satker = f.dataset.satker, pesan, lanjut;
      if (naik) {
        var tg = $('[data-tanggal-unggah]', f).value;
        pesan = !tg ? 'Tanggal unggah harus terisi' : (tg > hari ? 'Tanggal unggah tidak boleh melewati hari ini' : '');
        lanjut = 'Statusnya jadi Belum Ditindaklanjuti sampai BPK memutus.';
        tombol.dataset.pastikan = JSON.stringify({
          judul: 'Catat unggahan ' + satker + ' ke SIPTL tanggal ' + tglId(tg) + '?',
          ket: 'Tanggal ini dikunci begitu dicatat dan tidak bisa diubah lagi — pastikan sama dengan tanggal unggah di SIPTL. Statusnya otomatis Belum Ditindaklanjuti sampai BPK memutus.',
          tombol: 'Ya, catat unggahannya', nada: 'hijau' });
      } else {
        var pilih = $('[data-status-pilih]', f).value;
        var catatanEl = $('[data-catatan-bpk]', f), catatan = catatanEl.value.trim();
        $$('[data-bila-pilih]', f).forEach(function (x) { x.hidden = !pilih; });
        $('[data-akibat-bs]', f).hidden = pilih !== 'BS';
        $$('[data-kartu-status]', f).forEach(function (k) { k.setAttribute('aria-pressed', k.dataset.kartuStatus === pilih ? 'true' : 'false'); });
        var grup = $('[data-wajib]', f);
        if (grup) grup.dataset.wajib = pilih ? 'isi' : 'kosong';
        /* Catatannya wajib kalau Belum Sesuai — bintang, keterangan, dan
           tanda wajibnya ikut putusan yang dipilih. */
        var baris = catatanEl.closest('.fb-brs');
        tampilkan($('[data-bintang-bila]', baris), pilih === 'BS');
        var info = $('.fb-lbl .info', baris), teksInfo = INFO_CATATAN[pilih === 'BS' ? 'BS' : 'SS'];
        if (info) { $('button', info).title = teksInfo; $('.isi', info).textContent = teksInfo; }
        if (pilih === 'BS') catatanEl.setAttribute('aria-required', 'true'); else catatanEl.removeAttribute('aria-required');
        pesan = !pilih ? 'Pilih putusan BPK. Kalau BPK belum memutus, biarkan dulu.' : (pilih === 'BS' && !catatan ? 'Catatan BPK harus terisi' : '');
        lanjut = pilih === 'SS' ? 'Tindak lanjut ini berhenti dipantau.' : 'Sesudah ini, berkasnya dikirim ulang ke satuan kerja.';
        tombol.dataset.pastikan = JSON.stringify({
          judul: 'Catat status ' + pilih + ' untuk ' + satker + '?',
          ket: pilih === 'SS'
            ? 'Putusan ini dikunci begitu dicatat. Tindak lanjut satuan kerja ini berhenti dipantau, dan rekomendasinya tertutup kalau seluruh satuan kerjanya sudah begitu.'
            : 'Putusan ini dikunci begitu dicatat. Supaya bisa dinilai lagi, berkasnya dikirim ulang ke satuan kerja lalu diunggah ulang ke SIPTL.',
          tombol: 'Ya, catat ' + pilih, nada: pilih === 'SS' ? 'hijau' : 'jingga' });
      }
      tombol.disabled = !!pesan;
      aturKetKerja(f, pesan, lanjut);
    }
    function aturUlangBpk(f) {
      var kosong = !$('[data-alasan-bpk]', f).value.trim();
      var tombol = $('[data-kirim-ulang-bpk]', f);
      tombol.disabled = kosong;
      aturKetKerja(f, kosong ? 'Catatan untuk satuan kerja harus terisi' : '', f.dataset.lanjut);
      /* Daftar dokumen ikut disebut di kotak pemastiannya, sama dengan
         pengiriman ulang atas penolakan UKI dan Inspektorat. */
      var dok = $$('[data-daftar-isian] input', f).map(function (i) { return i.value.trim(); }).filter(Boolean);
      var isi = JSON.parse(tombol.dataset.pastikanDasar || tombol.dataset.pastikan);
      if (!tombol.dataset.pastikanDasar) tombol.dataset.pastikanDasar = tombol.dataset.pastikan;
      if (dok.length) {
        isi.ket += ' Satuan kerja wajib melampirkan ' + dok.length + ' dokumen sebelum bisa mengirim lagi.';
        isi.rincian = '<ul class="ceklis">' + dok.map(function (d) { return '<li><span>' + esc(d) + '</span></li>'; }).join('') + '</ul>';
      }
      tombol.dataset.pastikan = JSON.stringify(isi);
    }
    $$('[data-form-siptl]').forEach(aturSiptl);
    $$('[data-form-ulang-bpk]').forEach(aturUlangBpk);
    document.addEventListener('click', function (e) {
      var kartu = e.target.closest('[data-kartu-status]');
      if (kartu) {
        var f = kartu.closest('form'), input = $('[data-status-pilih]', f);
        /* Menekan yang sudah terpilih melepasnya lagi. */
        input.value = input.value === kartu.dataset.kartuStatus ? '' : kartu.dataset.kartuStatus;
        aturSiptl(f);
      }
    });
    document.addEventListener('input', function (e) {
      if (e.target.matches('[data-hitung-huruf]')) hitungHuruf(e.target);
      var f = e.target.closest('[data-form-siptl]');
      if (f) aturSiptl(f);
      var g = e.target.closest('[data-form-ulang-bpk]');
      if (g) aturUlangBpk(g);
    });
  }

  var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  function tglId(s) {
    if (!s) return '—';
    var p = s.split('-');
    return p[2] + ' ' + BULAN[parseInt(p[1], 10) - 1] + ' ' + p[0];
  }
  function angka(v) { return parseInt(String(v == null ? '' : v).replace(/\D/g, ''), 10) || 0; }
  function rp(n) { n = angka(n); return n ? 'Rp ' + n.toLocaleString('id-ID') : '—'; }

  /* Putusan UKI atau Inspektorat — padanan PanelPeriksa. Isiannya baru
     muncul sesudah putusannya dipilih, dan bintangnya ikut putusan itu:
     memadai berdiri di atas surat, belum memadai di atas catatan. */
  function pasangPanelPeriksa() {
    function atur(f) {
      var mode = $('[data-mode]', f).value, uki = f.dataset.uki === '1';
      var kataM = f.dataset.kataM, kataBM = f.dataset.kataBm, lainBelum = parseInt(f.dataset.lainBelum, 10);
      var tolak = mode === 'BM', itjenTolak = !uki && tolak;
      $$('[data-pilih-mode]', f).forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.pilihMode === mode ? 'true' : 'false'); });
      var grup = $('[data-wajib]', f);
      if (grup) grup.dataset.wajib = mode ? 'isi' : 'kosong';
      $('[data-mode-isi]', f).hidden = !mode;
      $('[data-aksi-kerja]', f).hidden = !mode;
      $$('[data-hanya-m]', f).forEach(function (x) { x.hidden = mode !== 'M'; });
      $$('[data-hanya-bm]', f).forEach(function (x) { x.hidden = !tolak; });
      if (!mode) { aturKetKerja(f, '', ''); return; }
      $$('[data-bintang-bila]', f).forEach(function (x) { tampilkan(x, x.dataset.bintangBila === mode); });
      $$('[data-info-bila]', f).forEach(function (x) { x.hidden = x.dataset.infoBila !== mode; });
      $$('[data-wajib-bila]', f).forEach(function (x) {
        if (x.dataset.wajibBila === mode) x.setAttribute('aria-required', 'true'); else x.removeAttribute('aria-required');
      });
      var catatanEl = $('[data-f="catatan"]', f);
      catatanEl.placeholder = tolak ? catatanEl.dataset.contohBm : catatanEl.dataset.contohM;

      var nomor = $('[data-f="nomor"]', f).value.trim(), tgl = $('[data-f="tgl_surat"]', f).value;
      var catatan = catatanEl.value.trim();
      var batasEl = $('[data-f="batas_waktu"]', f), batas = batasEl ? batasEl.value : '';
      var siap = mode === 'M' ? (nomor.length > 0 && !!tgl) : (catatan.length >= 6 && (!itjenTolak || (!!batas && batas >= f.dataset.hari)));
      var sebutSurat = uki ? 'surat hasil validasi' : 'CHV';
      var tombol = $('[data-tetapkan]', f);
      tombol.className = 'btn ' + (mode === 'M' ? 'btn-ok' : 'btn-bad');
      tombol.disabled = !siap;
      tampilkan($('[data-ikon-m]', tombol), !tolak);
      tampilkan($('[data-ikon-bm]', tombol), tolak);
      var kata = (mode === 'M' ? kataM : kataBM).toLowerCase();
      $('[data-teks-tetapkan]', f).textContent = 'Tetapkan ' + kata;
      var kurang = siap ? '' : mode === 'M'
        ? 'Nomor dan tanggal ' + sebutSurat + ' harus terisi — putusan memadai berdiri di atas suratnya.'
        : (itjenTolak && catatan.length >= 6 ? 'Batas waktu perbaikan harus diisi, dan tidak boleh sebelum hari ini.'
          : 'Catatan harus diisi supaya satuan kerja tahu apa yang perlu diperbaiki.');
      /* Yang terjadi sesudah tombolnya ditekan — disebut sebelum ditekan. */
      var akibat = mode === 'M'
        ? (uki ? 'Berkas kembali ke Setba untuk diteruskan ke Inspektorat.'
          : lainBelum ? 'Tindak lanjut satuan kerja ini ditandai memadai dan berkasnya berhenti di sini.'
            : 'Rekomendasi dinyatakan selesai diverifikasi.')
        : 'Berkas kembali ke Setba, lalu Setba mengirimkannya ulang ke satuan kerja untuk pemberkasan ulang.';
      aturKetKerja(f, kurang, akibat);
      var dok = $$('[data-daftar-isian] input', f).map(function (i) { return i.value.trim(); }).filter(Boolean);
      tombol.dataset.pastikan = JSON.stringify({
        judul: 'Tetapkan ' + kata + '?',
        ket: mode === 'M'
          ? (uki ? 'Berkas kembali ke Setba untuk diteruskan ke Inspektorat.'
            : lainBelum ? 'Berkas satuan kerja ini keluar dari meja Inspektorat. Surat CHV-nya tercatat ' + kataBM.toLowerCase() + ', karena ' + lainBelum + ' satuan kerja lain belum.'
              : 'Berkas keluar dari meja Inspektorat dan rekomendasi dinyatakan selesai diverifikasi.')
          : 'Berkas kembali ke Setba bersama alasannya' + (dok.length ? ' dan ' + dok.length + ' dokumen yang diminta' : '')
            + '. Setba memeriksanya, lalu mengirimkannya ulang ke satuan kerja untuk pemberkasan ulang.',
        tombol: 'Ya, ' + kata,
        nada: mode === 'M' ? 'hijau' : 'merah' });
    }
    document.addEventListener('click', function (e) {
      var f = e.target.closest('[data-panel-periksa]');
      if (!f) return;
      var pilih = e.target.closest('[data-pilih-mode]');
      if (pilih) {
        /* Menekan yang sudah terpilih melepasnya lagi, seperti PilihPutusan. */
        var m = $('[data-mode]', f);
        m.value = m.value === pilih.dataset.pilihMode ? '' : pilih.dataset.pilihMode;
        atur(f);
        return;
      }
      if (e.target.closest('[data-batal-mode]')) {
        /* Kembali seperti belum disentuh — padanan `bersih` di prototipe. */
        f.reset();
        $('[data-mode]', f).value = '';
        $$('[data-daftar-isian]', f).forEach(function (d) {
          $$('[data-baris-isian]', d).slice(1).forEach(function (b) { b.remove(); });
          aturDaftarIsian(d);
        });
        $$('[data-opsional]', f).forEach(aturOpsional);
        atur(f);
        $('[data-pilih-mode]', f).focus();
      }
    });
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-panel-periksa]');
      if (f) atur(f);
    });
    $$('[data-panel-periksa]').forEach(atur);
  }

  /* Surat pengantar Setba: nomor, tanggal, dan perihal wajib. */
  function pasangFormSurat() {
    function atur(f) {
      var sah = ['nomor', 'tanggal', 'perihal'].every(function (k) { return $('[data-surat="' + k + '"]', f).value.trim(); });
      $('[data-butuh-surat]', f).disabled = !sah;
      aturKetKerja(f, sah ? '' : 'Nomor, tanggal, dan perihal surat harus terisi', f.dataset.lanjut);
      /* Tawaran surat terakhir cuma selama nomornya masih kosong. */
      var terakhir = $('[data-surat-terakhir]', f);
      if (terakhir) terakhir.hidden = !!$('[data-surat="nomor"]', f).value.trim();
    }
    document.addEventListener('click', function (e) {
      var pakai = e.target.closest('[data-pakai-surat]');
      if (!pakai) return;
      var f = pakai.closest('[data-form-surat]');
      var s = JSON.parse(pakai.closest('[data-surat-terakhir]').dataset.suratTerakhir);
      ['nomor', 'tanggal', 'perihal', 'link', 'catatan'].forEach(function (k) {
        var el = $('[data-surat="' + k + '"]', f);
        if (el && s[k]) el.value = s[k];
      });
      $$('[data-opsional]', f).forEach(aturOpsional);
      atur(f);
      $('[data-surat="nomor"]', f).focus();
    });
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-form-surat]');
      if (f) atur(f);
    });
    $$('[data-form-surat]').forEach(atur);
  }

  /* Meja pemberkasan ulang: kalimat penegasan menyebut dokumen yang diminta. */
  function pasangKirimUlang() {
    function atur(f) {
      var dok = $$('[data-daftar-isian] input', f).map(function (i) { return i.value.trim(); }).filter(Boolean);
      var ket = $('textarea[name="keterangan"]', f).value.trim();
      var tombol = $('[data-pastikan]', f);
      var isi = JSON.parse(tombol.dataset.pastikan);
      isi.ket = 'Berkas kembali ke meja ' + f.dataset.satker + ' untuk pemberkasan ulang, bersama alasan penolakan ' + f.dataset.dari
        + (f.dataset.batas ? ' dan batas waktunya (' + f.dataset.batas + ')' : '')
        + (ket ? ', ditambah keterangan Setba.' : '.')
        + (dok.length ? ' Satuan kerja wajib melampirkan ' + dok.length + ' dokumen sebelum bisa mengirim lagi.' : ' Tidak ada dokumen tertentu yang diminta.')
        + ' Satuan kerja lain pada rekomendasi ini tidak ikut.';
      isi.rincian = dok.length ? '<ul class="ceklis">' + dok.map(function (d) { return '<li><span>' + esc(d) + '</span></li>'; }).join('') + '</ul>' : '';
      tombol.dataset.pastikan = JSON.stringify(isi);
    }
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-form-kirim-ulang]');
      if (f) atur(f);
    });
    $$('[data-form-kirim-ulang]').forEach(atur);
  }

  /* Isian satuan kerja — padanan hitungan PanelBalai. Urutan kalimat bilah
     sama persis dengan urutan syarat tombol kirim. */
  function pasangPanelBalai() {
    function ntpnSah(v) { return /^[0-9A-Za-z]{16}$/.test(v || ''); }
    function nilaiSetor(el) {
      var o = {};
      $$('[data-f]', el).forEach(function (i) { o[i.dataset.f] = i.value; });
      return o;
    }
    function setorSah(x) {
      return !!x.tanggal && angka(x.nilai) > 0 && !!(x.berkas || '').trim() && !!(x.link || '').trim()
        && (x.jenis === 'perbaikan' ? !!(x.noBa || '').trim() : ntpnSah(x.ntpn));
    }
    function kalimatKurang(n) { return n + ' link belum lengkap — judul dan alamatnya harus diisi.'; }
    function atur(f) {
      var target = angka(f.dataset.target), masuk = angka(f.dataset.masuk);
      var uraian = $('[data-uraian]', f).value.trim();
      /* Dokumen yang diminta: butir terpenuhi kalau link-nya lengkap. Link
         yang belum lengkap disebut di baris tempat ia ditulis. */
      var butir = $$('[data-butir]', f), dipenuhi = 0, mintaKurang = 0, lepasKurang = 0, namaBukti = [];
      butir.forEach(function (li) {
        var ada = !$('[data-isi-butir]', li).hidden;
        var nama = $('[data-bukti-nama]', li).value.trim(), link = $('[data-bukti-link]', li).value.trim();
        var sah = ada && !!nama && !!link;
        if (ada && !sah) mintaKurang++;
        if (sah) dipenuhi++;
        if (ada) namaBukti.push(nama);
        li.classList.toggle('buka', ada);
        $('[data-tanda-butir]', li).classList.toggle('ok', sah);
        tampilkan($('[data-ikon-ada]', li), sah);
        tampilkan($('[data-ikon-belum]', li), !sah);
      });
      $$('[data-lepas]', f).forEach(function (b) {
        var nama = $('[data-bukti-nama]', b).value.trim(), link = $('[data-bukti-link]', b).value.trim();
        if (!(nama && link)) lepasKurang++;
        namaBukti.push(nama);
      });
      var buktiKurang = mintaKurang + lepasKurang;
      var sisaDok = butir.length - dipenuhi;
      var tandaDok = $('[data-sisa-dok]', f);
      if (tandaDok) {
        tandaDok.textContent = sisaDok > 0 ? sisaDok + ' dari ' + butir.length + ' belum dilampirkan'
          : butir.length + ' dari ' + butir.length + ' sudah dilampirkan';
      }
      var mk = $('[data-minta-kurang]', f);
      if (mk) { mk.hidden = !mintaKurang; mk.textContent = kalimatKurang(mintaKurang); }
      var lk = $('[data-lepas-kurang]', f);
      lk.hidden = !lepasKurang;
      lk.textContent = kalimatKurang(lepasKurang);

      /* Pemulihan nilai. */
      var baris = $$('[data-setor]', f), sahSetor = 0, belumSah = 0;
      var lama = angka(f.dataset.setoranLama), rencana = angka(f.dataset.angsurRencana);
      baris.forEach(function (el, i) {
        var x = nilaiSetor(el), tunai = x.jenis !== 'perbaikan';
        var sah = setorSah(x);
        if (sah) sahSetor += angka(x.nilai); else belumSah++;
        $('[data-judul-setor]', el).textContent = (rencana ? 'Angsuran' : 'Pemulihan') + ' ke-' + (lama + i + 1);
        $('[data-setor-belum]', el).hidden = sah;
        $('[data-hint-nilai]', el).innerHTML = x.nilai ? esc(rp(x.nilai)) : '&nbsp;';
        $('[data-label-tanggal]', el).textContent = tunai ? 'Tanggal setor' : 'Tanggal perbaikan selesai';
        $('[data-label-bukti]', el).textContent = tunai ? 'Bukti setor (SSBP)' : 'Berita acara perbaikan';
        var sebut = tunai ? 'bukti setor' : 'berita acara', judulBukti = $('[data-judul-bukti]', el);
        judulBukti.setAttribute('aria-label', 'Judul ' + sebut);
        judulBukti.placeholder = 'Judul, mis. ' + (tunai ? 'Bukti setor kas negara (SSBP)' : 'Berita acara perbaikan');
        $('[data-link-bukti]', el).setAttribute('aria-label', 'Link ' + sebut);
        $('[data-hanya-tunai]', el).hidden = !tunai;
        $('[data-hanya-perbaikan]', el).hidden = tunai;
        var h = $('[data-hint-ntpn]', el);
        h.textContent = x.ntpn ? (ntpnSah(x.ntpn) ? 'Format sesuai' : x.ntpn.length + ' dari 16 karakter') : '16 karakter';
        h.classList.toggle('salah', !!x.ntpn && !ntpnSah(x.ntpn));
      });
      var bakal = masuk + sahSetor, lebih = target ? bakal > target : false;
      var lunas = !target || bakal >= target;
      var bar = $('[data-bar-pulih]', f);
      if (bar) {
        bar.style.width = Math.min(100, target ? bakal / target * 100 : 0) + '%';
        bar.style.background = lebih ? 'var(--verm)' : '';
        /* Hijau begitu kewajibannya tertutup, kuning selama masih bersisa. */
        bar.parentElement.classList.toggle('dana', !lunas);
        $('[data-bakal]', f).textContent = rp(bakal) === '—' ? 'Rp 0' : rp(bakal);
        var l = $('[data-lebih]', f);
        l.hidden = !lebih;
        l.textContent = 'Melebihi kewajiban ' + rp(bakal - target);
        var totalAngsur = lama + baris.length;
        var kunci = f.dataset.angsurKunci === '1';
        var tanda = $('[data-tanda-angsur]', f);
        if (tanda) {
          tanda.hidden = $('[data-isi-pulih]', f).hidden;
          tanda.textContent = 'angsuran ke-' + (Math.min(totalAngsur, rencana) || 1) + ' dari rencana ' + rencana + (kunci ? ' · dikunci' : '');
        }
        var habis = rencana && kunci && totalAngsur >= rencana;
        $('[data-tambah-setor]', f).disabled = !!habis;
        $('[data-kuota-habis]', f).hidden = !habis;
        $('[data-lewat-rencana]', f).hidden = !(rencana && !kunci && totalAngsur > rencana);
        var sk = $('[data-setor-kurang]', f);
        sk.hidden = !belumSah;
        sk.textContent = belumSah + ' baris belum lengkap — lengkapi atau hapus sebelum mengirim. Draf tetap menyimpannya.';
      }

      var bolehSimpan = !!uraian && !lebih;
      var bolehKirim = sisaDok === 0 && !lebih && buktiKurang === 0 && belumSah === 0 && bolehSimpan;
      $('[data-simpan-draf]', f).disabled = !bolehSimpan;
      var kirim = $('[data-kirim-setba]', f);
      kirim.disabled = !bolehKirim;
      aturKetKerja(f,
        lebih ? 'Nilai pemulihan melebihi kewajiban — periksa angkanya dulu'
          : sisaDok > 0 ? sisaDok + ' dokumen masih kurang, berkas belum bisa dikirim'
          : buktiKurang > 0 ? buktiKurang + ' link belum lengkap, berkas belum bisa dikirim'
          : belumSah > 0 ? belumSah + ' baris pemulihan belum lengkap — lengkapi atau hapus dulu'
          : !bolehSimpan ? 'Uraian dan tanggal pelaporan harus terisi dulu' : '',
        !lunas ? 'Sisa nilai ' + rp(target - bakal) + ' — berkas tetap bisa dikirim, sisanya menyusul'
          : 'Kewajiban tuntas — berkas siap dikirim ke Setba');

      /* Hitungan mundur kiriman otomatis, hanya saat benar-benar berjalan. */
      var kirimDraf = JSON.parse(f.dataset.kirimDraf || 'null');
      $('[data-pesan-kirim-draf]', f).hidden = !(kirimDraf && bolehKirim);
      if (kirimDraf) {
        $('[data-kotak-kirim-draf]', f).className = 'kerja-awas' + (kirimDraf.sisa <= 2 ? '' : ' tenang');
        $('[data-teks-kirim-draf]', f).textContent = kirimDraf.jatuh
          ? 'Draf belum dikirim lebih dari ' + (kirimDraf.hari || 7) + ' hari — akan terkirim otomatis ke Setba.'
          : 'Bila tidak dikirim sendiri, draf akan terkirim otomatis ke Setba dalam ' + kirimDraf.sisa + ' hari.';
      }

      var lamaBerkas = JSON.parse(f.dataset.berkasLama || '[]');
      var semuaNama = lamaBerkas.concat(namaBukti).filter(Boolean);
      var rincian = '<div class="lbl" style="margin-bottom:8px">Yang ikut terkirim</div><ul class="ceklis">'
        + '<li><span>Catatan tindak lanjut yang baru ditulis</span></li>'
        + '<li><span>' + semuaNama.length + ' berkas bukti<span class="lbl" style="display:block;margin-top:5px;line-height:1.7">' + esc(semuaNama.join(' · ')) + '</span></span></li>'
        + (target ? '<li><span>Pemulihan nilai ' + (bakal > 0 ? rp(bakal) : 'Rp 0') + ' dari ' + rp(target) + (lunas ? ' — lunas' : ' — sisa ' + rp(target - bakal) + ' menyusul') + '</span></li>' : '')
        + (butir.length ? '<li><span>Seluruh dokumen yang diminta sudah dipenuhi</span></li>' : '')
        + '</ul><div class="pesan warn" style="margin-top:14px;margin-bottom:0"><span>Periksa nama berkasnya — pastikan tidak ada berkas pribadi yang ikut terunggah.</span></div>';
      kirim.dataset.pastikan = JSON.stringify({
        judul: 'Kirim tindak lanjut ini ke Setba?',
        ket: f.dataset.bentuk
          ? 'Yang berpindah hanya tindak lanjut “' + f.dataset.bentuk + '”, dan sesudah ini satuan kerja tidak bisa lagi mengubahnya sendiri.'
            + (f.dataset.bentukLain === '1' ? ' Bentuk tindak lanjut lain yang Anda pikul pada rekomendasi ini tetap di sini, dan dikirim sendiri.' : '')
          : 'Berkas berpindah ke meja Setba dan satuan kerja tidak bisa lagi mengubahnya sendiri.',
        rincian: rincian, tombol: 'Ya, kirim ke Setba', balik: false });
    }
    /* Baris baru dari templatnya, disisipkan sebelum tombol tambahnya. */
    var nomor = 1000;
    function klon(templat, wadah, sebelum) {
      var html = templat.innerHTML.replace(/__i__/g, String(nomor++));
      var sementara = document.createElement('div');
      sementara.innerHTML = html;
      var el = sementara.firstElementChild;
      wadah.insertBefore(el, sebelum || null);
      return el;
    }
    document.addEventListener('click', function (e) {
      var f = e.target.closest('[data-panel-balai]');
      if (!f) return;
      var li = e.target.closest('[data-butir]');
      if (e.target.closest('[data-tambah-butir]') && li) {
        $('[data-isi-butir]', li).hidden = false;
        $$('input', li).forEach(function (i) { i.disabled = false; });
        $('[data-tambah-butir]', li).hidden = true;
        $('[data-hapus-butir]', li).hidden = false;
        $('[data-bukti-nama]', li).focus();
      } else if (e.target.closest('[data-hapus-butir]') && li) {
        $('[data-isi-butir]', li).hidden = true;
        $$('input', li).forEach(function (i) { i.disabled = true; if (i.type !== 'hidden') i.value = ''; });
        $('[data-tambah-butir]', li).hidden = false;
        $('[data-hapus-butir]', li).hidden = true;
        $('[data-tambah-butir]', li).focus();
      } else if (e.target.closest('[data-tambah-lepas]')) {
        var wadah = $('[data-berkas-lepas]', f);
        var el = klon($('[data-templat-lepas]', f), wadah, $('[data-tambah-lepas]', wadah));
        $('[data-bukti-nama]', el).focus();
      } else if (e.target.closest('[data-hapus-lepas]')) {
        e.target.closest('[data-lepas]').remove();
        $('[data-tambah-lepas]', f).focus();
      } else if (e.target.closest('[data-buka-pulih]')) {
        /* Langsung dengan baris pertamanya — yang menekan memang mau mencatat. */
        e.target.closest('[data-buka-pulih]').hidden = true;
        var isiPulih = $('[data-isi-pulih]', f);
        isiPulih.hidden = false;
        if (!$('[data-setor]', isiPulih)) klon($('[data-templat-setor]', f), isiPulih, $('[data-tambah-setor]', isiPulih));
        $('[data-f="nilai"]', isiPulih).focus();
      } else if (e.target.closest('[data-tambah-setor]')) {
        var isiP = $('[data-isi-pulih]', f);
        var baru = klon($('[data-templat-setor]', f), isiP, $('[data-tambah-setor]', isiP));
        $('[data-f="nilai"]', baru).focus();
      } else if (e.target.closest('[data-hapus-setor]')) {
        e.target.closest('[data-setor]').remove();
        $('[data-tambah-setor]', f).focus();
      } else if (e.target.closest('[data-tambah-bukti-setor]')) {
        var s = e.target.closest('[data-setor]');
        $('[data-isi-bukti-setor]', s).hidden = false;
        e.target.closest('[data-tambah-bukti-setor]').hidden = true;
        $('[data-judul-bukti]', s).focus();
      } else if (e.target.closest('[data-hapus-bukti-setor]')) {
        var s2 = e.target.closest('[data-setor]');
        $('[data-isi-bukti-setor]', s2).hidden = true;
        $$('[data-isi-bukti-setor] input', s2).forEach(function (i) { i.value = ''; });
        $('[data-tambah-bukti-setor]', s2).hidden = false;
        $('[data-tambah-bukti-setor]', s2).focus();
      } else {
        return;
      }
      atur(f);
    });
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-panel-balai]');
      if (f) atur(f);
    });
    document.addEventListener('change', function (e) {
      var f = e.target.closest('[data-panel-balai]');
      if (f) atur(f);
    });
    $$('[data-panel-balai]').forEach(atur);
  }

  /* ================= halaman laporan ================= */
  /* Blok temuan tertutup saat pertama tampil. Baris rekomendasi di dalamnya
     tidak membuka apa-apa di tempat — ia link ke halaman rinciannya, diurus
     `pasangBarisLink`. */
  function pasangTemuan() {
    function aturTemuan(blok, buka) {
      $('[data-isi-temuan]', blok).hidden = !buka;
      $('[data-buka-temuan]', blok).setAttribute('aria-expanded', buka ? 'true' : 'false');
      $('.panah', blok).classList.toggle('buka', buka);
    }
    $$('[data-temblok]').forEach(function (b) { aturTemuan(b, false); });
    document.addEventListener('click', function (e) {
      var kep = e.target.closest('[data-buka-temuan]');
      if (kep) {
        var blok = kep.closest('[data-temblok]');
        aturTemuan(blok, kep.getAttribute('aria-expanded') !== 'true');
      }
    });
  }

  /* ================= Data master, Log aktivitas, halaman masuk (27 Sep) ================= */
  /* Padanan perilaku `LayarMaster`, `LayarLog`, dan pemilih akun prototipe.
     Semuanya penambah: tanpa skrip, jendela tetap terbuka lewat alamat
     (?jendela=), filter tetap formulir GET, Rincian log tetap link. */
  /* Halaman masuk (ditata ulang 28 Sep): mata password, peringatan Caps
     Lock, tombol yang menunggu jawaban server, akun contoh, dan terbuka
     tidaknya daftar akun contoh. Tanpa skrip halamannya tetap bisa dipakai —
     yang hilang hanya kemudahan-kemudahan ini. */
  function pasangMasuk() {
    var form = $('form[data-form-masuk]');
    var password = $('#password');

    /* Tombol yang sedang menunggu: berputar, tulisannya berganti ("Memeriksa…"),
       dan tidak bisa ditekan dua kali. */
    function sibuk(el) {
      if (!el || el.hasAttribute('data-sibuk')) return false;
      el.setAttribute('data-sibuk', '');
      el.setAttribute('aria-busy', 'true');
      var t = $('.teks', el);
      if (t && el.dataset.tunggu) { t.dataset.asli = t.textContent; t.textContent = el.dataset.tunggu; }
      return true;
    }
    function lepas() {
      $$('.msk [data-sibuk]').forEach(function (el) {
        el.removeAttribute('data-sibuk');
        el.removeAttribute('aria-busy');
        var t = $('.teks', el);
        if (t && t.dataset.asli) { t.textContent = t.dataset.asli; delete t.dataset.asli; }
      });
    }
    if (!$('.msk')) return;
    /* Kembali ke halaman ini lewat tombol Kembali browser: halamannya diambil
       dari tembolok browser lengkap dengan tombol yang masih berputar. */
    window.addEventListener('pageshow', function (e) { if (e.persisted) lepas(); });

    var mata = $('[data-mata-password]');
    function tampakkan(ya) {
      if (!mata || !password) return;
      password.type = ya ? 'text' : 'password';
      var kata = ya ? 'Sembunyikan password' : 'Tampilkan password';
      mata.setAttribute('aria-pressed', ya ? 'true' : 'false');
      mata.setAttribute('aria-label', kata);
      mata.title = kata;
    }
    if (mata && password) {
      mata.hidden = false;
      mata.addEventListener('click', function () { tampakkan(password.type === 'password'); });
    }

    /* Caps Lock hanya bisa dibaca dari tombol yang ditekan, jadi diperiksa
       tiap ketukan di isian password. */
    var caps = $('#caps-password');
    if (password && caps) {
      var periksa = function (e) {
        if (e.getModifierState) caps.hidden = !e.getModifierState('CapsLock');
      };
      password.addEventListener('keydown', periksa);
      password.addEventListener('keyup', periksa);
      password.addEventListener('blur', function () { caps.hidden = true; });
    }

    if (form) {
      /* Sesudah gagal masuk kedua isian bergaris merah — begitu salah satunya
         diketik ulang, tanda salahnya dilepas. */
      $$('input[aria-invalid]', form).forEach(function (i) {
        i.addEventListener('input', function () {
          $$('input[aria-invalid]', form).forEach(function (x) { x.removeAttribute('aria-invalid'); });
        });
      });
      form.addEventListener('submit', function (e) {
        var tombol = $('button[type=submit]', form);
        if (tombol && tombol.hasAttribute('data-sibuk')) { e.preventDefault(); return; }
        tampakkan(false);
        sibuk(tombol);
        /* Jaringan putus: jangan berputar selamanya. */
        setTimeout(lepas, 20000);
      });
    }

    /* Masuk SSO berpindah ke halaman penyedia — tombolnya ikut menunggu.
       Klik tengah atau dengan Ctrl/⌘ membuka tab baru: tidak ditandai. */
    $$('a.msk-tombol[data-tunggu]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (!sibuk(a)) { e.preventDefault(); return; }
        setTimeout(lepas, 20000);
      });
    });

    /* Akun contoh — padanan pemilih "Masuk sebagai" di prototipe. Dulu skrip
       sebaris; di sini supaya halaman masuk tidak butuh skrip sebaris (CSP). */
    var daftar = $('[data-akun-demo]');
    if (daftar && form && password) {
      $$('button[data-email]', daftar).forEach(function (b) {
        b.addEventListener('click', function () {
          if ($('[data-sibuk]', form)) return;
          $('#email').value = b.dataset.email;
          password.value = daftar.dataset.password || 'rahasia123';
          b.setAttribute('data-sibuk', '');
          if (form.requestSubmit) form.requestSubmit(); else form.submit();
        });
      });
    }

    /* Terbuka atau terlipatnya daftar akun contoh diingat tiap browser.
       Yang terlipat sudah dilipat sebelum tergambar (skrip sebaris di
       masuk.blade.php); di sini tinggal mencatat perubahannya. */
    $$('details[data-ingat-buka]').forEach(function (d) {
      d.addEventListener('toggle', function () {
        try { localStorage.setItem(d.dataset.ingatBuka, d.open ? '1' : '0'); } catch (e) {}
      });
    });
  }

  /* ================= menu akun (28 Sep) ================= */
  /* Padanan `MenuAkun` prototipe. Tombol pengguna di kanan atas membuka menu
     di bawahnya; tanpa skrip ia tetap link ke Profil & pengaturan. Menutup:
     klik di luar, Esc (fokus kembali ke tombolnya), atau fokus pergi. Panah
     atas/bawah berpindah antarbutir; panah bawah di tombolnya membuka menu. */
  function pasangMenuAkun() {
    var tombol = $('[data-buka-akun]'), menu = $('[data-menu-akun]');
    if (!tombol || !menu) return;
    function butir() { return $$('.ak-butir', menu); }
    function buka(fokusPertama) {
      /* Pop-up pemberitahuan menutupi menunya — disingkirkan, seperti saat panel
         lonceng dibuka. */
      var popup = $('[data-popup]');
      if (popup && popup.tutupPopup) popup.tutupPopup();
      menu.hidden = false;
      tombol.setAttribute('aria-expanded', 'true');
      if (fokusPertama && butir()[0]) butir()[0].focus();
    }
    function tutup(fokus) {
      if (menu.hidden) return;
      menu.hidden = true;
      tombol.setAttribute('aria-expanded', 'false');
      if (fokus) tombol.focus();
    }
    tombol.addEventListener('click', function (e) {
      /* Klik tengah atau dengan Ctrl/⌘/Shift membuka halaman Profil di tab baru. */
      if (e.ctrlKey || e.metaKey || e.shiftKey || (e.button || 0) !== 0) return;
      e.preventDefault();
      if (menu.hidden) buka(false); else tutup(false);
    });
    tombol.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); buka(true); }
      else if (e.key === ' ') { e.preventDefault(); if (menu.hidden) buka(true); else tutup(false); }
    });
    function diLuar(el) { return !menu.contains(el) && !tombol.contains(el); }
    document.addEventListener('mousedown', function (e) { if (!menu.hidden && diLuar(e.target)) tutup(false); });
    document.addEventListener('focusin', function (e) { if (!menu.hidden && diLuar(e.target)) tutup(false); });
    document.addEventListener('keydown', function (e) {
      if (menu.hidden) return;
      if (e.key === 'Escape') { tutup(true); return; }
      if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(e.key) < 0 || !menu.contains(document.activeElement)) return;
      e.preventDefault();
      var d = butir(), i = d.indexOf(document.activeElement);
      var j = e.key === 'Home' ? 0 : e.key === 'End' ? d.length - 1
        : e.key === 'ArrowDown' ? (i + 1) % d.length : (i - 1 + d.length) % d.length;
      d[j].focus();
    });
    /* Panduan singkat dari menu: menunya ditutup; fokusnya nanti kembali ke
       tombol pengguna (pasangPanduan). */
    menu.addEventListener('click', function (e) {
      if (e.target.closest('[data-buka-panduan]')) tutup(false);
    });
  }

  /* ================= Profil & pengaturan (28 Sep) ================= */
    var antrianTampilan = Promise.resolve(), pesanTampilanTimer, tampilanTertunda = 0, transisiTemaTimer;
  function kabarTampilan(teks, gagal) {
    var p = $('[data-tampilan-pesan]');
    if (!p) return;
    clearTimeout(pesanTampilanTimer);
    p.textContent = teks;
    p.classList.toggle('gagal', !!gagal);
    p.hidden = false;
    pesanTampilanTimer = setTimeout(function () { p.hidden = true; }, gagal ? 12000 : 3500);
  }
  function terapkanTampilan(p) {
    if (p.tema === 'terang' || p.tema === 'gelap') {
        var akar = document.documentElement;
        if (akar.dataset.tema !== p.tema) {
          clearTimeout(transisiTemaTimer);
          var gerak = document.body.dataset.animasi !== 'kurang'
            && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
            && !document.hidden;
          akar.classList.toggle('tema-beralih', gerak);
          // Daftarkan transisi sebelum warna diganti, termasuk elemen yang
          // sebelumnya tidak mempunyai transisi. Tidak dijalankan saat muat awal.
          if (gerak) void getComputedStyle(document.body).backgroundColor;
          akar.dataset.tema = p.tema;
          if (gerak) transisiTemaTimer = setTimeout(function () {
            akar.classList.remove('tema-beralih');
          }, 380);
        }
      $$('[data-tema-pintas]').forEach(function (f) {
        var berikutnya = p.tema === 'gelap' ? 'terang' : 'gelap';
        $('input[name=tema]', f).value = berikutnya;
        var b = $('button', f), label = 'Gunakan tema ' + berikutnya;
        b.title = label; b.setAttribute('aria-label', label);
      });
    }
    if (['otomatis', 'lebar', 'ringkas'].indexOf(p.menu) >= 0) document.body.dataset.modeMenu = p.menu;
    ['tema', 'menu'].forEach(function (k) {
      if (!p[k]) return;
      $$('[data-form-tampilan] input[name="' + k + '"]').forEach(function (i) { i.checked = i.value === p[k]; });
    });
    document.dispatchEvent(new CustomEvent('simtlhp:tampilan'));
  }
  function simpanTampilan(pilihan) {
    // Urutkan permintaan dari pintasan agar klik cepat tidak membalik hasil.
    tampilanTertunda++;
    antrianTampilan = antrianTampilan.then(async function () {
      try {
        var r = await fetch(document.body.dataset.tampilanUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: {'Content-Type': 'application/json', 'Accept': 'application/json',
            'X-CSRF-TOKEN': $('meta[name=csrf-token]').content},
          body: JSON.stringify(Object.assign({akun: document.body.dataset.akunId}, pilihan)),
        });
        if (!r.ok) throw new Error([401, 409, 419].indexOf(r.status) >= 0
          ? 'Sesi akun berubah atau berakhir. Muat ulang halaman lalu coba lagi.'
          : 'Pengaturan belum tersimpan. Silakan coba lagi.');
        var data = await r.json();
        if (!data.tampilan) throw new Error('Pengaturan belum tersimpan. Muat ulang halaman lalu coba lagi.');
        terapkanTampilan(data.tampilan);
        kabarTampilan('Pengaturan tampilan tersimpan untuk akun Anda.', false);
        // Hanya memberi kabar ke tab akun yang sama; sumber saat muat tetap server.
        try { localStorage.setItem('tridaya.tampilan.' + document.body.dataset.akunId,
          JSON.stringify({pilihan: data.tampilan, waktu: Date.now()})); } catch (e) { /* penyimpanan browser opsional */ }
      } catch (e) {
        kabarTampilan(e instanceof TypeError ? 'Koneksi terputus. Pengaturan belum tersimpan; silakan coba lagi.' : e.message, true);
      } finally {
        tampilanTertunda--;
      }
    });
    return antrianTampilan;
  }
  function pasangTampilan() {
    if (!document.body.dataset.tampilanUrl) return;
    $$('[data-tema-pintas]').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var b = $('button', f);
        if (b.disabled) return;
        b.disabled = true;
        simpanTampilan({tema: $('input[name=tema]', f).value}).finally(function () { b.disabled = false; });
      });
    });
    window.addEventListener('storage', function (e) {
      if (e.key !== 'tridaya.tampilan.' + document.body.dataset.akunId || !e.newValue) return;
      try { terapkanTampilan(JSON.parse(e.newValue).pilihan || {}); } catch (x) { /* abaikan pesan tidak valid */ }
    });
    var form = $('[data-form-tampilan]');
    if (form) form.addEventListener('submit', function (e) {
      // Hindari nilai formulir lama menimpa pintasan yang masih menyimpan.
      if (tampilanTertunda) { e.preventDefault(); kabarTampilan('Tunggu sampai perubahan dari pintasan selesai tersimpan.', false); }
    });
  }
  /* Padanan `LayarAkun` prototipe: kemudahan yang butuh skrip — tampilkan
     password dan peringatan saat email dimatikan. */
  function pasangAkun() {
    var tampakPassword = $('[data-tampak-password]'), formPassword = $('form[data-form-password]');
    if (tampakPassword && formPassword) {
      tampakPassword.hidden = false;
      $('input', tampakPassword).addEventListener('change', function (e) {
        $$('input[type=password], input[data-password-tampak]', formPassword).forEach(function (i) {
          i.type = e.target.checked ? 'text' : 'password';
          i.toggleAttribute('data-password-tampak', e.target.checked);
        });
      });
      /* Dikirim tersamar lagi — pengelola password browser mengenalinya. */
      formPassword.addEventListener('submit', function () {
        $$('input[data-password-tampak]', formPassword).forEach(function (i) { i.type = 'password'; });
      });
    }

    var awas = $('[data-awas-email]');
    if (awas) {
      var saklar = $('input[name=email]', awas.closest('label'));
      if (saklar) saklar.addEventListener('change', function () { awas.hidden = saklar.checked; });
    }

  }

  function pasangMaster() {
    /* Jendela Tambah/Ubah: Escape dan menekan latarnya menutup — pulang ke
       alamat tab tanpa ?jendela=. */
    var tirai = $('[data-tirai-master]');
    if (tirai) {
      var tutup = function () { location.href = tirai.dataset.tutup; };
      tirai.addEventListener('click', function (e) { if (e.target === tirai) tutup(); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !$('.tirai-tanya')) tutup();
      });
      var pertama = $('input[autofocus], input:not([type=hidden])', tirai);
      if (pertama) pertama.focus();
    }

    /* Baris yang baru diubah disorot sebentar, dan dibawa ke depan mata. */
    var sorot = $('.dm-tabel tr.disorot');
    if (sorot) {
      setTimeout(function () { sorot.scrollIntoView({ block: 'center', behavior: 'smooth' }); }, 120);
      setTimeout(function () { sorot.classList.remove('disorot'); }, 2400);
    }

    /* Kotak cari di kepala tab memfilter barisnya sambil mengetik. Enter tetap
       mengirimnya (filter di server). */
    $$('form[data-filter-baris]').forEach(function (form) {
      var tabel = document.getElementById(form.dataset.filterBaris);
      var kotak = $('input[type=search]', form);
      if (!tabel || !kotak) return;
      var kosong = document.createElement('tr');
      kosong.className = 'dm-kosong-baris';
      kosong.hidden = true;
      kosong.innerHTML = '<td colspan="9" class="dm-kosong"></td>';
      $('tbody', tabel).appendChild(kosong);
      kotak.addEventListener('input', function () {
        var q = kotak.value.trim().toLowerCase();
        var n = 0;
        $$('tbody tr[data-cari]', tabel).forEach(function (tr) {
          var cocok = !q || tr.dataset.cari.toLowerCase().indexOf(q) >= 0;
          tr.hidden = !cocok;
          if (cocok) n++;
        });
        $$('tbody tr.dm-kosong-baris', tabel).forEach(function (tr) { if (tr !== kosong) tr.hidden = true; });
        kosong.hidden = n > 0 || !q;
        kosong.firstChild.textContent = 'Tidak ada yang cocok dengan “' + kotak.value.trim() + '”.';
      });
    });

    /* Tambah pengguna: mencari di direktori sambil mengetik. */
    var cari = $('[data-cari-pegawai]');
    if (cari) {
      var kotakCari = $('input[name=q]', cari);
      var hasil = $('[data-hasil-pegawai]');
      var jeda, urut = 0;
      cari.addEventListener('submit', function (e) { e.preventDefault(); });
      kotakCari.addEventListener('input', function () {
        clearTimeout(jeda);
        jeda = setTimeout(function () {
          var nomor = ++urut;
          fetch(cari.dataset.sumber + '?q=' + encodeURIComponent(kotakCari.value), {
            credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
          })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
            .then(function (t) {
              if (nomor !== urut) return;
              hasil.innerHTML = t;
              history.replaceState(null, '', location.pathname + '?tab=pengguna&jendela=pengguna'
                + (kotakCari.value.trim() ? '&q=' + encodeURIComponent(kotakCari.value.trim()) : ''));
            })
            .catch(function () { /* Enter tetap bisa mengirim formulirnya */ });
        }, 200);
      });
    }

    /* Log aktivitas: pilihan filter langsung dikirim; Rincian dibuka di
       tempat tanpa memuat ulang halaman. */
    var filterLog = $('form[data-log-filter]');
    if (filterLog) {
      filterLog.addEventListener('change', function (e) {
        if (e.target.matches('select')) filterLog.requestSubmit ? filterLog.requestSubmit() : filterLog.submit();
      });
    }
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-rinci-log]');
      if (!t) return;
      var baris = document.getElementById(t.getAttribute('aria-controls'));
      if (!baris) return;
      e.preventDefault();
      var buka = baris.hidden;
      baris.hidden = !buka;
      t.setAttribute('aria-expanded', buka ? 'true' : 'false');
    });
  }

  function mulai() {
    /* Tombol yang hanya berguna kalau skrip ini mati — mis. "Terapkan" pada
       formulir yang di sini terkirim sendiri begitu pilihannya diganti. */
    $$('[data-tanpa-js]').forEach(function (x) { x.hidden = true; });
    pasangTemuan();
    pasangInfo();
    pasangLekat();
    pasangLaci();
    pasangCiut();
    pasangPanduan();
    pasangArsip();
    pasangPemberitahuan();
    pasangBlokLipat();
    pasangLipat();
    pasangBarisLink();
    pasangFilter();
    pasangDaftarDiTempat();
    pasangCari();
    pasangPreview();
    pasangPastikan();
    pasangTiket();
    pasangTabelTl();
    pasangPeriodeRiwayat();
    pasangOpsional();
    pasangDaftarIsian();
    pasangPanelSiptl();
    pasangPanelPeriksa();
    pasangFormSurat();
    pasangKirimUlang();
    pasangPanelBalai();
    pasangFormBaru();
    pasangDasbor();
    pasangPilihPj();
    pasangMasuk();
    pasangMenuAkun();
    pasangTampilan();
    pasangAkun();
    pasangMaster();
    pasangSorot();
    pasangPintasan();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
  else mulai();
})();
