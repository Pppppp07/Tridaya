@php
  use App\Support\Tampil;
  use App\Enums\HasilTelaah;

  /* Satuan kerja melihat tanggal yang mengikat dirinya (paling awal di antara tindak lanjutnya). */
  $tgh = $balai ? $r->renaksiUntuk($u->satkerAktifId()) : $r->tenggat_jawab;
  $telatIni = $tgh && $tgh->copy()->startOfDay()->lt(now()->startOfDay()) && ! $r->tanpaTenggat();
  $nilai = $balai ? $baris->sum('nilai') : (int) $r->nilai_pulih;
  $dana = $r->progresDana($balai ? $u->satkerAktifId() : null);
  $angsur = $r->rencanaAngsur();
  $bentuk = $baris->map(fn ($x) => $x->tindakan?->namaBentuk())->filter()->unique()->values();
  /* Satuan kerja membaca keadaan tindak lanjutnya sendiri (Rekomendasi::keadaanUntuk()). */
  $keadaan = $r->keadaanUntuk($balai ? $u->satkerAktifId() : null);
  /* Pengakuan BPK atas nilai, bagi yang memantau seluruh rekomendasi (bukan satuan kerja). */
  $bpk = $jenis->melewatiSiptl() && ! $balai && $r->nilaiRek() > 0 && $baris->contains(fn ($x) => $x->sudahSiptl());
  $diakui = $bpk ? $r->nilaiDiakuiBpk() : 0;
  $belumDiakui = $bpk ? $r->sisaNilaiBpk() : 0;
  /* Sudah dan belum sebaris: hijau yang sudah, merah yang belum, abu bila belum ada. */
  $sudahBelum = fn (int $sudah, int $belum, string $kata, string $tuntas) =>
    '<span class="mono" style="color:'.($sudah ? 'var(--ok)' : 'var(--ink-3)').'">'.($sudah ? Tampil::rupiah($sudah) : 'Rp 0').'</span>'
    .'<span class="ket"> &middot; </span>'
    .($belum > 0
      ? '<span style="color:var(--bad)"><span class="mono">'.Tampil::rupiah($belum).'</span> belum '.$kata.'</span>'
      : '<span style="color:var(--ok)">'.$tuntas.'</span>');

  /* Status verifikasi = keputusan Itjen (bukan UKI, bukan BPK); sebutannya ikut jenis laporan. */
  [$nM, $nBM] = [HasilTelaah::M->nama($jenis), HasilTelaah::BM->nama($jenis)];
  [$kM, $kBM] = [mb_strtolower($nM), mb_strtolower($nBM)];
  $ketVerifikasi = [
    'Rangkuman hasil verifikasi Inspektorat untuk '.($balai ? 'penugasan Satker ini' : 'seluruh penugasan pada rekomendasi ini').'.',
    $nM.' jika seluruh penugasan sudah dinyatakan '.$kM.'. Jika masih ada yang belum diverifikasi atau dinyatakan '.$kBM.', rangkumannya '.$nBM.'.',
  ];

  $fakta = [
    ['l' => 'Status verifikasi', 'ket' => $ketVerifikasi,
      'v' => e($keadaan->nama($jenis)), 'c' => $keadaan === HasilTelaah::M ? 'var(--ok)' : 'var(--bad)'],
    ['l' => 'Bentuk tindak lanjut',
      'v' => e($bentuk->isEmpty() ? '—' : ($bentuk->count() === 1 ? $bentuk->first() : $bentuk->count().' tindak lanjut berbeda'))],
    ['l' => $balai ? 'Satuan kerja' : 'Satuan kerja dituju', 'v' => e($r->sebutSatker($peran, $u->satkerAktifId()))],
    ['l' => 'Rencana aksi', 'v' => Tampil::tgl($tgh), 'mono' => true, 'c' => $telatIni ? 'var(--verm)' : null,
      'k' => $telatIni ? Tampil::lamaTelat(\App\Models\Rekomendasi::selisih($tgh)) : null],
    ['l' => 'Target penyelesaian', 'mono' => true, 'v' => $r->target_selesai ? Tampil::tgl($r->target_selesai) : 'tidak ditetapkan'],
    /* Uang: harus dipulihkan, sudah dipulihkan, sudah diakui BPK — sudah dan belum sebaris. */
    ['l' => 'Nilai yang harus dipulihkan',
      'v' => $nilai ? Tampil::rupiah($nilai) : '—', 'mono' => true],
    $dana ? ['l' => 'Sudah dipulihkan', 'ket' => [
        'Total pemulihan yang sudah dicatat dari setoran, perbaikan fisik, atau pengembalian barang.',
        'Lunas berarti total tersebut sudah mencapai nilai yang harus dipulihkan. Ini bukan status pengakuan pemeriksa.',
      ], 'v' => $sudahBelum($dana['masuk'], $dana['sisa'], 'dipulihkan', 'lunas')] : null,
    $angsur ? ['l' => 'Rencana angsuran', 'v' => $angsur['sudah'].' dari '.$angsur['rencana'].' kali', 'k' => $angsur['kunci'] ? 'dikunci' : null] : null,
    $bpk ? ['l' => 'Diakui BPK', 'ket' => [
        'Jumlah nilai kewajiban pada penugasan yang sudah diunggah ke SIPTL dan berstatus SS (Sudah sesuai) menurut BPK.',
        'Jumlah yang belum diakui adalah nilai kewajiban dikurangi nilai yang diakui BPK.',
      ], 'v' => $sudahBelum($diakui, $belumDiakui, 'diakui', 'seluruhnya diakui')] : null,
  ];

  /* Catatan Setba milik tiap tindak lanjut; satuan kerja hanya membaca yang dipikulnya. Hanya di
     mode rincian. */
  $catatanTl = $r->tindakan->filter(fn ($tk) => trim((string) $tk->catatan) !== ''
    && (! $balai || $baris->contains('tindakan_id', $tk->id)));

  /* Tertutup: tersisa keterangan yang paling sering ditanya — beres atau belum, batas waktu,
     sisa pemulihan. */
  $ringkas = array_values(array_filter([
    $fakta[0],
    $tgh ? ['l' => 'Rencana aksi', 'v' => e(Tampil::tgl($tgh)), 'mono' => true, 'c' => $telatIni ? 'var(--verm)' : null,
      'k' => $telatIni ? Tampil::lamaTelat(\App\Models\Rekomendasi::selisih($tgh)) : null] : null,
    /* Uang yang masih tertinggal: pemulihan yang belum masuk, lalu nilai yang belum diakui BPK
       (bukan satuan kerja). */
    $dana
      ? ($dana['sisa'] > 0
          ? ['l' => 'Belum dipulihkan', 'v' => e(Tampil::rupiah($dana['sisa'])), 'mono' => true, 'c' => 'var(--bad)']
          : ['l' => 'Pemulihan', 'v' => 'lunas', 'c' => 'var(--ok)'])
      : ($nilai ? ['l' => 'Nilai yang harus dipulihkan',
          'v' => e(Tampil::rupiah($nilai)), 'mono' => true] : null),
    $bpk
      ? ($belumDiakui > 0
          ? ['l' => 'Belum diakui BPK', 'v' => e(Tampil::rupiah($belumDiakui)), 'mono' => true, 'c' => 'var(--bad)']
          : ['l' => 'Diakui BPK', 'v' => 'seluruhnya', 'c' => 'var(--ok)'])
      : null,
  ]));
@endphp

<div id="r-kepala" class="kepalarek">
  <div class="tanda">
    <span class="pil biru">Ref LHP <span class="asli">{{ $r->refLhp() ?: $r->kode }}</span></span>
    <x-sumber :j="$jenis" />
    <x-kode ket="Ref IDT" :isi="$r->refIdt()" />
  </div>

  <h2>{{ $r->uraian }}</h2>

  {{-- Tanpa skrip rinciannya yang tampil; skrip menukarnya dengan ringkasan. --}}
  <div data-baca>
  <div class="faktaringkas" data-baca-ringkas hidden>
    @foreach($ringkas as $f)
      <span>
        <span class="lbl">{{ $f['l'] }}</span>
        <b @if(! empty($f['mono'])) class="mono" @endif @if(! empty($f['c'])) style="color:{{ $f['c'] }}" @endif>{!! $f['v'] !!}@if(! empty($f['k']))<small>{{ $f['k'] }}</small>@endif</b>
      </span>
    @endforeach
  </div>

  <div data-baca-rinci>
  <x-fakta :isi="$fakta" />

  @if($catatanTl->isNotEmpty())
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--rule-2)">
      <div class="lbl" style="margin-bottom:4px">Catatan Setba untuk satuan kerja</div>
      @foreach($catatanTl as $tk)
        <div style="font-size:13px;margin-bottom:4px">
          @if($r->tindakan->count() > 1)<b>{{ $tk->namaBentuk() }}: </b>@endif{{ $tk->catatan }}
        </div>
      @endforeach
    </div>
  @endif
  </div>

  <div class="bacaselengkap">
    <button type="button" class="lipat-ajak" aria-expanded="true" data-baca-alih>
      <span data-baca-teks>Sembunyikan rincian</span><x-ikon n="ChevronDown" :s="14" />
    </button>
  </div>
  </div>
</div>
