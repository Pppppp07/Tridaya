{{-- Status versi BPSDM dan SIPTL (jumlah dan nilai) dalam satu matriks. Klik potongan memfilter
     keadaan itu. --}}
@php
  use App\Enums\HasilTelaah;
  use App\Enums\SumberLaporan;
  use App\Support\Dasbor;

  $bd = $d['banding'];
  $bagi = fn ($v, $tot) => $persen($v, $tot).'%';
  $pilihUnor = $f['hasil'] !== '' ? [$f['hasil']] : [];
  $kataLhp = $kecil(HasilTelaah::M->nama(SumberLaporan::LHP));
  /* Di kolom nilai: memadai = nilai diakui, belum = sisa. */
  $isiUnor = array_map(fn ($x) => ['k' => $x, 'nama' => $x === 'M' ? $kataM : $kataBM,
    'sebutRp' => $x === 'M' ? 'diakui' : 'sisa', 'n' => $bd['unor'][$x]['n'], 'rp' => $bd['unor'][$x]['rp'],
    'warna' => $W[$x], 'tulisan' => Dasbor::TULISAN_DI[$x]], ['M', 'BM']);
  $isiBpk = array_map(fn ($x) => ['k' => $x, 'pendek' => $x, 'nama' => $x.' · '.$STATUS($x),
    'n' => $bd['bpk'][$x]['n'], 'rp' => $bd['bpk'][$x]['rp'], 'warna' => $W[$x], 'tulisan' => Dasbor::TULISAN_DI[$x]],
    Dasbor::URUT_BPK);
  /* Belum diunggah: potongan abu di baris SIPTL, bukan status BPK. */
  $belumTampil = $bd['belumUnggah']['n'] > 0;
  if ($belumTampil) {
    $isiBpk[] = ['k' => 'belumUnggah', 'pendek' => 'belum diunggah', 'nama' => 'Belum diunggah',
      'n' => $bd['belumUnggah']['n'], 'rp' => $bd['belumUnggah']['rp'], 'warna' => $W['belumUnggah'], 'tulisan' => Dasbor::TULISAN_DI['belumUnggah']];
  }
  $nBpk = array_sum(array_column($isiBpk, 'n'));
  $totRpUnor = array_sum(array_column($isiUnor, 'rp'));
  $totRpBpk = array_sum(array_column($isiBpk, 'rp'));
  $sisaBpkBanding = array_sum(array_column(array_filter($isiBpk, fn ($x) => ! in_array($x['k'], ['SS', 'belumUnggah'], true)), 'rp'));
  /* Asal yang memadai di BPSDM: sudah di SIPTL, belum diunggah, LHA, atau lainnya. */
  $bagianM = array_values(array_filter([
    ['n' => $bd['mSiptl'], 't' => 'sudah di SIPTL', 'beda' => $bd['beda']],
    ['n' => $bd['belumUnggah']['n'], 't' => 'belum diunggah'],
    ['n' => $bd['mLha'], 't' => 'dari LHA (tidak masuk SIPTL)'],
    ['n' => $bd['unor']['M']['n'] - $bd['mSiptl'] - $bd['belumUnggah']['n'] - $bd['mLha'], 't' => 'lainnya'],
  ], fn ($p) => $p['n'] > 0));
  $kalimatM = '';
  if ($bagianM) {
    $teksM = array_map(fn ($p) => '<b>'.$p['n'].'</b> '.e($p['t']).(! empty($p['beda']) ? ' (<b>'.$p['beda'].'</b> belum SS)' : ''), $bagianM);
    $akhirM = array_pop($teksM);
    $kalimatM = 'Dari <b>'.$bd['unor']['M']['n'].'</b> yang '.e($kecil($kataM)).' di BPSDM: '
      .($teksM ? implode(', ', $teksM).' dan ' : '').$akhirM.'.';
  }
  $tabelOn = in_array('status', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-status" kelas="dsb-lebar2" judul="Status BPSDM dan SIPTL"
  :info="['Perbandingan hasil verifikasi Inspektorat (BPSDM) dengan penilaian BPK (SIPTL), dihitung per penugasan Satker.',
    'SIPTL hanya mencakup penugasan yang sudah diunggah. Hasil kedua pemeriksa dapat berbeda.',
    'Belum diunggah: sudah memadai, tapi unggahannya ke SIPTL belum dicatat Setba.']"
  :kosong="! $bd['n'] ? 'Tidak ada tindak lanjut pada pilihan ini.' : null"
  kunci-tabel="status" :tabel="$tabelOn">
  @if(($belumTampil || $bd['mLha']) && $kalimatM !== '')
    <x-slot:wawasan>{!! $kalimatM !!}</x-slot:wawasan>
  @elseif($bd['nSiptl'])
    <x-slot:wawasan>
      @if($bd['beda'] > 0)<b>{{ $bd['beda'] }}</b> tindak lanjut sudah {{ $kataLhp }} di BPSDM, tapi belum SS di SIPTL.@else Tidak ada selisih antara BPSDM dan SIPTL.@endif
    </x-slot:wawasan>
  @endif
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Status'], ['nama' => 'Jumlah', 'num' => true], ['nama' => '%', 'num' => true], ['nama' => 'Nilai', 'num' => true], ['nama' => '% nilai', 'num' => true]]"
      :baris="array_merge(
        array_map(fn ($x) => ['BPSDM · '.$x['nama'], $x['n'], $bagi($x['n'], $bd['n']), $rpk($x['rp']), $bagi($x['rp'], $totRpUnor)], $isiUnor),
        $nBpk ? array_map(fn ($x) => ['SIPTL · '.$x['nama'], $x['n'], $bagi($x['n'], $nBpk), $rpk($x['rp']), $bagi($x['rp'], $totRpBpk)], $isiBpk) : []
      )" />
  </x-slot:isiTabel>

  <div class="dsb-matriks">
    <span class="mk-sudut" aria-hidden="true"></span>
    <span class="mk-kolom">Jumlah</span>
    <span class="mk-kolom">Nilai (Rp) <x-info teks="Nilai kewajiban menurut hasil masing-masing pemeriksa. Sisa nilai adalah bagian yang belum diakui, bukan jumlah pemulihan yang belum dilakukan." /></span>

    {{-- Kepala baris = versinya dan kedua totalnya. --}}
    <div class="mk-versi"><b>BPSDM <x-info :teks="$INFO_BPSDM" /></b>
      <span>{{ $bd['n'] }} tindak lanjut</span><span>{{ $rpk($totRpUnor) }}</span></div>
    @include('ringkasan.panel.sel-banding', ['label' => 'BPSDM, jumlah tindak lanjut', 'ket' => 'Jumlah', 'isi' => $isiUnor,
      'uang' => false, 'pilih' => $pilihUnor, 'aksi' => 'hasil'])
    @include('ringkasan.panel.sel-banding', ['label' => 'BPSDM, nilai tindak lanjut', 'ket' => 'Nilai (Rp)', 'isi' => $isiUnor,
      'uang' => true, 'pilih' => $pilihUnor, 'aksi' => 'hasil'])

    {{-- SIPTL memuat tindak lanjut yang sudah diunggah, ditambah yang belum diunggah (potongan abu). --}}
    <div class="mk-versi"><b>SIPTL</b>
      @if($bd['nSiptl'])<span>{{ $bd['nSiptl'] }} tindak lanjut</span>@endif
      @if($belumTampil)<span>+ {{ $bd['belumUnggah']['n'] }} belum diunggah</span>@endif
      @if($nBpk)<span>{{ $rpk($totRpBpk) }}</span>@endif</div>
    @if($nBpk)
      @include('ringkasan.panel.sel-banding', ['label' => 'SIPTL, jumlah tindak lanjut', 'ket' => 'Jumlah', 'isi' => $isiBpk,
        'uang' => false, 'pilih' => $f['bpk'], 'aksi' => 'bpk'])
      @include('ringkasan.panel.sel-banding', ['label' => 'SIPTL, nilai tindak lanjut', 'ket' => 'Nilai (Rp)', 'isi' => $isiBpk,
        'uang' => true, 'pilih' => $f['bpk'], 'aksi' => 'bpk'])
    @else
      <div class="dsb-catatan-bb mk-penuh">Belum ada data SIPTL.</div>
    @endif
  </div>
  {{-- Dua sisa berdampingan: SIPTL dan verifikasi Itjen. --}}
  <p class="kaki-bb">
    Sisa: <b>{{ $rpk($bd['unor']['BM']['rp']) }}</b> di BPSDM
    @if($bd['nSiptl']) · <b>{{ $rpk($sisaBpkBanding) }}</b> di SIPTL @endif
  </p>
</x-dsb-panel>
