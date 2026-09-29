@extends('rangka')
{{-- Judulnya sama persis dengan menunya untuk semua peran (22 Sep). --}}
@section('judul', 'Dashboard')
@section('isi')
@php
  use App\Support\Dasbor;
  use App\Support\DasborKeadaan;
  use App\Support\Tampil;

  /* Pembantu tampilan yang dipakai seluruh potongan halaman ini. */
  $W = Dasbor::WARNA;
  $rpk = fn ($n) => Tampil::rupiahSingkat($n);
  $persen = fn ($a, $b) => Dasbor::persen($a, $b);
  $kecil = fn ($s) => mb_strtolower(mb_substr($s, 0, 1)).mb_substr($s, 1);
  /* Keterangan melayang: isinya dibawa atribut, digambar skrip — sama untuk
     tetikus dan papan ketik. */
  $petunjuk = fn (array $isi) => json_encode($isi, JSON_UNESCAPED_UNICODE);
  $namaHasil = fn ($h) => $h === 'M' ? $kataM : $kataBM;
  $STATUS = fn ($x) => \App\Enums\StatusTindakLanjut::from($x)->pendek();
  $TERATAS = \App\Http\Controllers\RingkasanController::TERATAS;
  $sisaLain = array_values(array_diff(Dasbor::URUT_LAIN, array_keys($f['lain'])));
  $kosongSemua = ! $d['K'];
  /* Dari yang belum memadai, yang berkasnya di satuan kerja — menunggu tanggapannya. */
  $nTunggu = $t['meja']['satker'] ?? 0;
@endphp

{{-- Seluruh halaman satu formulir GET: tombol mengirim perbuatannya
     (`ubah`) bersama keadaan sekarang (masukan tersembunyi); server
     menerapkannya dan mengalihkan ke alamat bersihnya. Skrip mengganti isi
     halaman di tempat, jadi halaman tidak melompat ke atas. --}}
<form class="body dsb" method="get" action="{{ route('ringkasan') }}" data-dasbor>
  @foreach(DasborKeadaan::tersembunyi($k) as [$nama, $nilai])
    <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
  @endforeach

  {{-- 1. FILTER — satu kali untuk seluruh halaman. Satu pertanyaan tiap
       baris, label di kolom yang sama. Jenis lebih dulu — Mbak Puspi:
       "sebelum milih ini dia mau LHP laporan pemeriksaan apa". --}}
  <section class="dsb-filter" aria-label="Filter dashboard">
    <span class="dsb-lbl" id="dsb-l-jenis"><x-ikon n="FileText" :s="14" />Jenis laporan</span>
    {{-- Ujung kanan baris Jenis dipakai tombol "Tambah filter", supaya
         kotak filter tidak perlu satu baris lagi. --}}
    <div class="dsb-baris-jenis">
      @include('ringkasan.pemilah', ['id' => 'dsb-l-jenis', 'judul' => 'Jenis laporan', 'satuan' => 'jenis laporan',
        'tunggal' => true, 'opsi' => $opsiJenis, 'pilih' => $f['jenis'] === 'semua' ? [] : [$f['jenis']],
        'hitung' => $d['hitung']['jenis'], 'aksi' => 'jenis', 'buang' => null])
      @if($sisaLain)
        <button type="button" class="dsb-tambah" aria-haspopup="dialog" aria-expanded="false" aria-controls="dsb-tambah-menu"
          data-buka-menu="dsb-tambah-menu"
          title="Tambah filter: {{ implode(', ', array_map(fn ($x) => mb_strtolower(Dasbor::DIM_LAIN[$x]['nama']), $sisaLain)) }}">
          <x-ikon n="Plus" :s="14" /> Tambah filter
        </button>
        {{-- Menu dimensi yang belum dipasang, masing-masing dengan keterangan
             singkatnya. Dibuka dan ditempatkan skrip. --}}
        <div id="dsb-tambah-menu" class="dsb-menu" role="dialog" aria-label="Tambah filter" hidden data-menu data-lebar="330">
          <div class="isi-menu">
            @foreach($sisaLain as $x)
              <button type="submit" name="ubah" value="{{ 'pasang:'.$x }}" class="opsi dua">
                <x-ikon n="Tag" :s="14" />
                <span class="nm">{{ Dasbor::DIM_LAIN[$x]['nama'] }}<small>{{ Dasbor::DIM_LAIN[$x]['ket'] }}</small></span>
              </button>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    <span class="dsb-lbl" id="dsb-l-tahun"><x-ikon n="Calendar" :s="14" />Tahun laporan</span>
    @include('ringkasan.pemilah', ['id' => 'dsb-l-tahun', 'judul' => 'Tahun laporan', 'satuan' => 'tahun',
      'tunggal' => false, 'opsi' => $opsiTahun, 'pilih' => $f['tahun'], 'hitung' => $d['hitung']['tahun'],
      'aksi' => 'tahun', 'buang' => null])

    {{-- Balai wilayah menurut nomor, lalu unit lain menurut data master. --}}
    <span class="dsb-lbl" id="dsb-l-satker"><x-ikon n="Building2" :s="14" />Satuan kerja</span>
    @include('ringkasan.pemilah', ['id' => 'dsb-l-satker', 'judul' => 'Satuan kerja', 'satuan' => 'satuan kerja',
      'tunggal' => false, 'opsi' => $kelompok['semua'], 'pilih' => $f['satker'], 'hitung' => $d['hitung']['satker'],
      'aksi' => 'satker', 'buang' => null])

    @foreach($f['lain'] as $x => $pilihLain)
      <span class="dsb-lbl" id="dsb-l-{{ $x }}" title="{{ Dasbor::DIM_LAIN[$x]['ket'] }}">
        <x-ikon n="Tag" :s="14" /><span class="teks">{{ Dasbor::DIM_LAIN[$x]['nama'] }}</span>
      </span>
      @include('ringkasan.pemilah', ['id' => 'dsb-l-'.$x, 'judul' => Dasbor::DIM_LAIN[$x]['nama'],
        'satuan' => mb_strtolower(Dasbor::DIM_LAIN[$x]['nama']), 'tunggal' => false, 'opsi' => $opsiTambahan[$x],
        'pilih' => $pilihLain, 'hitung' => $d['hitung']['lain'][$x] ?? [], 'aksi' => 'lain:'.$x, 'buang' => $x])
    @endforeach
  </section>

  {{-- Bar hasil: berapa yang tersisa, dan seluruh filter yang berlaku.
       Menempel di bawah bilah atas saat digulir. --}}
  <div class="dsb-hasil" data-dsb-hasil>
    <span class="jumlah" aria-live="polite">
      <x-ikon n="SlidersHorizontal" :s="15" />
      @if(Dasbor::adaFilter($f))
        <span><b>{{ $jmlTindakT }}</b> dari {{ $tindakSemua }} tindak lanjut</span>
      @else
        <span>Seluruh <b>{{ $tindakSemua }}</b> tindak lanjut satuan kerja</span>
      @endif
    </span>
    @if($aktif)
      <span class="dsb-aktif">
        @foreach($aktif as $a)
          @php
            if ($a['k'] === 'hasil') {
              $a['nama'] = $namaHasil($a['hasil']);
              $a['lengkap'] = 'Hanya yang '.$kecil($namaHasil($a['hasil']));
            }
          @endphp
          <button type="submit" name="ubah" value="{{ 'hapus:'.$a['k'] }}" class="keping-aktif"
            title="{{ $a['lengkap'] }} — klik untuk menghapus filter ini" aria-label="Hapus filter {{ $a['lengkap'] }}">
            <span class="t">{{ $a['nama'] }}</span><x-ikon n="X" :s="13" />
          </button>
        @endforeach
      </span>
    @else
      <span class="saran">Klik tombol atau grafik untuk memfilter.</span>
    @endif
    <span class="sela"></span>
    @if(Dasbor::adaFilter($f))
      <button type="submit" name="ubah" value="kosongkan" class="btn btn-s"><x-ikon n="RotateCcw" :s="13" /> Hapus filter</button>
    @endif
  </div>

  @if($k['hal'] === 'tabel')
    {{-- TABEL KESELURUHAN — halaman kedua dasbor. --}}
    <div class="subjudul dsb-judul-tabel">
      <button type="submit" name="ubah" value="hal:dasbor" class="dsb-pindah"><x-ikon n="ArrowLeft" :s="14" /> Dashboard</button>
      <b>Tabel keseluruhan</b>
      <x-info :teks="['Dihitung per tindak lanjut satuan kerja: satu satuan kerja pada satu bentuk tindak lanjut. SIPTL hanya LHP.',
        'Klik tahun untuk membuka satuan kerjanya, klik angka untuk melihat rekomendasinya.']" />
      <span class="garis"></span>
      @if($tk)
        @php $semuaTertutup = collect($tk['kelompok'])->every(fn ($g) => ! in_array((string) $g['k'], $k['buka'], true)); @endphp
        <button type="submit" name="ubah"
          value="{{ 'bukasemua:'.($semuaTertutup ? implode(',', array_column($tk['kelompok'], 'k')) : '') }}" class="dsb-pindah">
          @if($semuaTertutup)
            <x-ikon n="ChevronDown" :s="14" /> Buka semua
          @else
            <x-ikon n="ChevronUp" :s="14" /> Tutup semua
          @endif
        </button>
      @endif
    </div>
    @if($kosongSemua)
      @include('ringkasan.kosong')
    @endif
    @if($tk)
      @include('ringkasan.tabel-keseluruhan')
    @endif
    @if($pintas)
      @include('ringkasan.pintas')
    @endif
  @else
    {{-- 2. RINGKASAN UTAMA — hanya lima kartu yang tampil di awal. Tiap kartu
         membuka rinciannya sendiri, satu kartu sekali buka. --}}
    <div class="subjudul"><b>Ringkasan utama</b><span class="garis"></span>
      {{-- Pintu ke tabel keseluruhan, di halamannya sendiri. --}}
      <button type="submit" name="ubah" value="hal:tabel" class="dsb-pindah">
        <x-ikon n="Table2" :s="14" /> Tabel keseluruhan <x-ikon n="ArrowRight" :s="13" />
      </button>
    </div>
    @include('ringkasan.kartu')

    @if($kosongSemua)
      @include('ringkasan.kosong')
    @endif

    {{-- 3. RINCIAN — terbuka di bawah kartu yang diklik: bagian-bagian
         angkanya, satu kalimat sumber datanya, lalu grafik rinciannya. --}}
    @unless($kosongSemua)
      @if($k['kartu'] !== '')
        @include('ringkasan.rincian')
      @else
        <p class="dsb-ajak">Klik kartu untuk melihat rinciannya.</p>
      @endif
    @endunless
  @endif

  <p class="dsb-kaki">Data per {{ $hariIni }}.</p>
</form>
@endsection
