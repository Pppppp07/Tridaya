@extends('rangka')
@section('judul', 'Rincian laporan pemeriksaan')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;
  use App\Models\Rekomendasi;
  use App\Support\Tampil;

  $u = auth()->user();
  $peran = $u->peran;
  $balai = $peran === P::SATKER;
  $jenis = $lap->sumber;
  $a = $lap->angka();
  $sebaranTl = $lap->sebaranTindakLanjut($peran, $u->satker_id);
  $IKON_PEGANG = ['Balai' => 'Building2', 'Setba' => 'Users', 'UKI' => 'ClipboardCheck', 'Inspektorat' => 'Stamp',
    'Sudah diperiksa' => 'CheckCircle2', 'Satker' => 'Building2', 'BPK' => 'Landmark', 'Selesai' => 'CheckCircle2'];
  $tugasku = $lap->temuan->reject(fn ($t) => $t->hanya_terperiksa)->values();
  $pantau = $lap->temuan->filter(fn ($t) => $t->hanya_terperiksa)->values();
  $satkerLap = $lap->satkerDiperiksa();
  $batas = $lap->tenggatJawab();
  $sisa = -Rekomendasi::selisih($batas);
  $jeda = $lap->jedaPencatatan();
  $asli = $lap->suratAsli();

  $linkSatker = '<span class="linksatker">'.$satkerLap->map(function ($x) use ($lap) {
      $n = $lap->jumlahTemuanSatker($x->id);
      /* Pintasan (26 Sep): membuka blok temuannya lalu menyorot baris
         rekomendasi satuan kerja ini — dulu cuma menggulir ke seluruh daftar. */
      return '<button type="button" data-ke-satker="'.$x->id.'">'.e($x->namaPendek()).($n > 1 ? ' ('.$n.' temuan)' : '').'</button>';
  })->join(', ').($satkerLap->isEmpty() ? '—' : '').'</span>';

  $faktaSurat = [
    ['l' => 'Sumber', 'v' => $jenis->value, 'k' => $jenis->nama()],
    ['l' => 'Nomor surat', 'v' => e($lap->nomor), 'mono' => true],
    ['l' => 'Surat diterima Setba', 'v' => Tampil::tgl($lap->tgl_terima), 'mono' => true, 'ket' => [
      'Tanggal surat pemeriksaan sampai di Setba, diambil dari cap terima suratnya.',
      'Dari tanggal inilah tenggat jawaban dihitung — bukan dari tanggal pencatatannya di sistem.',
    ]],
    ['l' => 'Dicatat di sistem', 'v' => Tampil::tgl($lap->dicatat_pada), 'mono' => true,
      'k' => $jeda === null ? 'belum tercatat' : ($jeda > 0 ? $jeda.' hari setelah surat diterima' : 'hari yang sama'),
      'ket' => [
        'Tanggal Setba memasukkan laporan ini ke sistem. Diisi sendiri oleh sistem saat disimpan, tidak diketik.',
        'Jaraknya dari tanggal surat diterima penting: selama surat belum dicatat, tenggat jawabannya sudah berjalan tapi belum ada yang bisa mengerjakannya.',
      ]],
    ['l' => 'Tanggal rencana aksi', 'v' => Tampil::tgl($batas), 'mono' => true, 'c' => $sisa < 0 ? 'var(--verm)' : null,
      'k' => $sisa < 0 ? 'lewat '.(-$sisa).' hari' : ($sisa === 0 ? 'jatuh tempo hari ini' : $sisa.' hari lagi'),
      'ket' => [
        $jenis->hariTenggat().' '.($jenis->pakaiHariKerja() ? 'hari kerja' : 'hari kalender').' sejak laporan diterima.',
        'Dasarnya '.($jenis->pakaiHariKerja() ? 'ketentuan pengawasan intern' : 'UU 15/2004 Pasal 20').'.',
        $jenis->pakaiHariKerja()
          ? 'Dihitung hari kerja, jadi Sabtu, Minggu, dan hari libur tidak ikut dihitung.'
          : 'Dihitung hari kalender, jadi Sabtu dan Minggu ikut dihitung.',
      ]],
    ['l' => 'Satuan kerja diperiksa', 'v' => $linkSatker],
  ];

  $sebaran = '<span class="sebaran">'.collect($sebaranTl)->map(fn ($p) =>
      '<span>'.view('components.ikon', ['n' => $IKON_PEGANG[$p['nama']] ?? 'Clock', 's' => 13])->render().' '.e($p['nama']).' <b>'.$p['n'].'</b></span>'
  )->join('').'</span>';
  $faktaIsi = [
    /* "N temuan" menyorot blok-blok temuannya; "N rekomendasi" membuka semua
       blok lalu menyorot seluruh baris rekomendasinya (26 Sep). */
    ['l' => 'Temuan', 'v' => '<span class="linksatker"><button type="button" data-ke-temuan><b class="angkafakta">'.$a['temuan'].'</b> temuan</button></span>'],
    ['l' => 'Rekomendasi', 'v' => '<span class="linksatker"><button type="button" data-ke-rek><b class="angkafakta">'.$a['jml'].'</b> rekomendasi</button></span>'],
    ['l' => 'Rekomendasi selesai', 'c' => 'var(--ok)', 'v' => '<b class="angkafakta">'.$a['tuntas'].'</b> dari '.$a['jml']],
    $sebaranTl ? ['l' => 'Posisi tindak lanjut', 'ket' => 'Dihitung per tindak lanjut satuan kerja. Satu rekomendasi bisa punya beberapa tindak lanjut di meja berbeda.', 'v' => $sebaran] : null,
  ];

  $faktaDana = array_merge(
    [['l' => 'Total nilai temuan', 'v' => '<b class="angkafakta">'.Tampil::rupiah($a['nilaiTemuan']).'</b>',
      'ket' => 'Jumlah semua temuan di laporan ini. Tidak semuanya harus dikembalikan berupa uang.']],
    $a['target'] !== $a['nilaiTemuan'] ? [
      ['l' => 'Tagihan rekomendasi', 'v' => '<b class="angkafakta">'.Tampil::rupiah($a['target']).'</b>',
        'ket' => \App\Models\Laporan::KET_TAGIHAN],
      ['l' => 'Administratif', 'c' => 'var(--jingga)', 'v' => '<b class="angkafakta">'.Tampil::rupiah($a['nilaiTemuan'] - $a['target']).'</b>',
        'ket' => \App\Models\Laporan::KET_ADMINISTRATIF],
    ] : [],
    [
      ['l' => 'Sudah dipulihkan', 'c' => 'var(--ok)', 'v' => '<b class="angkafakta">'.Tampil::rupiah($a['masuk']).'</b>'],
      ['l' => $a['target'] - $a['masuk'] > 0 ? 'Sisa yang harus dipulihkan' : 'Sisa tagihan',
        'c' => $a['target'] - $a['masuk'] > 0 ? 'var(--bad)' : 'var(--ok)',
        'v' => '<b class="angkafakta">'.($a['target'] - $a['masuk'] > 0 ? Tampil::rupiah($a['target'] - $a['masuk']) : 'Sudah lunas').'</b>'],
    ],
  );
@endphp

<div class="body">
  <div style="display:flex;gap:10px;margin-bottom:20px;align-items:center;flex-wrap:wrap">
    {{-- Kembali ke baris laporan ini di daftarnya, disorot (26 Sep). --}}
    <a class="link" href="{{ route('laporan.index', ['tuju' => $lap->id]) }}"><x-ikon n="ArrowLeft" :s="16" /> Kembali ke daftar laporan</a>
  </div>

  <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:20px">
    <div style="flex:1;min-width:260px">
      <h2 style="margin:0;font-size:26px;font-weight:700;letter-spacing:-.03em;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        {{ $jenis->value }} {{ $lap->nomor }}
        <x-sumber :j="$jenis" />
      </h2>
      <div style="font-size:13.5px;color:var(--ink-3);margin-top:5px">
        Satuan kerja: <x-daftar-satker :lap="$lap" :maks="2" />
      </div>
    </div>
    @if($a['beres'])<span class="cap cap-ss"><x-ikon n="Check" :s="12" /> Sudah selesai</span>@endif
  </div>

  {{-- Satu lajur penuh. Rel kanannya dibuang bersama kartu "Jadwal penting":
       tanggal rencana aksi berbeda-beda tiap rekomendasi, jadi satu tanggal di
       kepala halaman menyebut milik rekomendasi pertama saja — dan dua angkanya
       sudah tercetak di ringkasan kondisi laporan serta di tiap barisnya. --}}
<div class="card" style="margin-bottom:20px">
  <div class="judulkartu">
    <span class="ic-kotak"><x-ikon n="LayoutGrid" :s="17" /></span>
    <h3>Ringkasan kondisi laporan</h3>
  </div>

  <div class="subjudul"><b>Surat</b><span class="garis"></span></div>
  <x-fakta :isi="$faktaSurat" />

  <div class="subjudul" style="margin:18px 0 var(--s4)"><b>Isi laporan</b><span class="garis"></span></div>
  <x-fakta :isi="$faktaIsi" />

  <div class="subjudul" style="margin:18px 0 var(--s4)"><b>Pemulihan dana</b><span class="garis"></span></div>
  <x-fakta :isi="$faktaDana" />

  {{-- Surat aslinya tidak diberikan ke satuan kerja. --}}
  @if(! $balai)
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line-2);display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      <div class="lbl">Dokumen asli</div>
      @if($asli)
        <x-berkas :b="$asli" jenis="Surat laporan pemeriksaan" oleh="Setba" :tanggal="$lap->tgl_terima" />
      @else
        <span class="belumada">belum dihubungkan &mdash; bisa dilengkapi belakangan</span>
      @endif
    </div>
  @endif
</div>

@if($tugasku->isNotEmpty())
  <div class="judulkartu" style="margin-bottom:14px">
    <span class="ic-kotak hijau"><x-ikon n="ListChecks" :s="17" /></span>
    <h3>{{ $pantau->isNotEmpty() ? 'Temuan yang menjadi tugas Anda' : 'Daftar temuan' }}</h3>
    <x-info :teks="[
      'Tiap temuan berdiri sendiri dengan judulnya; kategorinya disebut di dalam rinciannya.',
      'Satu temuan bisa melahirkan beberapa rekomendasi, jadi angka rekomendasi selalu lebih besar atau sama dengan angka temuan.',
      'Satuan kerja yang menangani tiap rekomendasi disebut di dalam blok temuannya — satu temuan bisa mengenai beberapa satuan kerja sekaligus.',
    ]" />
    <span class="hitungtr"><b>{{ $pantau->isNotEmpty() ? $tugasku->count() : $a['temuan'] }}</b> temuan <x-ikon n="ArrowRight" :s="12" /> <b>{{ $a['jml'] }}</b> rekomendasi</span>
  </div>
@endif

<section id="daftar-temuan">
  @foreach($tugasku as $ke => $tem)
    @include('laporan.blok-temuan', ['tem' => $tem, 'i' => $ke])
  @endforeach
</section>

@if($pantau->isNotEmpty())
  <div class="judulkartu" style="margin:{{ $tugasku->isNotEmpty() ? '22px 0 14px' : '0 0 14px' }}">
    <span class="ic-kotak abu"><x-ikon n="Eye" :s="17" /></span>
    <h3>Temuan atas nama satuan kerja Anda</h3>
    <span class="n">{{ $pantau->count() }} temuan · tanpa tugas untuk Anda</span>
  </div>
  @foreach($pantau as $tem)
    @include('laporan.blok-temuan', ['tem' => $tem, 'i' => $lap->temuan->search($tem)])
  @endforeach
@endif
</div>
@endsection
