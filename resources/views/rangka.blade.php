<!doctype html>
<html lang="id" data-tema="{{ auth()->user()?->setelan('tema') ?? 'terang' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('judul', 'Monitoring TLHP') · {{ config('app.display_name') }}</title>
  <meta name="application-name" content="{{ config('app.display_name') }}">
  @include('bagian.ikon-aplikasi')
  @include('bagian.aset')
</head>
{{-- "Kurangi animasi" (Profil → Tampilan) mematikan animasi akun ini di semua perangkat. --}}
<body @if(auth()->user()?->setelan('animasi') === 'kurang') data-animasi="kurang" @endif
  @auth data-mode-menu="{{ auth()->user()->setelan('menu') }}" data-akun-id="{{ auth()->id() }}" data-tampilan-url="{{ route('akun.tampilan') }}"
    data-sesi-batas="{{ \App\Support\Sesi::batasDiam() }}" data-sesi="{{ route('sesi.sisa') }}" data-sesi-berakhir="{{ route('sesi.berakhir') }}"
    data-sesi-keluar="{{ route('keluar') }}" data-sesi-masuk="{{ route('masuk') }}" @endauth>
{{-- Gerak tiba di layar 0,7 detik (`data-masuk`); tidak dipasang bila yang dimuat halaman yang
     sama. --}}
{{-- Skrip sebaris wajib membawa `nonce` permintaan ini (CSP, KepalaAman). --}}
<script nonce="{{ $nonce ?? '' }}">(function(){var b=document.body;try{var r=document.referrer?new URL(document.referrer):null;if(r&&r.origin===location.origin&&r.pathname===location.pathname)return}catch(e){}b.setAttribute('data-masuk','');addEventListener('DOMContentLoaded',function(){setTimeout(function(){b.removeAttribute('data-masuk')},700)})})()</script>
@use('App\Enums\PeranPengguna', 'P')
@auth
  {{-- Lebar menu dibaca sebelum halaman tergambar supaya tidak melompat. Bila simtlhp.js gagal
       dimuat, rel dilepas lagi supaya nama menu terbaca. --}}
  <script nonce="{{ $nonce ?? '' }}">(function(){var b=document.body,m=b.dataset.modeMenu;if(m==='ringkas'||(m==='otomatis'&&!matchMedia('(min-width:1121px)').matches))b.classList.add('nav-ringkas');addEventListener('load',function(){var k=document.querySelector('[data-kontrol-nav]');if(k&&k.hidden)b.classList.remove('nav-ringkas')})})()</script>
  @php
    $u = auth()->user();
    $peran = $u->peranAktif();
    $bingkai = \App\Support\Rangka::untuk($u);
    $akun = \App\Support\Rangka::pengguna($u);

    /* Menu datar; butir yang tidak berlaku bagi peran dihilangkan. */
    $menu = [
      /* `aktif` = halaman itu sendiri ("page"); `induk` = halaman turunannya ("true"). */
      ['rute' => 'rekomendasi.index', 'aktif' => ['rekomendasi.index'], 'induk' => ['rekomendasi.show'],
        'ikon' => 'FileText', 'nama' => 'Rekomendasi',
        'tanda' => $bingkai['perluKerja'], 'ket' => $bingkai['perluKerja'].' perlu Anda kerjakan'],
      ['rute' => 'laporan.index', 'aktif' => ['laporan.index'], 'induk' => ['laporan.show', 'laporan.baru', 'laporan.historis.*'],
        'ikon' => 'Files', 'nama' => 'Daftar laporan'],
    ];
    if (in_array($peran, [P::SETBA, P::PIMPINAN, P::ADMIN], true)) {
      array_unshift($menu, ['rute' => 'ringkasan', 'aktif' => ['ringkasan'], 'ikon' => 'LayoutGrid', 'nama' => 'Dashboard']);
    }
    if ($peran->kelolaMaster()) {
      $menu[] = ['rute' => 'master', 'aktif' => ['master*'], 'ikon' => 'ListChecks', 'nama' => 'Data Master',
        'tanda' => $bingkai['tanpaPj'], 'genting' => true,
        'ket' => $bingkai['tanpaPj'].' satuan kerja belum punya penanggung jawab'];
    }
    /* Log aktivitas — DTI dan Admin. */
    if ($peran->bacaLog()) {
      $menu[] = ['rute' => 'log', 'aktif' => ['log*'], 'ikon' => 'History', 'nama' => 'Log aktivitas'];
    }
  @endphp

  {{-- Batang atas hanya muncul di layar sempit — pembuka laci navigasi. --}}
  <div class="nav-atas">
    <button class="buka-nav" type="button" data-buka-nav aria-label="Buka menu" aria-expanded="false" aria-controls="nav-utama"><x-ikon n="Menu" :s="18" /></button>
    <b>@yield('judul')</b>
    <x-logo-infax :simbol-saja="true" class="infax-ponsel" />
  </div>
  <div class="tirai-nav" data-tutup-nav aria-hidden="true"></div>

  {{-- Selama laci ponsel terbuka skrip menjadikannya dialog dan mengunci halaman di
       belakangnya. Klik area kosongnya menciutkan/melebarkan menu di layar lebar. --}}
  <nav class="nav" id="nav-utama" aria-label="Menu utama" tabindex="-1">
    <p class="nav-ruang">Dalam ruang kerja</p>
    <div class="brand">
      <x-logo-infax />
      <button type="button" class="tutup-nav" data-tutup-nav aria-label="Tutup menu"><x-ikon n="X" :s="18" /></button>
    </div>
    <p class="nav-deskripsi">Pemantauan Tindak Lanjut<br>BPSDM</p>

    {{-- Kontrol lebar menu; area kosong menu dan pintasan [ tetap berfungsi. --}}
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
      {{-- Angka di menu disebut artinya (di rel tulisannya terlalu kecil). --}}
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
          {{-- Di layar sempit tinggal ikon; namanya tetap dibaca pembaca layar. --}}
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
        {{-- Lonceng membuka panel (tanpa skrip: link ke Pemberitahuan). Angkanya =
             pemberitahuan BARU. --}}
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
        {{-- Tombol pengguna membuka menu akun (tanpa skrip: link ke Profil & pengaturan). --}}
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
            @if(count($u->semuaPeran()) > 1)
              <div class="ak-menu-isi ak-peran-pilih">
                <b>Peran aktif</b>
                @foreach($u->semuaPeran() as $p)
                  <form method="post" action="{{ route('akun.peran-aktif') }}">@csrf
                    <input type="hidden" name="peran" value="{{ $p->value }}">
                    <button type="submit" class="ak-butir" @if($p === $peran) disabled aria-current="true" @endif>
                      <x-ikon :n="$p === $peran ? 'Check' : 'ArrowRight'" :s="15" />{{ $p->pendek() }}
                    </button>
                  </form>
                @endforeach
              </div>
            @endif
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

    @include('bagian.pesan')

    @if($peran === P::PIMPINAN && request()->routeIs('ringkasan', 'rekomendasi.*', 'laporan.*'))
      @php $dipimpin = $u->daftarSatkerDipimpin(); @endphp
      <div class="pesan{{ $dipimpin ? '' : ' warn' }}" style="margin:16px 24px 0" role="status">
        <x-ikon n="Building2" :s="16" />
        <span>@if($dipimpin)<b>{{ $dipimpin }}</b> · Data dan proses tindak lanjut Satker yang Anda pimpin.@else<b>Satker belum ditentukan.</b> Hubungi Admin DTI untuk menetapkan Satker yang Anda pimpin.@endif</span>
      </div>
    @endif

    @yield('isi')
  </div>

  @if($s = $bingkai['popup'])
    @include('bagian.pemberitahuan-popup', ['k' => $s['pemberitahuan'], 'jumlah' => $s['jumlah']])
  @endif

  {{-- Peringatan sebelum keluar otomatis (simtlhp-sesi.js). --}}
  <div class="tirai tirai-tanya" data-sesi-peringatan hidden>
    <div class="lembar tanya" role="alertdialog" aria-modal="true" aria-labelledby="sesi-judul" aria-describedby="sesi-ket">
      <div class="kep"><span class="ic-kotak besar kuning"><x-ikon n="Clock" :s="20" /></span><b id="sesi-judul">Sesi akan berakhir</b></div>
      <div class="bdn">
        <p class="apa" id="sesi-ket">Tidak ada aktivitas di akun ini. Anda akan keluar otomatis dalam <b data-sesi-hitung>1:00</b>.</p>
        <div class="pesan warn" style="margin:0"><span>Isian yang belum dikirim atau disimpan sebagai draf akan hilang.</span></div>
      </div>
      <div class="kak">
        <button type="button" class="btn btn-p" data-sesi-lanjut><x-ikon n="Check" :s="15" /> Tetap masuk</button>
        <form method="post" action="{{ route('keluar') }}">@csrf<button type="submit" class="btn"><x-ikon n="LogOut" :s="15" /> Keluar</button></form>
      </div>
    </div>
  </div>
@else
  @yield('isi')
@endauth
</body>
</html>
