@php
  use App\Support\Tampil;

  /* Masalah administrasi terpisah dari indikasi keamanan berbasis log. */
  $unitAktif = $unit->where('aktif', true)->count();
  $kartu = [
    ['j' => 'User aktif', 'n' => $jmlAkun['aktif'],
      'sub' => $jmlAkun['pusat'].' petugas pusat · '.$jmlAkun['pj'].' penanggung jawab', 'tab' => 'pengguna', 'ikon' => 'Users'],
    ['j' => 'Satuan kerja aktif', 'n' => $unitAktif,
      'sub' => 'dari '.$unit->count().' satuan kerja terdaftar', 'tab' => 'unit', 'ikon' => 'Building2'],
    ['j' => 'Referensi aktif', 'n' => array_sum($aktifKategori),
      'sub' => $aktifKategori['temuan'].' temuan · '.$aktifKategori['intern'].' internal · '.$aktifKategori['sifat'].' sifat · '.$aktifKategori['bentuk'].' bentuk', 'tab' => 'temuan', 'ikon' => 'ListChecks'],
  ];
  $mendesak = array_values(array_filter($perluDiurus, fn ($x) => $x['tingkat'] === 'awas'));
  /* Tombol Buka: tab tujuan beserta filternya. */
  $tujuan = function (array $x) use ($keTab) {
    $t = $x['tuju']['tab'];
    $s = $x['tuju']['saring'] ?? [];
    if (in_array($t, ['temuan', 'intern', 'sifat', 'bentuk'], true)) {
      $s = isset($s['segmen']) ? ['filter' => $s['segmen']] : [];
    }

    return $keTab($t, $s);
  };
@endphp
<x-dm-kepala judul="Dashboard" ket="Kelola user, satuan kerja, dan referensi." :info="[
  'Ringkasan jumlah user, satuan kerja, dan referensi aktif, beserta data yang perlu dilengkapi dan perubahan terbaru.',
  'Total user menghitung setiap akun sekali. Kelompok petugas pusat dan penanggung jawab dapat memuat akun yang sama jika memiliki beberapa peran.',
]" />
@if($mendesak)
  <div class="dm-bagian dm-prioritas">
    <h3>Perlu ditangani <span class="dm-n">{{ count($mendesak) }}</span></h3>
    <ul class="dm-urus">
      @foreach($mendesak as $x)
        <li class="{{ $x['tingkat'] }}">
          <span class="dm-urus-ikon" aria-hidden="true"><x-ikon :n="$x['tingkat'] === 'awas' ? 'AlertTriangle' : 'CircleDot'" :s="16" /></span>
          <span class="dm-urus-isi"><b>{{ $x['judul'] }}</b><span>{{ $x['ket'] }}</span></span>
          <a class="btn btn-s" href="{{ $tujuan($x) }}">Tinjau <x-ikon n="ArrowRight" :s="13" /></a>
        </li>
      @endforeach
    </ul>
  </div>
@endif

<div class="dm-kartu-ringkas">
  @foreach($kartu as $k)
    <a class="dm-stat" href="{{ $keTab($k['tab']) }}">
      <span><x-ikon :n="$k['ikon']" :s="16" /> {{ $k['j'] }} <x-ikon n="ArrowRight" :s="14" /></span>
      <b>{{ $k['n'] }}</b><small>{{ $k['sub'] }}</small>
    </a>
  @endforeach
</div>

<div class="dm-status-ringkas {{ $mendesak ? 'dm-status-tunggal' : '' }}">
  @unless($mendesak)
    <div class="dm-status-item dm-status-ok">
      <x-ikon n="CheckCircle2" :s="18" />
      <div><b>Peran & penanggung jawab lengkap</b></div>
      <x-info teks="Tidak ada kekurangan penetapan peran utama, penanggung jawab Satker aktif, atau cakupan Satker Pimpinan pada pemeriksaan kelengkapan saat ini." />
    </div>
  @endunless
  <div class="dm-status-item {{ $jumlahAlert ? 'dm-status-awas' : '' }}">
    <x-ikon :n="$jumlahAlert ? 'AlertTriangle' : 'ShieldCheck'" :s="18" />
    <div><b>Alert keamanan @if($jumlahAlert)<span class="dm-awas">{{ $jumlahAlert }}</span>@endif</b>
      <span>{{ $jumlahAlert ? 'Perlu ditinjau.' : 'Tidak ada alert terbuka.' }}</span>
    </div>
    <x-info :teks="[
      'Jumlah alert yang masih terbuka dan perlu ditinjau. Alert menunjukkan aktivitas yang memenuhi aturan pemantauan, bukan bukti pelanggaran.',
      'Buka alert untuk melihat aktivitas pemicu, bukti, dan hasil tinjauannya.',
    ]" />
    <a class="btn btn-s" href="{{ route('log', ['tab' => 'alert', 'status' => 'terbuka']) }}">{{ $jumlahAlert ? 'Tinjau' : 'Buka' }} <x-ikon n="ArrowRight" :s="13" /><span class="sr-only"> alert keamanan</span></a>
  </div>
</div>

<div class="dm-bagian">
  <div class="dm-bagian-kep dm-log-kep">
    <div><h3>Log terbaru</h3><span>{{ $terbaru->count() }} terakhir · Semua waktu</span></div>
    <a class="btn btn-s" href="{{ $keTab('riwayat') }}">Lihat semua ({{ $totalRiwayat }}) <x-ikon n="ArrowRight" :s="13" /><span class="sr-only"> log admin</span></a>
  </div>
  @if($terbaru->count())
    <div class="dm-tabel dm-tabel-dalam dm-admin-log" tabindex="0" role="region" aria-label="Log terbaru, geser untuk melihat semua kolom">
      <table>
        <thead>@include('data-master.log-kolom')</thead>
        <tbody>
          @foreach($terbaru as $x)
            @include('bagian.jejak-administrasi', ['x' => $x])
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="dm-sub dm-kosong-teks">Belum ada perubahan data master atau hak akses.</p>
  @endif
</div>
