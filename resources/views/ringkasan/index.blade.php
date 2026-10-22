@extends(\App\Support\Halaman::bagian() ? 'rangka-bagian' : 'rangka')
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
  /* Keterangan melayang: isinya dibawa atribut, digambar skrip (tetikus dan papan ketik). */
  $petunjuk = fn (array $isi) => json_encode($isi, JSON_UNESCAPED_UNICODE);
  $namaHasil = fn ($h) => $h === 'M' ? $kataM : $kataBM;
  $STATUS = fn ($x) => \App\Enums\StatusTindakLanjut::from($x)->pendek();
  /* Satu bunyi keterangan untuk setiap label "BPSDM" di dashboard. */
  $INFO_BPSDM = [
    'Status BPSDM mengikuti hasil verifikasi Inspektorat.',
    'Penugasan yang belum diverifikasi atau belum disetujui masuk kelompok '.$kataBM.'. Satu Satker pada satu bentuk tindak lanjut dihitung sebagai satu penugasan.',
  ];
  $TERATAS = \App\Http\Controllers\RingkasanController::TERATAS;
  $sisaLain = array_values(array_diff(Dasbor::URUT_LAIN, array_keys($f['lain'])));
  $kosongSemua = ! $d['K'];
  /* Warna tahap untuk yang belum lolos; "selesai" di sini abu, bukan hijau lolos. */
  $warnaProses = fn ($m) => $m === 'selesai' ? $W['proses'] : Dasbor::WARNA_MEJA[$m];
@endphp

{{-- Satu formulir GET: tombol mengirim perbuatannya (`ubah`) bersama keadaan sekarang; server
     mengalihkan ke alamat bersihnya. Skrip mengganti isi di tempat. --}}
<form class="body dsb" method="get" action="{{ route('ringkasan') }}" data-dasbor>
  @foreach(DasborKeadaan::tersembunyi($k) as [$nama, $nilai])
    <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
  @endforeach

  {{-- 0. Tab data, di atas filter: data historis dan data sistem dipantau sebagai tampilan
       terpisah (bentuk tab sama dengan Data Master dan Log). Hanya bila ada data historis. --}}
  @if($adaHistoris)
    <div class="tl-tabs dm-tabs dsb-data" role="tablist" aria-label="Data yang ditampilkan">
      @foreach(Dasbor::DATA as $kunci => $v)
        <button type="submit" name="ubah" value="{{ 'data:'.$kunci }}" role="tab"
          aria-selected="{{ $k['data'] === $kunci ? 'true' : 'false' }}" title="{{ $v['ket'] }}">
          <x-ikon :n="$v['ikon']" :s="14" />{{ $v['nama'] }}<span class="dm-n">{{ $jumlahData[$kunci] }}</span>
        </button>
      @endforeach
    </div>
  @endif

  {{-- 1. Filter — sekali untuk seluruh halaman; Jenis lebih dulu. --}}
  <section class="dsb-filter" aria-label="Filter dashboard">
    <span class="dsb-lbl" id="dsb-l-jenis"><x-ikon n="FileText" :s="14" />Jenis laporan</span>
    <div class="dsb-baris-jenis">
      @include('ringkasan.pemilah', ['id' => 'dsb-l-jenis', 'judul' => 'Jenis laporan', 'satuan' => 'jenis laporan',
        'tunggal' => true, 'opsi' => $opsiJenis, 'pilih' => [$f['jenis']],
        'hitung' => $d['hitung']['jenis'], 'aksi' => 'jenis', 'buang' => null, 'tanpaSemua' => true])
      @if($sisaLain)
        <button type="button" class="dsb-tambah" aria-haspopup="dialog" aria-expanded="false" aria-controls="dsb-tambah-menu"
          data-buka-menu="dsb-tambah-menu"
          title="Tambah filter: {{ implode(', ', array_map(fn ($x) => mb_strtolower(Dasbor::DIM_LAIN[$x]['nama']), $sisaLain)) }}">
          <x-ikon n="Plus" :s="14" /> Tambah filter
        </button>
        {{-- Menu dimensi yang belum dipasang; dibuka dan ditempatkan skrip. --}}
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
      'aksi' => 'satker', 'buang' => null, 'tanpaSemua' => auth()->user()->peranAktif()->terbatasSatker()])

    @foreach($f['lain'] as $x => $pilihLain)
      <span class="dsb-lbl" id="dsb-l-{{ $x }}" title="{{ Dasbor::DIM_LAIN[$x]['ket'] }}">
        <x-ikon n="Tag" :s="14" /><span class="teks">{{ Dasbor::DIM_LAIN[$x]['nama'] }}</span>
      </span>
      @include('ringkasan.pemilah', ['id' => 'dsb-l-'.$x, 'judul' => Dasbor::DIM_LAIN[$x]['nama'],
        'satuan' => mb_strtolower(Dasbor::DIM_LAIN[$x]['nama']), 'tunggal' => false, 'opsi' => $opsiTambahan[$x],
        'pilih' => $pilihLain, 'hitung' => $d['hitung']['lain'][$x] ?? [], 'aksi' => 'lain:'.$x, 'buang' => $x])
    @endforeach
  </section>

  {{-- Bar hasil: berapa yang tersisa dan filter yang berlaku; menempel saat digulir. --}}
  <div class="dsb-hasil" data-dsb-hasil>
    <span class="jumlah" aria-live="polite">
      <x-ikon n="SlidersHorizontal" :s="15" />
      {{-- Jenis laporan disebut: bar ini tetap terlihat saat keping jenisnya sudah tergulir. --}}
      @if(Dasbor::adaFilter($f))
        <span><b>{{ $jmlTindakT }}</b> dari {{ $tindakSemua }} tindak lanjut {{ $f['jenis'] }}</span>
      @else
        <span>Seluruh <b>{{ $tindakSemua }}</b> tindak lanjut {{ $f['jenis'] }}</span>
      @endif
      {{-- Tab datanya ikut disebut: bar ini tetap terlihat saat tabnya sudah tergulir. --}}
      @if($k['data'] !== 'semua')<span class="dsb-data-sebut">· {{ Dasbor::DATA[$k['data']]['nama'] }}</span>@endif
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
      <x-info :teks="['Jumlah dihitung per penugasan: satu satuan kerja pada satu bentuk tindak lanjut.',
        'Setiap kolom angka bisa dijumlah ke baris tahunnya.',
        'Klik tahun untuk melihat satuan kerja, lalu klik angka untuk membuka rekomendasi yang terkait. Data mengikuti filter aktif dan hak akses Anda.']" />
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
    {{-- 2. Ringkasan utama — tiap kartu membuka rinciannya. --}}
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

    {{-- 3. Rincian — terbuka di bawah kartu yang diklik. --}}
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
