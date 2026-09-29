{{-- Status versi BPSDM dan versi SIPTL, jumlah dan nilainya, dalam SATU
     matriks: versi di baris, ukuran di kolom — seperti blok "STATUS
     REKOMENDASI SIPTL" dan "STATUS REKOMENDASI UNOR" di lembar Excel mereka.
     Klik potongannya memfilter keadaan itu. --}}
@php
  use App\Enums\HasilTelaah;
  use App\Enums\SumberLaporan;
  use App\Support\Dasbor;

  $bd = $d['banding'];
  $bagi = fn ($v, $tot) => $persen($v, $tot).'%';
  $pilihUnor = $f['hasil'] !== '' ? [$f['hasil']] : [];
  $kataLhp = $kecil(HasilTelaah::M->nama(SumberLaporan::LHP));
  /* Di kolom nilai, bagian yang memadai adalah nilai yang sudah DIAKUI dan
     yang belum adalah SISA-nya — sebutan yang sama dengan kartu Sisa nilai. */
  $isiUnor = array_map(fn ($x) => ['k' => $x, 'nama' => $x === 'M' ? $kataM : $kataBM,
    'sebutRp' => $x === 'M' ? 'diakui' : 'sisa', 'n' => $bd['unor'][$x]['n'], 'rp' => $bd['unor'][$x]['rp'],
    'warna' => $W[$x], 'tulisan' => Dasbor::TULISAN_DI[$x]], ['M', 'BM']);
  $isiBpk = array_map(fn ($x) => ['k' => $x, 'pendek' => $x, 'nama' => $x.' · '.$STATUS($x),
    'n' => $bd['bpk'][$x]['n'], 'rp' => $bd['bpk'][$x]['rp'], 'warna' => $W[$x], 'tulisan' => Dasbor::TULISAN_DI[$x]],
    array_values(array_filter(Dasbor::URUT_BPK, fn ($x) => $x !== 'TD' || $bd['bpk']['TD']['n'] > 0)));
  $totRpUnor = array_sum(array_column($isiUnor, 'rp'));
  $totRpBpk = array_sum(array_column($isiBpk, 'rp'));
  $sisaBpkBanding = array_sum(array_column(array_filter($isiBpk, fn ($x) => $x['k'] !== 'SS'), 'rp'));
  $tabelOn = in_array('status', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-status" kelas="dsb-lebar2" judul="Status BPSDM dan SIPTL"
  :info="['BPSDM: hasil verifikasi Inspektorat atas tiap tindak lanjut satuan kerja.',
    'SIPTL: putusan BPK, hanya LHP. Bisa tertinggal dari BPSDM karena satu rekomendasi BPK juga menyangkut Unor lain.',
    'Sisa nilai = nilai yang belum diakui, bukan uang yang belum disetor.']"
  :kosong="! $bd['n'] ? 'Tidak ada tindak lanjut pada pilihan ini.' : null"
  kunci-tabel="status" :tabel="$tabelOn">
  @if($bd['adaLhp'])
    <x-slot:wawasan>
      @if($bd['beda'] > 0)<b>{{ $bd['beda'] }}</b> tindak lanjut sudah {{ $kataLhp }} di BPSDM, tapi belum SS di SIPTL.@else Tidak ada selisih antara BPSDM dan SIPTL.@endif
    </x-slot:wawasan>
  @endif
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Status'], ['nama' => 'Jumlah', 'num' => true], ['nama' => '%', 'num' => true], ['nama' => 'Nilai', 'num' => true], ['nama' => '% nilai', 'num' => true]]"
      :baris="array_merge(
        array_map(fn ($x) => ['BPSDM · '.$x['nama'], $x['n'], $bagi($x['n'], $bd['n']), $rpk($x['rp']), $bagi($x['rp'], $totRpUnor)], $isiUnor),
        $bd['adaLhp'] ? array_map(fn ($x) => ['SIPTL · '.$x['nama'], $x['n'], $bagi($x['n'], $bd['nLhp']), $rpk($x['rp']), $bagi($x['rp'], $totRpBpk)], $isiBpk) : []
      )" />
  </x-slot:isiTabel>

  <div class="dsb-matriks">
    <span class="mk-sudut" aria-hidden="true"></span>
    <span class="mk-kolom">Jumlah</span>
    <span class="mk-kolom">Nilai (Rp)</span>

    {{-- Kepala baris = versinya dan kedua totalnya, seperti "Total
         Rekomendasi" dan "Total Nilai Rekomendasi" di tiap blok lembar mereka. --}}
    <div class="mk-versi"><b>BPSDM</b>
      <span>{{ $bd['n'] }} tindak lanjut</span><span>{{ $rpk($totRpUnor) }}</span></div>
    @include('ringkasan.panel.sel-banding', ['label' => 'BPSDM, jumlah tindak lanjut', 'ket' => 'Jumlah', 'isi' => $isiUnor,
      'uang' => false, 'pilih' => $pilihUnor, 'aksi' => 'hasil'])
    @include('ringkasan.panel.sel-banding', ['label' => 'BPSDM, nilai tindak lanjut', 'ket' => 'Nilai (Rp)', 'isi' => $isiUnor,
      'uang' => true, 'pilih' => $pilihUnor, 'aksi' => 'hasil'])

    <div class="mk-versi"><b>SIPTL</b>
      @if($bd['adaLhp'])<span>{{ $bd['nLhp'] }} tindak lanjut LHP</span><span>{{ $rpk($totRpBpk) }}</span>@endif</div>
    @if($bd['adaLhp'])
      @include('ringkasan.panel.sel-banding', ['label' => 'SIPTL, jumlah tindak lanjut', 'ket' => 'Jumlah', 'isi' => $isiBpk,
        'uang' => false, 'pilih' => $f['bpk'], 'aksi' => 'bpk'])
      @include('ringkasan.panel.sel-banding', ['label' => 'SIPTL, nilai tindak lanjut', 'ket' => 'Nilai (Rp)', 'isi' => $isiBpk,
        'uang' => true, 'pilih' => $f['bpk'], 'aksi' => 'bpk'])
    @else
      <div class="dsb-catatan-bb mk-penuh">LHA tidak masuk SIPTL.</div>
    @endif
  </div>
  {{-- Dua sisa berdampingan, seperti kolom "Sisa Nilai SIPTL" dan "Sisa
       Nilai Hasil Verifikasi ITJEN" di lembar mereka. --}}
  <p class="kaki-bb">
    Sisa: <b>{{ $rpk($bd['unor']['BM']['rp']) }}</b> di BPSDM
    @if($bd['adaLhp']) · <b>{{ $rpk($sisaBpkBanding) }}</b> di SIPTL @endif
  </p>
</x-dsb-panel>
