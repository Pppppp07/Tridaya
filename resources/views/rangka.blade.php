<!doctype html>
<html lang="id" data-tema="{{ auth()->user()?->setelan('tema') ?? 'terang' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('judul', 'Monitoring TLHP') · Tridaya</title>
  <meta name="application-name" content="Tridaya">
  <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="16x16 32x32 48x48">
  <link rel="icon" type="image/svg+xml" href="{{ asset('brand/tridaya/favicon.svg') }}?v={{ filemtime(public_path('brand/tridaya/favicon.svg')) }}" sizes="any">
  <link rel="apple-touch-icon" href="{{ asset('brand/tridaya/apple-touch-icon.png') }}?v={{ filemtime(public_path('brand/tridaya/apple-touch-icon.png')) }}">
  <link rel="preload" href="{{ asset('fonts/manrope-variable.ttf') }}" as="font" type="font/ttf" crossorigin>
  {{-- Aset statis biasa, tanpa langkah build. simtlhp.css disalin dari
       prototipe; yang khusus Laravel ada di simtlhp-tambahan.css. --}}
  <link rel="stylesheet" href="{{ asset('css/simtlhp.css') }}?v={{ filemtime(public_path('css/simtlhp.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/simtlhp-tambahan.css') }}?v={{ filemtime(public_path('css/simtlhp-tambahan.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/simtlhp-tema.css') }}?v={{ filemtime(public_path('css/simtlhp-tema.css')) }}">
  <script src="{{ asset('js/simtlhp.js') }}?v={{ filemtime(public_path('js/simtlhp.js')) }}" defer></script>
</head>
{{-- "Kurangi animasi" di Profil → Tampilan (28 Sep): seluruh animasi dan
     peralihan dimatikan untuk akun ini, di perangkat mana pun — aturannya di
     simtlhp.css (src/akun.css prototipe). --}}
<body @if(auth()->user()?->setelan('animasi') === 'kurang') data-animasi="kurang" @endif
  @auth data-mode-menu="{{ auth()->user()->setelan('menu') }}" data-akun-id="{{ auth()->id() }}" data-tampilan-url="{{ route('akun.tampilan') }}" @endauth>
{{-- Gerak tiba di layar (26 Sep) — padanan data-masuk di kerangka prototipe:
     selama 0,7 detik sesudah halaman siap, bagian-bagian halamannya naik
     bergiliran (aturannya di simtlhp.css, "GERAK SELURUH SISTEM"). Dipasang
     sebelum isi halamannya tergambar. Tidak dipasang kalau yang dimuat halaman
     yang sama — sesudah formulir dikirim atau filter diganti — sebab di
     prototipe layarnya tetap berdiri dan tidak diputar ulang. --}}
{{-- Skrip sebaris membawa `nonce` permintaan ini — tanpa itu ditolak
     Content-Security-Policy (KepalaAman, 27 Sep). --}}
<script nonce="{{ $nonce ?? '' }}">(function(){var b=document.body;try{var r=document.referrer?new URL(document.referrer):null;if(r&&r.origin===location.origin&&r.pathname===location.pathname)return}catch(e){}b.setAttribute('data-masuk','');addEventListener('DOMContentLoaded',function(){setTimeout(function(){b.removeAttribute('data-masuk')},700)})})()</script>
@use('App\Enums\PeranPengguna', 'P')
@auth
  {{-- Lebar menu dibaca dari pengaturan akun sebelum halamannya tergambar,
       supaya menunya tidak melompat. Tanpa pilihan,
       rel di bawah 1121 px — sama dengan Prototipe/src/navigasi.js.
       Kalau simtlhp.js gagal dimuat, kontrolnya tidak pernah dipasang dan rel
       tidak bisa dilebarkan. Maka rel dilepas lagi begitu halamannya selesai
       dimuat tanpa kontrol itu, supaya nama menu tetap mudah dibaca. --}}
  <script nonce="{{ $nonce ?? '' }}">(function(){var b=document.body,m=b.dataset.modeMenu;if(m==='ringkas'||(m==='otomatis'&&!matchMedia('(min-width:1121px)').matches))b.classList.add('nav-ringkas');addEventListener('load',function(){var k=document.querySelector('[data-kontrol-nav]');if(k&&k.hidden)b.classList.remove('nav-ringkas')})})()</script>
  @php
    $u = auth()->user();
    $peran = $u->peran;
    $bingkai = \App\Support\Rangka::untuk($u);
    $akun = \App\Support\Rangka::pengguna($u);

    /* Menu datar tanpa kelompok, sama dengan prototipe. Butir yang tidak
       berlaku bagi sebuah peran dihilangkan, bukan diganti nama lain. */
    $menu = [
      /* `aktif` menyalakan menunya sebagai halaman itu sendiri ("page");
         `induk` menyalakannya untuk halaman turunannya ("true") — rincian
         rekomendasi menyalakan Rekomendasi, rincian laporan dan Catat laporan
         baru menyalakan Daftar laporan (21 Sep). */
      ['rute' => 'rekomendasi.index', 'aktif' => ['rekomendasi.index'], 'induk' => ['rekomendasi.show'],
        'ikon' => 'FileText', 'nama' => 'Rekomendasi',
        'tanda' => $bingkai['perluKerja'], 'ket' => $bingkai['perluKerja'].' perlu Anda kerjakan'],
      ['rute' => 'laporan.index', 'aktif' => ['laporan.index'], 'induk' => ['laporan.show', 'laporan.baru'],
        'ikon' => 'Files', 'nama' => 'Daftar laporan'],
    ];
    if (in_array($peran, [P::SETBA, P::PIMPINAN, P::ADMIN], true)) {
      array_unshift($menu, ['rute' => 'ringkasan', 'aktif' => ['ringkasan'], 'ikon' => 'LayoutGrid', 'nama' => 'Dashboard']);
    }
    if ($peran->kelolaMaster()) {
      $menu[] = ['rute' => 'master', 'aktif' => ['master*'], 'ikon' => 'ListChecks', 'nama' => 'Data master',
        'tanda' => $bingkai['tanpaPj'], 'genting' => true,
        'ket' => $bingkai['tanpaPj'].' unit kerja belum punya penanggung jawab'];
    }
    /* Log aktivitas (27 Sep) — DTI, dan Admin. Bang Kamal tentang DTI: "Log
       aktivitas, sebatas itu. Terus data master enggak." */
    if ($peran->bacaLog()) {
      $menu[] = ['rute' => 'log', 'aktif' => ['log*'], 'ikon' => 'History', 'nama' => 'Log aktivitas'];
    }
    /* Pemberitahuan bukan butir menu lagi (18 Sep) — lonceng di batang atas
       tugasnya sama persis, dan tampil di semua lebar layar. */
  @endphp

  {{-- Batang atas hanya muncul di layar sempit — pembuka laci navigasi. --}}
  <div class="nav-atas">
    <button class="buka-nav" type="button" data-buka-nav aria-label="Buka menu" aria-expanded="false" aria-controls="nav-utama"><x-ikon n="Menu" :s="18" /></button>
    <b>@yield('judul')</b>
    <x-logo-tridaya :simbol-saja="true" class="tridaya-ponsel" />
  </div>
  <div class="tirai-nav" data-tutup-nav aria-hidden="true"></div>

  {{-- Selama laci ponsel terbuka, skrip menjadikannya dialog (role, aria-modal)
       dan mengunci halaman di belakangnya. Klik di area kosongnya
       menciutkan/melebarkan menu di komputer dan tablet. --}}
  <nav class="nav" id="nav-utama" aria-label="Menu utama" tabindex="-1">
    <p class="nav-ruang">Dalam ruang kerja</p>
    <div class="brand">
      <x-logo-tridaya />
      {{-- Laci di ponsel juga bisa ditutup dari dalam, bukan cuma dengan
           mengetuk bayangan di sebelahnya. --}}
      <button type="button" class="tutup-nav" data-tutup-nav aria-label="Tutup menu"><x-ikon n="X" :s="18" /></button>
    </div>
    <p class="nav-deskripsi">Pemantauan Tindak Lanjut<br>BPSDM</p>

    {{-- Kontrol lebar di samping label ruang kerja; di rel turun di bawah
         simbol. Area kosong menu dan pintasan [ tetap berfungsi. --}}
    <div class="kontrol-nav" data-kontrol-nav hidden>
      <button type="button" class="ciut" data-ciut-nav aria-expanded="true" aria-controls="nav-utama"
        aria-label="Ciutkan menu" title="Ciutkan menu — atau klik area kosong menu, atau tekan [">
        <x-ikon n="ChevronLeft" :s="18" data-ciut-ikon="ciut" />
        <x-ikon n="ChevronRight" :s="18" data-ciut-ikon="lebar" hidden />
      </button>
    </div>

    @foreach($menu as $m)
      @php
        $tanda = $m['tanda'] ?? 0;
        $kini = request()->routeIs(...$m['aktif']) ? 'page'
          : (! empty($m['induk']) && request()->routeIs(...$m['induk']) ? 'true' : null);
      @endphp
      {{-- Angkanya disebut artinya — di rel, tulisan menu tinggal sepuluh
           piksel dan angkanya berdiri tanpa keterangan. --}}
      <a @class(['menu', 'genting' => ! empty($m['genting']) && $tanda > 0]) href="{{ route($m['rute']) }}"
        title="{{ $tanda > 0 ? $m['nama'].' — '.$m['ket'] : $m['nama'] }}"
        @if($tanda > 0) aria-label="{{ $m['nama'] }}, {{ $m['ket'] }}" @endif
        @if($kini) aria-current="{{ $kini }}" @endif>
        <span class="nav-penanda" aria-hidden="true"><x-ikon :n="$m['ikon']" :s="16" /></span>
        <span class="tulisan">{{ $m['nama'] }}</span>
        @if($tanda > 0)<span class="n" aria-hidden="true">{{ \App\Support\Rangka::angka($tanda) }}</span>@endif
      </a>
    @endforeach

    <div class="nav-kaki">
      <p class="nav-lembaga">BPSDM<span>Kementerian PU</span></p>
      <a class="nav-panduan" href="#panduan" title="Buka panduan" aria-label="Buka panduan" data-buka-panduan>
        <x-ikon n="HelpCircle" :s="19" />
      </a>
    </div>
  </nav>

  @include('bagian.panduan', [
    'peran' => $peran,
    'menu' => $menu,
    'sebutan' => $peran === P::SATKER ? 'Satuan kerja · '.($u->satker?->namaPendek() ?? '') : $akun['peran'],
  ])

  <div class="main">
    <header class="top">
      <h1>@yield('judul')</h1>

      {{-- Hasilnya diambil dari rute yang sudah difilter hak aksesnya. --}}
      <div class="cari-glob" data-cari-glob data-sumber="{{ route('cari') }}">
        <span class="kotak">
          <x-ikon n="Search" :s="15" />
          <input type="text" placeholder="Cari kode, temuan, atau satuan kerja" aria-label="Cari" autocomplete="off">
          <kbd>/</kbd>
          <button class="bersih" type="button" aria-label="Kosongkan pencarian" hidden><x-ikon n="X" :s="13" /></button>
        </span>
        <div class="panel" hidden></div>
      </div>

      <div class="alat">
        @if($peran === P::SETBA && ! request()->routeIs('laporan.baru'))
          {{-- Di layar sempit tinggal ikonnya; namanya tetap dibaca pembaca
               layar dan muncul sebagai keterangan saat ditunjuk (22 Sep). --}}
          <a class="btn btn-p" href="{{ route('laporan.baru') }}"
            title="{{ $bingkai['drafLaporan'] ? 'Lanjutkan draf laporan' : 'Catat laporan baru' }}">
            @if($bingkai['drafLaporan'])
              <x-ikon n="Save" :s="15" /><span class="teks-tombol">Lanjutkan draf laporan</span>
            @else
              <x-ikon n="Plus" :s="15" /><span class="teks-tombol">Catat laporan baru</span>
            @endif
          </a>
        @endif
        <form method="post" action="{{ route('akun.tampilan') }}" class="tema-pintas" data-tema-pintas>
          @csrf
          <input type="hidden" name="akun" value="{{ $u->id }}">
          <input type="hidden" name="tema" value="{{ $u->setelan('tema') === 'gelap' ? 'terang' : 'gelap' }}">
          <button type="submit" class="tema-tombol" title="{{ $u->setelan('tema') === 'gelap' ? 'Gunakan tema terang' : 'Gunakan tema gelap' }}"
            aria-label="{{ $u->setelan('tema') === 'gelap' ? 'Gunakan tema terang' : 'Gunakan tema gelap' }}">
            <x-ikon n="Moon" :s="19" class="tema-bulan" /><x-ikon n="Sun" :s="19" class="tema-matahari" />
          </button>
        </form>
        <span class="lbl tanggal-hari">
          <x-ikon n="Clock" :s="13" /> {{ \App\Support\Tampil::tgl(now()) }}
        </span>
        {{-- Lonceng membuka panel di bawahnya (26 Sep) — padanan PanelPemberitahuan;
             tanpa skrip tetap link ke layar Pemberitahuan. Angkanya
             menghitung pemberitahuan BARU (belum dibaca dan belum pernah tampil di
             daftarnya); menyala saat panelnya terbuka atau layarnya dibuka. --}}
        <span class="kb-wadah">
          <a class="lonceng" href="{{ route('pemberitahuan') }}" title="Pemberitahuan" data-buka-pemberitahuan
            aria-haspopup="dialog" aria-expanded="false" aria-controls="kb-panel"
            data-panel="{{ route('pemberitahuan.panel') }}" data-ringkas="{{ route('pemberitahuan.ringkas') }}"
            @if(request()->routeIs('pemberitahuan')) aria-current="page" @endif
            aria-label="Pemberitahuan, {{ $bingkai['baru'] }} baru, {{ $bingkai['belumDibaca'] }} belum dibaca">
            <x-ikon n="Bell" :s="19" />
            @if($bingkai['baru'] > 0)<span class="n">{{ \App\Support\Rangka::angka($bingkai['baru']) }}</span>@endif
          </a>
          <div id="kb-panel" class="kb-panel" role="dialog" aria-label="Pemberitahuan" hidden data-panel-pemberitahuan></div>
        </span>
        {{-- Tombol pengguna membuka menu akun (28 Sep) — padanan MenuAkun
             prototipe. Tanpa skrip tetap link ke Profil & pengaturan. --}}
        @php
          $sebutanAkun = $peran === P::SATKER ? 'Satuan kerja · '.($u->satker?->namaPendek() ?? '') : $akun['peran'];
          $tabAkun = request()->routeIs('akun') ? (in_array(request('tab'), \App\Http\Controllers\AkunController::TAB, true) ? request('tab') : 'profil') : null;
          $butirAkun = [
            ['profil', 'Profil', 'UserRound'], ['keamanan', 'Keamanan', 'ShieldCheck'],
            ['pemberitahuan', 'Pemberitahuan', 'Bell'], ['tampilan', 'Tampilan', 'SlidersHorizontal'],
            ['aktivitas', 'Aktivitas akun', 'History'],
          ];
        @endphp
        <span class="ak-wadah">
          <a class="pengguna" href="{{ route('akun') }}" data-buka-akun aria-haspopup="true" aria-expanded="false"
            aria-controls="ak-menu" title="Akun dan pengaturan" aria-label="Menu akun — {{ $u->name }}, {{ $sebutanAkun }}">
            <span class="rupa">{{ $akun['rupa'] }}</span>
            <span class="teks">
              <b>{{ $akun['nama'] }}</b>
              <span>{{ $akun['ket'] }}</span>
            </span>
            <x-ikon n="ChevronDown" :s="15" class="panah-pengguna" />
          </a>
          <div id="ak-menu" class="ak-menu" hidden data-menu-akun>
            <div class="ak-menu-kepala">
              <span class="rupa" aria-hidden="true">{{ \App\Support\Rangka::inisial($u->name) }}</span>
              <span class="teks">
                <b>{{ $u->name }}</b>
                <span>{{ $sebutanAkun }}</span>
                @if($u->email)<span>{{ $u->email }}</span>@endif
              </span>
            </div>
            <nav class="ak-menu-isi" aria-label="Akun">
              @foreach($butirAkun as [$k, $nama, $ikon])
                <a class="ak-butir" href="{{ route('akun', ['tab' => $k]) }}" @if($tabAkun === $k) aria-current="page" @endif>
                  <x-ikon :n="$ikon" :s="16" />{{ $nama }}
                </a>
              @endforeach
            </nav>
            <div class="ak-menu-isi">
              <a class="ak-butir" href="#panduan" data-buka-panduan><x-ikon n="HelpCircle" :s="16" />Panduan singkat</a>
            </div>
            <div class="ak-menu-isi">
              <form method="post" action="{{ route('keluar') }}">@csrf<button type="submit" class="ak-butir"><x-ikon n="LogOut" :s="16" />Keluar</button></form>
            </div>
          </div>
        </span>
      </div>
    </header>
    <div class="tampilan-pesan" role="status" aria-live="polite" data-tampilan-pesan hidden></div>

    @if(session('pesan') || session('gagal') || $errors->any())
      <div class="body pesanbingkai">
        @if(session('pesan'))
          <div class="pesan ok"><x-ikon n="Check" :s="16" /><span>{{ session('pesan') }}</span></div>
        @endif
        @if(session('gagal') || $errors->any())
          <div class="pesan bad"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') ?? $errors->first() }}</span></div>
        @endif
      </div>
    @endif

    @yield('isi')
  </div>

  @if($s = $bingkai['popup'])
    @include('bagian.pemberitahuan-popup', ['k' => $s['pemberitahuan'], 'jumlah' => $s['jumlah']])
  @endif
@else
  @yield('isi')
@endauth
</body>
</html>
