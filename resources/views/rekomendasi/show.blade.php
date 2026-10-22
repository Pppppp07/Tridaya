@extends('rangka')
@section('judul', 'Rincian rekomendasi')
@section('isi')
@php
  use App\Enums\HasilTelaah;
  use App\Enums\PeranPengguna as P;
  use App\Support\Tampil;

  $u = auth()->user();
  $peran = $u->peranAktif();
  $balai = $peran->terbatasSatker();
  $jenis = $lap->sumber;
  $baris = $r->daftarSasaran();
  $arsip = $r->lampiran;
@endphp

<div class="body">
  <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <a class="link" href="{{ $kembali }}"><x-ikon n="ArrowLeft" :s="16" /> Kembali</a>
    <div style="flex:1"></div>
    {{-- `id` lama: pemberitahuan yang menunjuk arsip mendarat di tombol ini; tanpa skrip
         membuka jendelanya lewat #arsip. --}}
    <a class="btn" href="#arsip" id="r-arsip" data-buka-arsip aria-haspopup="dialog">
      <x-ikon n="Paperclip" :s="15" /> Arsip rekomendasi <span class="jml-arsip">{{ $arsip->count() }}</span>
    </a>
    {{-- Membuka blok temuannya dan menyorot baris rekomendasi ini. --}}
    <a class="btn" href="{{ route('laporan.show', [$lap, 'temuan' => $tem->id, 'rek' => $r->id]) }}"><x-ikon n="Files" :s="15" /> Lihat laporan lengkap</a>
  </div>

  <div class="babak">
    <span class="no">1</span>
    <b>Laporan dan temuan</b>
    <x-info teks="Laporan pemeriksaan asal dan temuan yang menjadi dasar rekomendasi ini." />
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
          ['l' => 'Satuan kerja terperiksa', 'v' => $balai ? e($u->satkerAktifModel()->map->namaPendek()->join(', ')) : view('components.daftar-satker', ['lap' => $lap])->render()],
          /* Surat asli tidak diberikan ke satuan kerja (memuat seluruh temuan seluruh satuan
             kerja). */
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
        /* Identitas temuan berbaris bersama keterangan temuan lain. */
        $faktaTemuan = [
          ['l' => 'Judul temuan', 'v' => e($tem->judul)],
          /* Ditulis seperti di surat: satu baris, dipisah garis miring. */
          /* Nomor surat dulu, kode sistem menyusul; kode TMN- tidak ada di surat mana pun. */
          ['l' => 'Nomor/Kode temuan', 'ket' => [
              $tem->nomor_pada_surat.' — nomor temuan pada laporan pemeriksaan, digunakan sebagai rujukan saat berkoordinasi.',
              $tem->kode.' — kode temuan yang dibuat sistem saat laporan dicatat; bukan nomor pada surat pemeriksaan.',
            ],
            'mono' => true, 'v' => e($tem->nomor_pada_surat.'/'.$tem->kode)],
          ['l' => 'Kategori temuan', 'ket' => \App\Models\Temuan::ketKategori($jenis),
            'v' => view('components.kategori-temuan', ['nama' => $tem->kategori?->nama])->render()],
          $tem->kategoriIntern ? ['l' => 'Kategori internal', 'ket' => \App\Models\Temuan::KET_KATEGORI_INTERN,
            'v' => view('components.tag-kategori', ['kat' => $tem->kategoriIntern, 'polos' => true])->render()] : null,
          ['l' => 'Sebab', 'v' => e($tem->sebab)],
          ['l' => 'Akibat', 'v' => e($tem->akibat)],
          $saudara->isNotEmpty() ? ['l' => 'Rekomendasi lain', 'ket' => [
              'Satu temuan dapat memiliki beberapa rekomendasi dengan penugasan dan bentuk tindak lanjut yang berbeda.',
              'Klik rekomendasi untuk membuka rinciannya. Daftar mengikuti hak akses Anda.',
            ], 'v' => view('rekomendasi.bagian.saudara', ['saudara' => $saudara, 'jenis' => $jenis])->render()] : null,
        ];
      @endphp
      <x-fakta :isi="$faktaTemuan" />
    </x-lipat>
  </div>

  <div class="babak">
    <span class="no">2</span>
    <b>Tindak lanjut</b>
    <x-info teks="Penugasan tiap satuan kerja, posisi berkas, hasil pemeriksaan, serta bukti tindak lanjut." />
  </div>

  @if($baris->isNotEmpty())
    <div id="r-tindaklanjut" class="wadahtl">
      @include('rekomendasi.bagian.kepala')

      {{-- Kepala rincian tindak lanjut. --}}
      @php
        $beres = $baris->filter(fn ($x) => $x->hasil === HasilTelaah::M)->count();
        $adaBpk = $jenis->melewatiSiptl() && $baris->contains(fn ($x) => $x->sudahSiptl());
        $sesuaiBpk = $baris->filter(fn ($x) => $x->sudahSiptl() && $x->status_bpk?->value === 'SS')->count();
        /* Satuan kerja membaca keadaan tindak lanjutnya sendiri di kedua lencana. */
        $milik = $balai ? $u->satkerAktifId() : null;
        $stBpk = $r->statusBpkUntuk($milik);
        $keadaanSiptl = \App\Http\Controllers\RincianController::keadaanSiptl($r, $peran, $u->satkerAktifId());
      @endphp
      <div class="kepalatl">
          <div class="kepalatl-judul">
            <span class="ic-kotak"><x-ikon n="ListChecks" :s="17" /></span>
            <b>
              Rincian tindak lanjut
              <x-info :teks="[
                'Daftar bentuk tindak lanjut beserta Satker yang ditugaskan pada rekomendasi ini.',
                'Setiap pasangan Satker dan bentuk tindak lanjut memiliki proses, status, nilai, dan bukti tersendiri.',
              ]" />
            </b>
            <span>{{ $balai
              ? ($peran === P::SATKER ? 'Kewajiban Satker ini pada rekomendasi. Buka tiket untuk mengisi tindak lanjut.' : 'Kewajiban Satker yang dipimpin. Buka tiket untuk memantau tindak lanjut.')
              : 'Satu tiket untuk tiap bentuk tindak lanjut. Buka tiketnya untuk melihat satuan kerja yang mengerjakannya.' }}</span>
          </div>
        {{-- Keterangan berderet ke bawah: nama di kiri, isi di kanan. --}}
        <dl class="kepalatl-daftar">
          <dt>Verifikasi Inspektorat <x-info :teks="[
            'Jumlah penugasan yang dinyatakan '.mb_strtolower(HasilTelaah::M->nama($jenis)).' oleh Inspektorat dibandingkan dengan seluruh penugasan yang ditampilkan.',
            'Rangkuman menjadi '.mb_strtolower(HasilTelaah::M->nama($jenis)).' setelah seluruh penugasan memenuhi hasil tersebut.',
          ]" /></dt>
          <dd>
            <b>{{ $beres }} dari {{ $baris->count() }} {{ mb_strtolower(HasilTelaah::M->nama($jenis)) }}</b>
            <x-cap-hasil :jenis="$jenis" :hasil="$r->keadaanUntuk($milik)" />
          </dd>
          {{-- Sumbu kedua, milik BPK — hanya LHP, dan hanya begitu ada yang naik. --}}
          @if($adaBpk)
            <dt>Penilaian BPK · SIPTL <x-info :teks="[
              'Hasil penilaian BPK untuk tindak lanjut LHP yang dicatat dari SIPTL. Penugasan berstatus SS masuk dalam jumlah sesuai di sini.',
              'Rangkuman menjadi BT jika masih ada BT; BS jika masih ada BS atau penugasan belum diunggah; SS jika seluruhnya SS.',
            ]" /></dt>
            <dd>
              <b>{{ $sesuaiBpk }} dari {{ $baris->count() }} sesuai</b>
              @if($stBpk)
                <span class="cap {{ $stBpk->cap() }}" title="{{ $stBpk->pendek() }}">{{ $stBpk->value }}</span>
              @endif
            </dd>
          @endif
          {{-- Keadaan urusan SIPTL dalam satu kalimat. --}}
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

  @include('rekomendasi.bagian.modal-arsip')
</div>
@endsection
