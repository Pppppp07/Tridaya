@extends('rangka')
@section('judul', 'Data Master')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;

  /* Data master milik Admin DTI: satu halaman bertab. */
  $tabs = [
    ['k' => 'ringkasan', 'nama' => 'Dashboard', 'ikon' => 'Gauge', 'n' => null, 'awas' => $jumlahAwas, 'ketAwas' => 'hal yang perlu ditangani'],
    ['k' => 'pengguna', 'nama' => 'User & akses', 'ikon' => 'Users', 'n' => null],
    ['k' => 'unit', 'nama' => 'Satuan kerja', 'ikon' => 'Building2', 'n' => null, 'awas' => $tanpaPj, 'ketAwas' => 'satuan kerja belum punya penanggung jawab aktif'],
    ['k' => 'kategori', 'nama' => 'Kategori & referensi', 'ikon' => 'ListChecks', 'n' => null],
    ['k' => 'sumber', 'nama' => 'Sumber bukti', 'ikon' => 'ShieldCheck', 'n' => null],
    ['k' => 'riwayat', 'nama' => 'Log admin', 'ikon' => 'History', 'n' => null],
  ];
  /* Empat daftar referensi satu tab di deret; alamatnya sendiri-sendiri (?tab=temuan|intern|sifat|bentuk). */
  $tabAktif = in_array($tab, ['temuan', 'intern', 'sifat', 'bentuk'], true) ? 'kategori' : ($tab === 'peran' ? 'pengguna' : $tab);
  $namaTab = collect($tabs)->firstWhere('k', $tabAktif)['nama'];

  $bersih = fn (array $p) => array_filter($p, fn ($v) => $v !== null && $v !== '' && $v !== false);
  /* Alamat tab ini dengan parameter tambahan (null = lepaskan), dan alamat tab lain. */
  $ke = fn (array $lain = []) => route('master', $bersih(array_merge($param, $lain)));
  $keTab = fn (string $t, array $lain = []) => route('master', $bersih(['tab' => $t] + $lain));
  /* Alamat daftar ini tanpa jendela — tujuan tombol Tutup dan "kembali" formulir. */
  $tutup = $ke();
  $kembali = route('master', $param, false);
  $sorot = request('kartu');
  $saya = auth()->user();

  $keadaan = fn (bool $aktif, bool $izin = false) => '<span class="dm-keadaan'.($aktif ? ' aktif' : '').'">'.($izin ? ($aktif ? 'Diizinkan' : 'Dibatasi') : ($aktif ? 'Aktif' : 'Nonaktif')).'</span>';
  $kosongCari = fn (int $n) => $n === 0 && $cari !== ''
    ? '<tr class="dm-kosong-baris"><td colspan="9" class="dm-kosong">Tidak ada yang cocok dengan &ldquo;'.e($cari).'&rdquo;.</td></tr>' : '';
  /* Kolom yang bisa diurutkan: sekali naik, lagi turun, lagi kembali. Rekomendasi dimulai dari
     terbanyak. */
  $kolomUrut = function (string $k, string $nama, string $kelas = '') use ($f, $ke) {
    $sini = $f['urut'] === $k;
    $tujuan = ! $sini ? ['urut' => $k, 'arah' => $k === 'rekomendasi' ? 'turun' : 'naik']
      : ($f['arah'] === 'naik' && $k !== 'rekomendasi' ? ['urut' => $k, 'arah' => 'turun']
        : ($f['arah'] === 'turun' && $k === 'rekomendasi' ? ['urut' => $k, 'arah' => 'naik'] : ['urut' => null, 'arah' => null]));
    $sortir = $sini ? ($f['arah'] === 'naik' ? 'ascending' : 'descending') : 'none';
    $panah = $sini ? ($f['arah'] === 'naik' ? '↑' : '↓') : '⇅';
    $judul = ! $sini ? 'Urutkan menurut '.mb_strtolower($nama) : 'Tekan lagi untuk membalik, lalu untuk mengembalikan';

    return '<th'.($kelas ? ' class="'.$kelas.'"' : '').' aria-sort="'.$sortir.'"><a class="dm-urut-kolom" href="'.e($ke($tujuan)).'" title="'.e($judul).'">'
      .e($nama).'<span class="dm-panah" aria-hidden="true">'.$panah.'</span></a></th>';
  };

  $alasanTolak = fn ($a) => match (true) {
    $a->is($saya) => 'Akun Anda sendiri tidak bisa diubah dari sini.',
    $a->peran === P::SATKER => null,
    ! in_array($a->peran, $bolehBeri, true) => 'Akun '.$a->peran->pendek().' hanya bisa diatur Admin DTI.',
    default => null,
  };
  /* Setba dan Admin DTI terakhir yang aktif tidak boleh hilang. */
  $alasanTerakhir = function ($a) use ($akun) {
    foreach ([P::SETBA, P::ADMIN] as $p) {
      if ($a->aktif && $a->punyaPeran($p) && ! $akun->contains(fn ($x) => $x->id !== $a->id && $x->punyaPeran($p) && $x->aktif)) {
        return 'Minimal satu akun '.$p->pendek().' harus tetap aktif.';
      }
    }
    return null;
  };
@endphp

<div data-master-akar>
<div class="body master dm {{ $tab === 'ringkasan' ? 'dm-overview' : '' }}">
  <div class="tl-tabs dm-tabs" role="tablist" aria-label="Menu Data Master">
    @foreach($tabs as $t)
      <a role="tab" id="dm-tab-{{ $t['k'] }}" href="{{ $keTab($t['k'] === 'kategori' ? 'temuan' : $t['k']) }}"
        aria-selected="{{ $tabAktif === $t['k'] ? 'true' : 'false' }}" @if($tabAktif === $t['k']) aria-current="page" @endif>
        <x-ikon :n="$t['ikon']" :s="14" />{{ $t['nama'] }}
        @if($t['n'] !== null)<span class="dm-n">{{ $t['n'] }}</span>@endif
        @if(! empty($t['awas']))<span class="dm-awas" title="{{ $t['awas'] }} {{ $t['ketAwas'] }}"
          aria-label="{{ $t['awas'] }} {{ $t['ketAwas'] }}">{{ $t['awas'] }}</span>@endif
      </a>
    @endforeach
  </div>

  <section class="kartu dm-panel" role="tabpanel" aria-labelledby="dm-tab-{{ $tabAktif }}">
    @if($tabAktif === 'pengguna')
      <nav class="dm-subnav" aria-label="User dan hak akses">
        <a href="{{ $keTab('pengguna') }}" @if($tab === 'pengguna') aria-current="page" @endif><x-ikon n="Users" :s="14" /> Daftar user</a>
        <a href="{{ $keTab('peran') }}" @if($tab === 'peran') aria-current="page" @endif><x-ikon n="ShieldCheck" :s="14" /> Panduan peran</a>
      </nav>
    @endif
    @if(session('pesan') && ! $jendela && ! $pilih)
      <div class="pesan ok dm-pesan" role="status"><x-ikon n="CheckCircle2" :s="16" /><span>{{ session('pesan') }}</span>
        <button type="button" class="dm-pesan-tutup" data-tutup-pesan hidden aria-label="Tutup pesan"><x-ikon n="X" :s="14" /></button></div>
    @endif
    @if(session('gagal') && ! $jendela && ! $pilih)
      <div class="pesan bad dm-pesan" role="status"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') }}</span>
        <button type="button" class="dm-pesan-tutup" data-tutup-pesan hidden aria-label="Tutup pesan"><x-ikon n="X" :s="14" /></button></div>
    @endif

    @switch($tab)
      @case('ringkasan') @include('data-master.ringkasan') @break
      @case('pengguna') @include('data-master.pengguna') @break
      @case('peran') @include('data-master.peran') @break
      @case('unit') @include('data-master.unit') @break
      @case('temuan')
      @case('intern')
      @case('sifat')
      @case('bentuk') @include('data-master.kategori') @break
      @case('riwayat') @include('data-master.riwayat') @break
      @case('sumber') @include('data-master.sumber-bukti') @break
    @endswitch
  </section>
</div>

@if($jendela)
  @include('data-master.jendela')
@endif
@if($pilih)
  @include('data-master.pilih-pj')
@endif
</div>
@endsection
