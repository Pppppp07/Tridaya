@extends('rangka')
@section('judul', 'Data master')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;
  use App\Enums\SumberLaporan;
  use App\Support\DirektoriIrm;
  use App\Support\Rangka;
  use App\Support\Tampil;

  /* Data master — padanan `LayarMaster` prototipe (27 Sep): satu halaman
     bertab, bukan kartu berlipat yang bertumpuk. Bang Kamal: "Gua tuh
     menghindari banyak scroll." Baris hanya dibaca; menambah dan mengubah lewat
     jendela melayang (`?jendela=`).

     Tidak ada yang dihapus, hanya dinonaktifkan. Nama yang sudah dipakai
     berkas terkunci. Setiap perubahan tercatat — tab Riwayat perubahan. */
  $tanpaPj = $unit->where('aktif', true)->filter(fn ($u) => ! $u->penanggungJawab)->count();
  $temuanSemua = $kategoriTemuan->flatten();
  $tabs = [
    ['k' => 'unit', 'nama' => 'Unit kerja', 'ikon' => 'Building2', 'n' => $unit->count(), 'awas' => $tanpaPj],
    ['k' => 'pengguna', 'nama' => 'Pengguna', 'ikon' => 'Users', 'n' => $akun->where('aktif', true)->count()],
    ['k' => 'temuan', 'nama' => 'Kategori temuan', 'ikon' => 'FileCheck2', 'n' => $temuanSemua->count()],
    ['k' => 'intern', 'nama' => 'Kategori internal', 'ikon' => 'ListChecks', 'n' => $kategori->count()],
    ['k' => 'sifat', 'nama' => 'Sifat rekomendasi', 'ikon' => 'Wallet', 'n' => $sifat->count()],
    ['k' => 'riwayat', 'nama' => 'Riwayat perubahan', 'ikon' => 'History', 'n' => null],
  ];
  $sorot = request('kartu');
  $kata = mb_strtolower($cari);
  $cocok = fn (string $teks) => $kata === '' || str_contains(mb_strtolower($teks), $kata);
  $keadaan = fn (bool $aktif) => '<span class="dm-keadaan'.($aktif ? ' aktif' : '').'">'.($aktif ? 'Aktif' : 'Nonaktif').'</span>';
  $keTab = fn (string $t, array $lain = []) => route('master', ['tab' => $t] + $lain);
  $kosongCari = fn (int $n) => $n === 0 && $cari !== ''
    ? '<tr class="dm-kosong-baris"><td colspan="9" class="dm-kosong">Tidak ada yang cocok dengan &ldquo;'.e($cari).'&rdquo;.</td></tr>' : '';
  /* Sebutan peran di daftar: petugas pusat dengan sebutan pendeknya. */
  $saya = auth()->user();
  $alasanTolak = function ($a) use ($saya, $bolehBeri) {
      return match (true) {
          $a->is($saya) => 'Akun Anda sendiri tidak bisa diubah dari sini.',
          $a->peran === P::SATKER => 'Akun satuan kerja milik penanggung jawab unit kerjanya. Ganti lewat tab Unit kerja.',
          ! in_array($a->peran, $bolehBeri, true) => 'Akun '.$a->peran->pendek().' hanya bisa diatur Admin.',
          default => null,
      };
  };
  $alasanTerakhir = fn ($a) => in_array($a->peran, [P::SETBA, P::ADMIN], true) && $a->aktif
    && ! $akun->contains(fn ($x) => $x->id !== $a->id && $x->peran === $a->peran && $x->aktif)
    ? 'Paling tidak satu akun '.$a->peran->pendek().' harus tetap aktif.' : null;
@endphp

<div class="body master dm">
  <div class="tl-tabs dm-tabs" role="tablist" aria-label="Daftar data master">
    @foreach($tabs as $t)
      <a role="tab" href="{{ $keTab($t['k']) }}" aria-selected="{{ $tab === $t['k'] ? 'true' : 'false' }}"
        @if($tab === $t['k']) aria-current="page" @endif>
        <x-ikon :n="$t['ikon']" :s="14" />{{ $t['nama'] }}
        @if($t['n'] !== null)<span class="dm-n">{{ $t['n'] }}</span>@endif
        @if(! empty($t['awas']))<span class="dm-awas" title="{{ $t['awas'] }} unit kerja belum punya penanggung jawab"
          aria-label="{{ $t['awas'] }} unit kerja belum punya penanggung jawab">{{ $t['awas'] }}</span>@endif
      </a>
    @endforeach
  </div>

  <section class="kartu dm-panel" role="tabpanel" aria-label="{{ collect($tabs)->firstWhere('k', $tab)['nama'] }}">
    @if(session('pesan') && ! $jendela && ! $pilih)
      <div class="pesan ok dm-pesan"><x-ikon n="CheckCircle2" :s="16" /><span>{{ session('pesan') }}</span></div>
    @endif
    @if(session('gagal') && ! $jendela && ! $pilih)
      <div class="pesan bad dm-pesan"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') }}</span></div>
    @endif

    {{-- ======================= UNIT KERJA ======================= --}}
    @if($tab === 'unit')
      @php
        $unitTampil = $unit->filter(fn ($u) => $cocok($u->nama.' '.$u->namaPendek().' '.($u->penanggungJawab?->name ?? '').' '.($u->penanggungJawab?->nip ?? '')));
      @endphp
      <x-dm-kepala judul="Unit kerja" ket="Unit yang bisa ditugasi tindak lanjut, dan satu penanggung jawab tiap unit." :info="[
        'Yang dinonaktifkan tidak lagi ditawarkan saat mencatat laporan baru; pekerjaan lamanya tetap berjalan.',
        'Setiap unit kerja memiliki satu penanggung jawab. Penanggung jawab inilah yang masuk sebagai unit kerja tersebut dan menerima pemberitahuannya, di aplikasi dan lewat email.',
        'Nama, NIP, dan email penanggung jawab diambil sekaligus dari IRM/eHRM, tidak diketik, supaya tidak tertukar dengan milik orang lain. Selama IRM belum tersambung, isinya dibaca dari daftar pegawai contoh.',
        'Nama unit kerja yang sudah dipakai berkas terkunci. Kalau namanya berubah karena penataan organisasi, tambahkan unit kerja baru lalu nonaktifkan yang lama.',
      ]">
        <form method="get" action="{{ route('master') }}" class="dm-cari" role="search" data-filter-baris="dm-t-unit">
          <input type="hidden" name="tab" value="unit">
          <x-ikon n="Search" :s="14" />
          <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari unit atau penanggung jawab" aria-label="Cari unit atau penanggung jawab">
        </form>
        <a class="btn btn-p" href="{{ $keTab('unit', ['jendela' => 'unit']) }}"><x-ikon n="Plus" :s="14" /> Tambah unit kerja</a>
      </x-dm-kepala>
      <div class="dm-ringkas">
        <span><b>{{ $unit->where('aktif', true)->count() }}</b> aktif dari {{ $unit->count() }}</span>
        @if($tanpaPj > 0)
          <span class="awas"><x-ikon n="AlertTriangle" :s="13" /> {{ $tanpaPj }} belum punya penanggung jawab</span>
        @else
          <span class="ok"><x-ikon n="CheckCircle2" :s="13" /> Semua unit aktif punya penanggung jawab</span>
        @endif
      </div>
      <div class="dm-tabel">
        <table id="dm-t-unit">
          <thead><tr><th>Unit kerja</th><th>Penanggung jawab</th><th class="num">Rekomendasi</th><th>Keadaan</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
          <tbody>
            @foreach($unitTampil as $u)
              @php
                $pj = $u->penanggungJawab;
                $pakai = $pakaiUnit[$u->id] ?? null;
                $terkunci = isset($kunciUnit[$u->id]);
                $pastikan = ['judul' => 'Nonaktifkan '.$u->namaPendek().'?',
                  'ket' => ($pakai['jalan'] ?? 0).' rekomendasi yang menugasinya belum selesai. Pekerjaan itu tetap berjalan dan penanggung jawabnya tetap bisa masuk, tapi unit kerja ini tidak lagi ditawarkan saat mencatat laporan baru.',
                  'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false];
              @endphp
              <tr id="unit-{{ $u->id }}" data-cari="{{ $u->nama }} {{ $u->namaPendek() }} {{ $pj?->name }} {{ $pj?->nip }}"
                @class(['mati' => ! $u->aktif, 'disorot' => $sorot === 'unit-'.$u->id])>
                <td class="dm-nama" data-label="Unit kerja">
                  <b>{{ $u->namaPendek() }}@if($terkunci) @include('data-master.kunci', ['ket' => 'Sudah dipakai berkas — nama tidak bisa diganti']) @endif</b>
                  <span>{{ $u->nama }}</span>
                </td>
                <td data-label="Penanggung jawab">
                  <div class="dm-pj">
                    @if($pj)
                      <span class="dm-orang">
                        <b>{{ $pj->name }}</b>
                        <span class="mono">NIP {{ DirektoriIrm::nipTampil($pj->nip) }}</span>
                        <span>{{ $pj->email }}</span>
                      </span>
                    @else
                      <span class="dm-belum"><x-ikon n="AlertTriangle" :s="13" /> Belum ada</span>
                    @endif
                    <a class="btn btn-s{{ $pj ? '' : ' btn-p' }}" href="{{ route('master', ['pj' => $u->id]) }}"
                      aria-label="{{ ($pj ? 'Ganti penanggung jawab ' : 'Pilih penanggung jawab ').$u->namaPendek() }}">{{ $pj ? 'Ganti' : 'Pilih dari IRM' }}</a>
                  </div>
                </td>
                <td class="num" data-label="Rekomendasi">
                  <span class="mono">{{ $pakai['rek'] ?? 0 }}</span>
                  @if(($pakai['jalan'] ?? 0) > 0)<span class="dm-sub">{{ $pakai['jalan'] }} berjalan</span>@endif
                </td>
                <td data-label="Keadaan">{!! $keadaan($u->aktif) !!}</td>
                <td class="dm-aksi">
                  @unless($terkunci)
                    <a class="btn btn-s" href="{{ $keTab('unit', ['jendela' => 'unit-'.$u->id]) }}"><x-ikon n="Pencil" :s="13" /> Ubah</a>
                  @endunless
                  <form method="post" action="{{ route('master.unit.saklar', $u) }}">
                    @csrf
                    <button class="btn btn-s" type="submit"
                      @if($u->aktif && ($pakai['jalan'] ?? 0) > 0) data-pastikan='@json($pastikan)' @endif>{{ $u->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                  </form>
                </td>
              </tr>
            @endforeach
            {!! $kosongCari($unitTampil->count()) !!}
          </tbody>
        </table>
      </div>
    @endif

    {{-- ======================= PENGGUNA ======================= --}}
    @if($tab === 'pengguna')
      @php
        $filter = in_array($filter, ['semua', 'pusat', 'pj', 'nonaktif'], true) ? $filter : 'semua';
        $jmlFilter = [
          'semua' => $akun->count(),
          'pusat' => $akun->filter(fn ($a) => $a->aktif && $a->peran !== P::SATKER)->count(),
          'pj' => $akun->filter(fn ($a) => $a->aktif && $a->peran === P::SATKER)->count(),
          'nonaktif' => $akun->where('aktif', false)->count(),
        ];
        $akunTampil = $akun->filter(fn ($a) => match ($filter) {
            'pusat' => $a->aktif && $a->peran !== P::SATKER,
            'pj' => $a->aktif && $a->peran === P::SATKER,
            'nonaktif' => ! $a->aktif,
            default => true,
          })->filter(fn ($a) => $cocok($a->name.' '.$a->nip.' '.$a->email.' '.$a->peran->pendek().' '.($a->satker?->nama ?? '')));
      @endphp
      <x-dm-kepala judul="Pengguna" ket="Siapa boleh masuk ke aplikasi ini, dan sebagai apa." :info="[
        'Masuk lewat SSO eHRM hanya membuktikan siapa orangnya. Yang boleh masuk hanya yang terdaftar di sini, dikenali dari NIP-nya.',
        'Petugas pusat didaftarkan dari direktori pegawai: nama, NIP, jabatan, dan email diambil dari sana. Penanggung jawab unit kerja diatur di tab Unit kerja.',
        'Setba mengatur akun Setba, UKI, Inspektorat, dan Pimpinan. Akun DTI dan Admin hanya diatur Admin, supaya yang diawasi tidak mengatur pengawasnya.',
        'Akun tidak pernah dihapus. Akun yang dinonaktifkan tidak bisa masuk lagi, sesinya langsung diakhiri, dan riwayat aktivitasnya tetap tercatat.',
      ]">
        <form method="get" action="{{ route('master') }}" class="dm-cari" role="search" data-filter-baris="dm-t-pengguna">
          <input type="hidden" name="tab" value="pengguna">
          @if($filter !== 'semua')<input type="hidden" name="filter" value="{{ $filter }}">@endif
          <x-ikon n="Search" :s="14" />
          <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama, NIP, atau email" aria-label="Cari nama, NIP, atau email">
        </form>
        @if($bolehBeri)
          <a class="btn btn-p" href="{{ $keTab('pengguna', ['jendela' => 'pengguna']) }}"><x-ikon n="UserPlus" :s="14" /> Tambah pengguna</a>
        @endif
      </x-dm-kepala>
      <div class="dm-filter" role="group" aria-label="Filter pengguna">
        @foreach(['semua' => 'Semua', 'pusat' => 'Petugas pusat', 'pj' => 'Penanggung jawab unit', 'nonaktif' => 'Nonaktif'] as $k => $nama)
          <a href="{{ $keTab('pengguna', array_filter(['filter' => $k === 'semua' ? null : $k, 'cari' => $cari ?: null])) }}"
            aria-pressed="{{ $filter === $k ? 'true' : 'false' }}">{{ $nama }} <span class="dm-n">{{ $jmlFilter[$k] }}</span></a>
        @endforeach
      </div>
      <div class="dm-tabel">
        <table id="dm-t-pengguna">
          <thead><tr><th>Nama</th><th>Peran</th><th>Terakhir masuk</th><th>Keadaan</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
          <tbody>
            @foreach($akunTampil as $a)
              @php
                $tolak = $alasanTolak($a);
                $terakhir = $alasanTerakhir($a);
                $pastikan = ['judul' => 'Nonaktifkan akun '.$a->name.'?',
                  'ket' => 'Pengguna tidak bisa masuk lagi dan sesinya langsung diakhiri. Riwayat aktivitasnya tetap tercatat atas namanya. Akun bisa diaktifkan kembali kapan saja.',
                  'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false];
              @endphp
              <tr id="akun-{{ $a->id }}" data-cari="{{ $a->name }} {{ $a->nip }} {{ $a->email }} {{ $a->peran->pendek() }} {{ $a->satker?->nama }}"
                @class(['mati' => ! $a->aktif, 'disorot' => $sorot === 'akun-'.$a->id])>
                <td data-label="Nama">
                  <span class="dm-akun">
                    <span class="rupa" aria-hidden="true">{{ Rangka::inisial($a->name) }}</span>
                    <span class="dm-orang">
                      <b>{{ $a->name }}@if($a->is($saya))<span class="dm-anda">Anda</span>@endif</b>
                      <span class="mono">NIP {{ DirektoriIrm::nipTampil($a->nip) }}</span>
                      <span>{{ $a->email }}</span>
                    </span>
                  </span>
                </td>
                <td data-label="Peran">
                  <span class="dm-peran">{{ $a->peran->pendek() }}</span>
                  @if($a->peran === P::SATKER)
                    <span class="dm-sub">{{ $a->satker?->namaPendek() }}</span>
                  @elseif($a->jabatan)
                    <span class="dm-sub">{{ $a->jabatan }}</span>
                  @endif
                </td>
                <td data-label="Terakhir masuk">@if($a->terakhir_masuk_pada){{ Tampil::waktuLog($a->terakhir_masuk_pada) }}@else<span class="dm-sub">Belum pernah</span>@endif</td>
                <td data-label="Keadaan">{!! $keadaan($a->aktif) !!}</td>
                <td class="dm-aksi">
                  @if($a->peran === P::SATKER && $a->satker)
                    <a class="btn btn-s" href="{{ $keTab('unit', ['kartu' => 'unit-'.$a->satker_id]) }}#unit-{{ $a->satker_id }}"><x-ikon n="Building2" :s="13" /> Buka di Unit kerja</a>
                  @elseif($tolak)
                    <span class="dm-sub" title="{{ $tolak }}"><x-ikon n="Lock" :s="12" /> {{ $a->is($saya) ? 'Akun Anda' : 'Diatur Admin' }}</span>
                  @else
                    @if($a->aktif)
                      <a class="btn btn-s" href="{{ $keTab('pengguna', ['jendela' => 'akun-'.$a->id]) }}"><x-ikon n="Pencil" :s="13" /> Ubah peran</a>
                    @endif
                    <form method="post" action="{{ route('master.pengguna.saklar', $a) }}">
                      @csrf
                      <button class="btn btn-s" type="submit" @disabled($a->aktif && $terakhir)
                        @if($a->aktif && $terakhir) title="{{ $terakhir }}" @endif
                        @if($a->aktif && ! $terakhir) data-pastikan='@json($pastikan)' @endif>{{ $a->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
            {!! $kosongCari($akunTampil->count()) !!}
          </tbody>
        </table>
      </div>
    @endif

    {{-- ======================= KATEGORI TEMUAN ======================= --}}
    @if($tab === 'temuan')
      @php $segmen = in_array($filter, ['LHP', 'LHA'], true) ? $filter : 'LHP'; @endphp
      <x-dm-kepala judul="Kategori temuan" ket="Pilihan kategori saat mencatat temuan, dipisah menurut sumber laporannya." :info="[
        'Yang dinonaktifkan tidak lagi ditawarkan, tapi temuan lama tetap memakainya.',
        'Nama yang sudah dipakai temuan terkunci: temuan lama menyimpan nama itu.',
      ]">
        <a class="btn btn-p" href="{{ $keTab('temuan', ['jendela' => 'temuan', 'sumber' => $segmen, 'filter' => $segmen]) }}"><x-ikon n="Plus" :s="14" /> Tambah kategori</a>
      </x-dm-kepala>
      <div class="dm-filter" role="group" aria-label="Sumber laporan">
        @foreach(['LHP', 'LHA'] as $j)
          <a href="{{ $keTab('temuan', ['filter' => $j]) }}" aria-pressed="{{ $segmen === $j ? 'true' : 'false' }}">{{ $j }} <span class="dm-sub-inline">{{ SumberLaporan::from($j)->nama() }}</span> <span class="dm-n">{{ collect($kategoriTemuan[$j] ?? [])->count() }}</span></a>
        @endforeach
      </div>
      <div class="dm-tabel">
        <table>
          <thead><tr><th>Nama kategori</th><th class="num">Dipakai temuan</th><th>Keadaan</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
          <tbody>
            @foreach(collect($kategoriTemuan[$segmen] ?? []) as $k)
              @php
                $n = (int) ($terpakaiTemuan[$k->id] ?? 0);
                $pastikan = ['judul' => 'Nonaktifkan kategori ini?',
                  'ket' => $n.' temuan masih memakainya. Keterangannya tetap terbaca, tapi kategori ini tidak lagi muncul saat mencatat laporan baru.',
                  'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false];
              @endphp
              <tr id="kt-{{ $k->id }}" @class(['mati' => ! $k->aktif, 'disorot' => $sorot === 'kt-'.$k->id])>
                <td class="dm-nama" data-label="Nama kategori"><b>{{ $k->nama }}@if($n) @include('data-master.kunci', ['ket' => 'Sudah dipakai temuan — nama tidak bisa diganti']) @endif</b></td>
                <td class="num mono" data-label="Dipakai temuan">{{ $n }}</td>
                <td data-label="Keadaan">{!! $keadaan($k->aktif) !!}</td>
                <td class="dm-aksi">
                  @unless($n)
                    <a class="btn btn-s" href="{{ $keTab('temuan', ['jendela' => 'temuan-'.$k->id, 'filter' => $segmen]) }}"><x-ikon n="Pencil" :s="13" /> Ubah</a>
                  @endunless
                  <form method="post" action="{{ route('master.temuan.saklar', $k) }}">
                    @csrf
                    <button class="btn btn-s" type="submit" @if($k->aktif && $n) data-pastikan='@json($pastikan)' @endif>{{ $k->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    {{-- ======================= KATEGORI INTERNAL ======================= --}}
    @if($tab === 'intern')
      <x-dm-kepala judul="Kategori internal" ket="Pengelompokan temuan milik BPSDM sendiri." :info="[
        'Dipakai saat mencatat temuan dan di Dashboard (Per kategori internal).',
        'Tanpa warna: merah dan hijau di aplikasi ini berarti keadaan (belum memadai, memadai), jadi kategori tidak memakai warna supaya tidak terbaca sebagai keadaan.',
        'Nama yang sudah dipakai temuan terkunci. Yang dinonaktifkan tidak lagi ditawarkan, tapi temuan lama tetap memakainya.',
      ]">
        <a class="btn btn-p" href="{{ $keTab('intern', ['jendela' => 'intern']) }}"><x-ikon n="Plus" :s="14" /> Tambah kategori</a>
      </x-dm-kepala>
      <div class="dm-tabel">
        <table>
          <thead><tr><th>Nama kategori</th><th class="num">Dipakai temuan</th><th>Keadaan</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
          <tbody>
            @foreach($kategori as $k)
              @php
                $n = (int) ($terpakai[$k->id] ?? 0);
                $pastikan = ['judul' => 'Nonaktifkan kategori ini?',
                  'ket' => $n.' temuan masih memakainya. Keterangannya tetap terbaca, tapi kategori ini tidak lagi muncul saat mencatat laporan baru.',
                  'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false];
              @endphp
              <tr id="kat-{{ $k->id }}" @class(['mati' => ! $k->aktif, 'disorot' => $sorot === 'kat-'.$k->id])>
                <td class="dm-nama" data-label="Nama kategori"><b>{{ $k->nama }}@if($n) @include('data-master.kunci', ['ket' => 'Sudah dipakai temuan — nama tidak bisa diganti']) @endif</b></td>
                <td class="num mono" data-label="Dipakai temuan">{{ $n }}</td>
                <td data-label="Keadaan">{!! $keadaan($k->aktif) !!}</td>
                <td class="dm-aksi">
                  @unless($n)
                    <a class="btn btn-s" href="{{ $keTab('intern', ['jendela' => 'intern-'.$k->id]) }}"><x-ikon n="Pencil" :s="13" /> Ubah</a>
                  @endunless
                  <form method="post" action="{{ route('master.saklar', $k) }}">
                    @csrf
                    <button class="btn btn-s" type="submit" @if($k->aktif && $n) data-pastikan='@json($pastikan)' @endif>{{ $k->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    {{-- ======================= SIFAT REKOMENDASI ======================= --}}
    @if($tab === 'sifat')
      @php $sifatAktifN = $sifat->where('aktif', true)->count(); @endphp
      <x-dm-kepala judul="Sifat rekomendasi" ket="Pilihan sifat saat mencatat rekomendasi." :info="[
        'Sifat yang menuntut penyetoran membuka isian nilai tiap satuan kerja.',
        'Nama dan keterangan menuntut penyetoran terkunci begitu dipakai rekomendasi: menggantinya mengubah arti rekomendasi yang sudah tercatat.',
        'Paling tidak satu sifat harus tetap aktif, karena formulir rekomendasi butuh pilihan.',
      ]">
        <a class="btn btn-p" href="{{ $keTab('sifat', ['jendela' => 'sifat']) }}"><x-ikon n="Plus" :s="14" /> Tambah sifat</a>
      </x-dm-kepala>
      <div class="dm-tabel">
        <table>
          <thead><tr><th>Nama sifat</th><th>Menuntut penyetoran</th><th class="num">Rekomendasi</th><th>Keadaan</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
          <tbody>
            @foreach($sifat as $x)
              @php
                $dipakai = (int) ($pakaiSifat[$x->id] ?? 0);
                $terakhirSifat = $x->aktif && $sifatAktifN === 1;
              @endphp
              <tr id="sifat-{{ $x->id }}" @class(['mati' => ! $x->aktif, 'disorot' => $sorot === 'sifat-'.$x->id])>
                <td class="dm-nama" data-label="Nama sifat"><b>{{ $x->nama }}@if($dipakai) @include('data-master.kunci', ['ket' => 'Sudah dipakai rekomendasi — tidak bisa diubah']) @endif</b></td>
                <td data-label="Menuntut penyetoran">{{ $x->perlu_nilai ? 'Ya' : 'Tidak' }}</td>
                <td class="num mono" data-label="Rekomendasi">{{ $dipakai }}</td>
                <td data-label="Keadaan">{!! $keadaan($x->aktif) !!}</td>
                <td class="dm-aksi">
                  @unless($dipakai)
                    <a class="btn btn-s" href="{{ $keTab('sifat', ['jendela' => 'sifat-'.$x->id]) }}"><x-ikon n="Pencil" :s="13" /> Ubah</a>
                  @endunless
                  <form method="post" action="{{ route('master.sifat.saklar', $x) }}">
                    @csrf
                    <button class="btn btn-s" type="submit" @disabled($terakhirSifat)
                      @if($terakhirSifat) title="Paling tidak satu sifat harus tetap aktif" @endif>{{ $x->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    {{-- ======================= RIWAYAT PERUBAHAN ======================= --}}
    @if($tab === 'riwayat')
      @php
        $filterR = in_array($filter, ['unit', 'pengguna', 'temuan', 'intern', 'sifat'], true) ? $filter : 'semua';
        $batas = max(30, (int) request('batas', 30));
      @endphp
      <x-dm-kepala judul="Riwayat perubahan" ket="Setiap perubahan data master dan hak akses: siapa, kapan, sebelum dan sesudahnya." :info="[
        'Tidak bisa diubah atau dihapus oleh siapa pun. Seluruh aktivitas lain — perpindahan berkas, masuk dan keluar — ada di Log aktivitas milik DTI.',
      ]">
        <form method="get" action="{{ route('master') }}" class="dm-cari" role="search">
          <input type="hidden" name="tab" value="riwayat">
          @if($filterR !== 'semua')<input type="hidden" name="filter" value="{{ $filterR }}">@endif
          <x-ikon n="Search" :s="14" />
          <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari pelaku atau perubahan" aria-label="Cari pelaku atau perubahan">
        </form>
      </x-dm-kepala>
      <div class="dm-filter" role="group" aria-label="Filter perubahan">
        @foreach(['semua' => 'Semua', 'unit' => 'Unit kerja', 'pengguna' => 'Pengguna & penanggung jawab', 'temuan' => 'Kategori temuan', 'intern' => 'Kategori internal', 'sifat' => 'Sifat'] as $k => $n)
          <a href="{{ $keTab('riwayat', array_filter(['filter' => $k === 'semua' ? null : $k, 'cari' => $cari ?: null])) }}"
            aria-pressed="{{ $filterR === $k ? 'true' : 'false' }}">{{ $n }}</a>
        @endforeach
      </div>
      @if($riwayat->count())
        <div class="dm-tabel">
          <table>
            <thead><tr><th>Waktu</th><th>Oleh</th><th>Perubahan</th></tr></thead>
            <tbody>
              @foreach($riwayat as $x)
                <tr>
                  <td class="dm-waktu" data-label="Waktu">{{ Tampil::waktuLog($x->waktu) }}</td>
                  <td data-label="Oleh"><span class="dm-orang"><b>{{ $x->nama ?: 'Sistem (otomatis)' }}</b><span>{{ $x->sebutanPeran() }}</span></span></td>
                  <td data-label="Perubahan"><span class="dm-ubah">{{ $x->ringkasan }}</span>@include('bagian.beda-log', ['rincian' => $x->rincian])</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="kosong dm-kosong-kotak">{{ $jumlahRiwayat === 0 && $cari === '' && $filterR === 'semua' ? 'Belum ada perubahan data master atau hak akses.' : 'Tidak ada perubahan yang cocok.' }}</div>
      @endif
      @if($jumlahRiwayat > $riwayat->count())
        <div class="dm-lagi">
          <a class="btn" href="{{ $keTab('riwayat', array_filter(['filter' => $filterR === 'semua' ? null : $filterR, 'cari' => $cari ?: null, 'batas' => $batas + 30])) }}">
            Tampilkan lebih banyak ({{ $jumlahRiwayat - $riwayat->count() }} lagi)
          </a>
        </div>
      @endif
    @endif
  </section>
</div>

{{-- ======================= JENDELA ======================= --}}
@if($jendela)
  @php $tutup = $keTab($tab, array_filter(['filter' => $filter ?: null])); @endphp
  @switch($jendela['jenis'])
    @case('unit')
      @php $x = $jendela['x']; @endphp
      <x-dm-jendela :judul="$x ? 'Ubah '.$x->namaPendek() : 'Tambah unit kerja'" ikon="Building2" :tutup="$tutup" wajib
        :sub="$x ? 'Nama hanya bisa diganti selama belum dipakai berkas.' : 'Penanggung jawabnya dipilih dari IRM sesudah unitnya ada.'"
        :aksi="$x ? route('master.unit.simpan', $x) : route('master.unit.tambah')">
        <label class="fld">
          <span>Nama lengkap<span class="wajib">*</span></span>
          <input type="text" name="nama" value="{{ old('nama', $x?->nama) }}" required autofocus
            placeholder="mis. Balai Pengembangan Kompetensi PUPR Wilayah X Kupang">
        </label>
        <label class="fld" style="margin-bottom:0">
          <span>Nama pendek</span>
          <input type="text" name="pendek" value="{{ old('pendek', $x?->nama_pendek) }}" placeholder="mis. Balai Wil. X Kupang">
          <span class="hint">Dipakai di tabel dan kolom sempit. Kosong = sama dengan nama lengkap.</span>
        </label>
      </x-dm-jendela>
      @break

    @case('pengguna')
      @php $p = $jendela['pilih']; $c = $jendela['cari']; @endphp
      @if(! $p)
        <x-dm-jendela judul="Tambah pengguna" ikon="UserPlus" :tutup="$tutup" lebar sub="Cari pegawainya di direktori dengan nama atau NIP.">
          <form method="get" action="{{ route('master') }}" class="cari-irm" data-cari-pegawai data-sumber="{{ route('master.pengguna.cari') }}">
            <x-ikon n="Search" :s="15" />
            <input type="hidden" name="tab" value="pengguna">
            <input type="hidden" name="jendela" value="pengguna">
            <input type="text" name="q" value="{{ $c['q'] }}" placeholder="Ketik nama atau NIP" aria-label="Cari pegawai di direktori" autocomplete="off" autofocus>
          </form>
          <div data-hasil-pegawai>@include('data-master.hasil-pegawai', $c)</div>
        </x-dm-jendela>
      @else
        @php $ada = $akun->firstWhere('nip', $p['nip']); @endphp
        <x-dm-jendela judul="Tambah pengguna" ikon="UserPlus" :tutup="$tutup" lebar wajib sub="Pilih perannya."
          :aksi="route('master.pengguna.tambah')" tombol="Tambahkan">
          <input type="hidden" name="nip" value="{{ $p['nip'] }}">
          <div class="dm-terpilih">
            <span class="rupa" aria-hidden="true">{{ Rangka::inisial($p['nama']) }}</span>
            <span class="dm-orang">
              <b>{{ $p['nama'] }}</b>
              <span class="mono">NIP {{ DirektoriIrm::nipTampil($p['nip']) }}</span>
              <span>{{ $p['jabatan'] }} · {{ $p['unitNama'] }}</span>
              <span>{{ $p['email'] }}</span>
            </span>
            <a class="btn btn-s" href="{{ $keTab('pengguna', ['jendela' => 'pengguna', 'q' => $c['q'] ?: $p['nama']]) }}">Ganti orang</a>
          </div>
          @if($ada && $ada->aktif)
            <div class="pesan warn" style="margin-top:0"><x-ikon n="AlertTriangle" :s="16" /><span>{{ $p['nama'] }} sudah punya akun sebagai {{ $ada->peran->pendek() }}.</span></div>
          @endif
          <div class="fld" style="margin-bottom:0">
            <span>Peran<span class="wajib">*</span></span>
            @include('data-master.pilihan-peran', ['nilai' => old('peran', ''), 'bolehBeri' => $bolehBeri])
          </div>
        </x-dm-jendela>
      @endif
      @break

    @case('akun')
      @php $a = $jendela['x']; @endphp
      @if(! $alasanTolak($a))
        <x-dm-jendela :judul="'Ubah peran '.$a->name" ikon="Users" :tutup="$tutup" lebar
          :sub="'NIP '.DirektoriIrm::nipTampil($a->nip).' · '.$a->email" :aksi="route('master.pengguna.simpan', $a)" tombol="Simpan peran"
          :data-pastikan="json_encode(['judul' => 'Ubah peran '.$a->name.'?', 'ket' => 'Sesi yang sedang berjalan akan diakhiri, sehingga pengguna perlu masuk ulang dengan hak akses barunya.', 'tombol' => 'Ya, ubah', 'nada' => 'jingga', 'balik' => false])">
          @include('data-master.pilihan-peran', ['nilai' => old('peran', $a->peran->value), 'bolehBeri' => $bolehBeri])
        </x-dm-jendela>
      @endif
      @break

    @case('temuan')
      @php $k = $jendela['x']; $sumber = $k ? $k->sumber->value : $jendela['sumber']; @endphp
      <x-dm-jendela :judul="$k ? 'Ubah kategori temuan' : 'Tambah kategori temuan'" ikon="FileCheck2" :tutup="$tutup" wajib
        :aksi="$k ? route('master.temuan.simpan', $k) : route('master.temuan.tambah')">
        @if(! $k)
          <div class="fld">
            <span>Sumber laporan<span class="wajib">*</span></span>
            <div class="dm-pilih">
              @foreach(['LHP', 'LHA'] as $j)
                <label class="dm-opsi{{ old('sumber', $sumber) === $j ? ' pilih' : '' }}">
                  <input type="radio" name="sumber" value="{{ $j }}" @checked(old('sumber', $sumber) === $j)>
                  <b>{{ $j }}</b><span>{{ SumberLaporan::from($j)->nama() }}</span>
                </label>
              @endforeach
            </div>
          </div>
        @else
          <p class="dm-sub" style="margin-top:0">Sumber laporan: <b>{{ $sumber }}</b> · {{ SumberLaporan::from($sumber)->nama() }}</p>
        @endif
        <label class="fld" style="margin-bottom:0">
          <span>Nama kategori<span class="wajib">*</span></span>
          <input type="text" name="nama" value="{{ old('nama', $k?->nama) }}" required autofocus>
        </label>
      </x-dm-jendela>
      @break

    @case('intern')
      @php $k = $jendela['x']; @endphp
      <x-dm-jendela :judul="$k ? 'Ubah kategori internal' : 'Tambah kategori internal'" ikon="ListChecks" :tutup="$tutup" wajib
        :aksi="$k ? route('master.simpan', $k) : route('master.tambah')">
        <label class="fld" style="margin-bottom:0">
          <span>Nama kategori<span class="wajib">*</span></span>
          <input type="text" name="nama" value="{{ old('nama', $k?->nama) }}" required autofocus>
        </label>
      </x-dm-jendela>
      @break

    @case('sifat')
      @php $x = $jendela['x']; @endphp
      <x-dm-jendela :judul="$x ? 'Ubah sifat rekomendasi' : 'Tambah sifat rekomendasi'" ikon="Wallet" :tutup="$tutup" wajib
        :aksi="$x ? route('master.sifat.simpan', $x) : route('master.sifat.tambah')">
        <label class="fld">
          <span>Nama sifat<span class="wajib">*</span></span>
          <input type="text" name="nama" value="{{ old('nama', $x?->nama) }}" required autofocus>
        </label>
        <input type="hidden" name="uang" value="0">
        <label class="centang">
          <input type="checkbox" name="uang" value="1" @checked(old('uang', $x?->perlu_nilai))>
          Menuntut penyetoran uang
        </label>
      </x-dm-jendela>
      @break
  @endswitch
@endif

{{-- Jendela memilih penanggung jawab dari IRM — padanan `PilihPenanggungJawab`.
     Digambar di server (`?pj=`), jadi tetap jalan tanpa skrip; skrip cuma
     mencarikan sambil mengetik dan menutupnya dengan Escape. --}}
@if($pilih)
  @php
    $u = $pilih['unit'];
    $tutup = route('master', ['tab' => 'unit', 'kartu' => 'unit-'.$u->id]);
  @endphp
  <div class="tirai" data-tirai-pj data-tutup="{{ $tutup }}">
    <div class="lembar pilihpj" role="dialog" aria-modal="true" aria-label="Penanggung jawab {{ $u->namaPendek() }}">
      <div class="kep">
        <span class="ic-kotak biru"><x-ikon n="Users" :s="17" /></span>
        <span class="judul">
          <b>Penanggung jawab {{ $u->namaPendek() }}</b>
          <span>Cari di IRM dengan nama atau NIP. Nama, NIP, dan email terisi otomatis.</span>
        </span>
        <a class="tutup" href="{{ $tutup }}" aria-label="Tutup"><x-ikon n="X" :s="16" /></a>
      </div>
      <div class="bdn">
        @if(session('gagal'))
          <div class="pesan bad" style="margin-top:0"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') }}</span></div>
        @endif
        <form method="get" action="{{ route('master') }}" class="cari-irm" data-cari-irm
          data-sumber="{{ route('master.unit.irm', $u) }}">
          <x-ikon n="Search" :s="15" />
          <input type="hidden" name="pj" value="{{ $u->id }}">
          <input type="text" name="q" value="{{ $pilih['q'] }}" placeholder="Ketik nama atau NIP"
            aria-label="Cari pegawai di IRM" autocomplete="off" autofocus>
        </form>
        <div data-hasil-irm>@include('data-master.hasil-irm', $pilih)</div>
      </div>
    </div>
  </div>
@endif
@endsection
