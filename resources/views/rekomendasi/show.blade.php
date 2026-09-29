@extends('rangka')
@section('judul', 'Rincian rekomendasi')
@section('isi')
@php
  use App\Enums\HasilTelaah;
  use App\Enums\PeranPengguna as P;
  use App\Support\Tampil;

  $u = auth()->user();
  $peran = $u->peran;
  $balai = $peran === P::SATKER;
  $jenis = $lap->sumber;
  $baris = $r->daftarSasaran();
  $arsip = $r->lampiran;
@endphp

<div class="body">
  <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <a class="link" href="{{ $kembali }}"><x-ikon n="ArrowLeft" :s="16" /> Kembali</a>
    <div style="flex:1"></div>
    {{-- `id` bagian lama kartunya: pemberitahuan yang menunjuk arsip mendarat di
         tombol ini. Tanpa skrip, link-nya membuka jendelanya lewat #arsip. --}}
    <a class="btn" href="#arsip" id="r-arsip" data-buka-arsip aria-haspopup="dialog">
      <x-ikon n="Paperclip" :s="15" /> Arsip rekomendasi <span class="jml-arsip">{{ $arsip->count() }}</span>
    </a>
    {{-- Membuka blok temuannya dan menyorot baris rekomendasi ini (26 Sep). --}}
    <a class="btn" href="{{ route('laporan.show', [$lap, 'temuan' => $tem->id, 'rek' => $r->id]) }}"><x-ikon n="Files" :s="15" /> Lihat laporan lengkap</a>
  </div>

  <div class="babak">
    <span class="no">1</span>
    <b>Laporan dan temuan</b>
    <x-info teks="Surat asalnya dan isi temuan yang melahirkan rekomendasi ini. Ketentuan yang mengikat satuan kerja ada di bagian Tindak lanjut di bawah." />
  </div>

  <div class="card lipatsatu" style="margin-bottom:20px">
    {{-- ---- laporan asal ---- --}}
    <x-lipat :kartu="false" judul="Laporan asal" ikon="Files" id-isi="lipat-laporan-isi">
      <x-slot:ringkas>
        <span class="mono">{{ $lap->nomor }}</span>
        <span>{{ $jenis->penerbit() }}</span>
        <span>Diterima {{ Tampil::tgl($lap->tgl_terima) }}</span>
      </x-slot:ringkas>
      @php
        $asli = $lap->suratAsli();
        $faktaLaporan = [
          ['l' => 'Nomor surat', 'v' => e($lap->nomor), 'mono' => true],
          ['l' => 'Diterbitkan', 'v' => e($jenis->penerbit())],
          ['l' => 'Diterima', 'v' => Tampil::tgl($lap->tgl_terima)],
          ['l' => 'Satuan kerja terperiksa', 'v' => $balai ? e($u->satker->namaPendek()) : view('components.daftar-satker', ['lap' => $lap])->render()],
          /* Surat aslinya tidak diberikan ke satuan kerja: satu surat memuat
             seluruh temuan pada seluruh satuan kerja. */
          ! $balai ? ['l' => 'Berkas surat asli', 'v' => $asli
              ? view('components.berkas', ['b' => $asli, 'jenis' => 'Surat laporan pemeriksaan', 'oleh' => 'Setba', 'tanggal' => $lap->tgl_terima])->render()
              : '<span class="belumada">belum dihubungkan</span>'] : null,
        ];
      @endphp
      <x-fakta :isi="$faktaLaporan" />
    </x-lipat>

    {{-- ---- uraian temuan ---- --}}
    <x-lipat :kartu="false" class="ruas" judul="Uraian temuan" ikon="FileText" nada="abu" id-isi="lipat-temuan-isi">
      <x-slot:ringkas>
        <span class="potong">{{ $tem->judul }}</span>
        <x-kategori-temuan :nama="$tem->kategori?->nama" />
        @if($tem->kategoriIntern)<x-tag-kategori :kat="$tem->kategoriIntern" :polos="true" />@endif
        @if($saudara->isNotEmpty())<span>{{ $saudara->count() }} rekomendasi lain dari temuan ini</span>@endif
      </x-slot:ringkas>
      @php
        /* Identitas temuannya ikut berbaris bersama keterangan temuan yang
           lain. Dulu berdiri di kepala rekomendasi, lalu sempat jadi blok
           tersendiri di sini — kata Hizkia (20 Sep): "judul temuannya akan
           lebih baik dibuat berderet atau menjadi baris kebawah disamakan
           dengan informasi temuan lainnya". */
        $faktaTemuan = [
          ['l' => 'Judul temuan', 'v' => e($tem->judul)],
          /* Ditulis seperti di surat: satu baris, dipisah garis miring. */
          /* Urutan namanya mengikuti urutan isinya: nomor surat dulu, kode
             sistem menyusul. Keduanya beda asal, dan itu disebut di
             keterangannya — kode TMN- tidak ada di surat mana pun. */
          ['l' => 'Nomor/Kode temuan', 'ket' => [
              $tem->nomor_pada_surat.' — nomor temuan pada suratnya, disalin apa adanya. Inilah yang disebut saat berkoordinasi dengan pemeriksa.',
              $tem->kode.' — kode arsip temuan di sistem ini, dirakit saat laporannya dicatat. Bukan bagian dari surat.',
              'Penomoran resmi rekomendasinya sendiri ada di kepala rekomendasi: Ref LHP dan Ref IDT.',
            ],
            'mono' => true, 'v' => e($tem->nomor_pada_surat.'/'.$tem->kode)],
          ['l' => 'Kategori temuan', 'ket' => \App\Models\Temuan::ketKategori($jenis),
            'v' => view('components.kategori-temuan', ['nama' => $tem->kategori?->nama])->render()],
          $tem->kategoriIntern ? ['l' => 'Kategori internal', 'ket' => \App\Models\Temuan::KET_KATEGORI_INTERN,
            'v' => view('components.tag-kategori', ['kat' => $tem->kategoriIntern, 'polos' => true])->render()] : null,
          ['l' => 'Sebab', 'v' => e($tem->sebab)],
          ['l' => 'Akibat', 'v' => e($tem->akibat)],
          $saudara->isNotEmpty() ? ['l' => 'Rekomendasi lain', 'ket' => [
              'Rekomendasi lain yang lahir dari temuan yang sama.',
              'Satu temuan bisa melahirkan beberapa rekomendasi, dan tiap rekomendasi bisa ditujukan ke satuan kerja yang berbeda — misalnya satu menyetor uangnya, satu lagi membenahi prosedurnya.',
              'Bisa ditekan untuk berpindah ke rekomendasi tersebut.',
            ], 'v' => view('rekomendasi.bagian.saudara', ['saudara' => $saudara, 'jenis' => $jenis])->render()] : null,
        ];
      @endphp
      <x-fakta :isi="$faktaTemuan" />
    </x-lipat>
  </div>

  <div class="babak">
    <span class="no">2</span>
    <b>Tindak lanjut</b>
    <x-info teks="Siapa mengerjakan apa, sudah sampai mana, dan statusnya." />
  </div>

  @if($baris->isNotEmpty())
    <div id="r-tindaklanjut" class="wadahtl">
      @include('rekomendasi.bagian.kepala')

      {{-- Kepala rincian tindak lanjut — padanan `kepalatl` di prototipe. Kata
           Hizkia (17 Sep): "kontraskan tampilan container nya … judul ini itu
           maksudnya judul untuk bagian mana ya?", lalu "tidak terlalu banyak
           distraksi … mengikuti tema atau kontras yang serasi dengan section
           utamanya". Identitasnya dibawa bentuk kepalanya, bukan warnanya: judul
           berlencana dengan kalimat penjelas, hitungan bernama bergaya ringkasan
           kepala rekomendasi, dan kalimat SIPTL bernama. --}}
      @php
        $beres = $baris->filter(fn ($x) => $x->hasil === HasilTelaah::M)->count();
        $adaBpk = $jenis->melewatiSiptl() && $baris->contains(fn ($x) => $x->siptl_tanggal);
        $sesuaiBpk = $baris->filter(fn ($x) => $x->siptl_tanggal && in_array($x->status_bpk?->value, ['SS', 'TD'], true))->count();
        /* Satuan kerja membaca keadaan tindak lanjutnya sendiri di kedua
           lencana (27 Sep) — dulu lencana BPK sudah begitu, lencana Inspektorat
           belum, jadi "1 dari 1 memadai" bersanding BM. */
        $milik = $balai ? $u->satker_id : null;
        $stBpk = $r->statusBpkUntuk($milik);
        $keadaanSiptl = \App\Http\Controllers\RincianController::keadaanSiptl($r, $peran, $u->satker_id);
      @endphp
      <div class="kepalatl">
          <div class="kepalatl-judul">
            <span class="ic-kotak"><x-ikon n="ListChecks" :s="17" /></span>
            <b>
              Rincian tindak lanjut
              <x-info :teks="array_merge($balai ? [
                'Yang ditampilkan hanya kewajiban satuan kerja ini. Rekomendasi yang sama bisa membebani satuan kerja lain, dan bagian mereka bukan urusan di sini.',
                'Satu satuan kerja yang kena dua tindak lanjut memikul dua kewajiban, dan keduanya harus tuntas sendiri-sendiri.',
              ] : [
                'Rekomendasi baru dinilai '.mb_strtolower(HasilTelaah::M->nama($jenis)).' kalau seluruh penugasan di dalamnya sudah '.mb_strtolower(HasilTelaah::M->nama($jenis)).'.',
                'Dua dari tiga selesai tetap terhitung '.mb_strtolower(HasilTelaah::BM->nama($jenis)).' — tapi yang belum tetap disebut namanya di sini supaya bisa dikejar.',
                'Satu satuan kerja yang kena dua tindak lanjut terhitung dua penugasan, karena keduanya memang harus tuntas sendiri-sendiri.',
              ], $jenis->melewatiSiptl() ? [
                'Hitungan kedua milik BPK: berapa tindak lanjut yang sudah dinyatakan sesuai lewat SIPTL. Tiap baris diunggah dan dinilai sendiri-sendiri.',
                'Status SIPTL rekomendasinya dirangkum dari baris-barisnya: yang paling belakang menentukan. Aturan itu diambil dari lembar pemantauan — kolom Rank Status SiPTL dan Max Rank per Reff IDT.',
              ] : [])" />
            </b>
            <span>{{ $balai
              ? 'Kewajiban satuan kerja Anda pada rekomendasi ini. Buka tiketnya untuk mengisi tindak lanjut.'
              : 'Satu tiket untuk tiap bentuk tindak lanjut. Buka tiketnya untuk melihat satuan kerja yang mengerjakannya.' }}</span>
          </div>
        {{-- Keterangannya berderet ke bawah — kata Hizkia (17 Sep), "teks teksnya
             pada rincian tindak lanjut dibuat berderet kebawah saja". Nama redup
             di kiri, isi di kanan, sejajar tulisan judul. --}}
        <dl class="kepalatl-daftar">
          <dt>Verifikasi Inspektorat</dt>
          <dd>
            <b>{{ $beres }} dari {{ $baris->count() }} {{ mb_strtolower(HasilTelaah::M->nama($jenis)) }}</b>
            <x-cap-hasil :jenis="$jenis" :hasil="$r->keadaanUntuk($milik)" />
          </dd>
          {{-- Sumbu kedua, milik BPK — hanya LHP, dan hanya begitu ada yang naik. --}}
          @if($adaBpk)
            <dt>Penilaian BPK · SIPTL</dt>
            <dd>
              <b>{{ $sesuaiBpk }} dari {{ $baris->count() }} sesuai</b>
              <span class="cap {{ $stBpk->cap() }}" title="{{ $stBpk->pendek() }}">{{ $stBpk->value }}</span>
            </dd>
          @endif
          {{-- Keadaan urusan SIPTL dalam satu kalimat — dulu kalimat pembuka kartu
               Urusan SIPTL. --}}
          @if($keadaanSiptl)
            <dt>Keadaan SIPTL</dt>
            <dd>{{ $keadaanSiptl['kalimat'] }}</dd>
          @endif
        </dl>
      </div>

      @foreach($r->tindakan as $k => $tk)
        @include('rekomendasi.bagian.tiket', ['tk' => $tk, 'k' => $k])
      @endforeach


    </div>
  @endif

  {{-- Di sini dulu berdiri kartu Urusan SIPTL. Seluruhnya lebur ke tabel tindak
       lanjut di atas (17 Sep): kalimatnya di bawah bilah rincian, statusnya di
       kolom SIPTL, putusan dan catatannya di rincian baris, pekerjaannya di tab
       Kerjakan, dan pembagian uangnya di kaki tabel. --}}

  {{-- Di sini dulu berdiri kartu "Riwayat status tindak lanjut" beserta tombol
       pemilih satuan kerjanya. Sejak 21 Sep isinya tab "Riwayat" di baris
       satuan kerja pada tabel tindak lanjut di atas (riwayat-baris.blade.php) —
       kata Hizkia, "ditampilkan kedalam table tindak lanjut satuan kerja sesuai
       dengan satuan kerjanya". --}}

  {{-- Kartu "Riwayat aktivitas" dulu berdiri di sini, bersama kartu Arsip.
       Keduanya keluar dari kaki halaman (18 Sep): Arsip jadi jendela dari
       tombol di bilah atas, Riwayat aktivitas dibuang — riwayat tiap satuan
       kerja sudah ada di tab Riwayat barisnya, dan catatan tingkat
       rekomendasi bisa menyebut satuan kerja lain. Prototipe menyusul 25 Sep. --}}

  @include('rekomendasi.bagian.modal-arsip')
</div>
@endsection
