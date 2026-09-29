@php
  use App\Support\Tampil;
  use App\Enums\HasilTelaah;

  /* Satuan kerja melihat tanggal yang mengikat dirinya — yang paling awal di
     antara tindak lanjut yang membebaninya. */
  $tgh = $balai ? $r->renaksiUntuk($u->satker_id) : $r->tenggat_jawab;
  $telatIni = $tgh && $tgh->copy()->startOfDay()->lt(now()->startOfDay()) && ! $r->tanpaTenggat();
  $nilai = $balai ? $baris->sum('nilai') : (int) $r->nilai_pulih;
  $dana = $r->progresDana($balai ? $u->satker_id : null);
  $angsur = $r->rencanaAngsur();
  $bentuk = $baris->map(fn ($x) => $x->tindakan?->namaBentuk())->filter()->unique()->values();
  /* Satuan kerja membaca keadaan tindak lanjutnya sendiri (27 Sep) — lihat
     Rekomendasi::keadaanUntuk(). */
  $keadaan = $r->keadaanUntuk($balai ? $u->satker_id : null);
  /* Pengakuan BPK atas nilainya: LHP yang barisnya sudah ada yang naik ke
     SIPTL, bagi yang memantau seluruh rekomendasi. Satuan kerja tidak —
     angkanya menjumlahkan seluruh satuan kerja. */
  $bpk = $jenis->melewatiSiptl() && ! $balai && $r->nilaiRek() > 0 && $baris->contains(fn ($x) => $x->siptl_tanggal);
  $diakui = $bpk ? $r->nilaiDiakuiBpk() : 0;
  $belumDiakui = $bpk ? $r->sisaNilaiBpk() : 0;
  /* Yang sudah dan yang belum di satu baris — padanan SudahBelum: hijau yang
     sudah, merah yang belum, abu kalau belum ada sama sekali. */
  $sudahBelum = fn (int $sudah, int $belum, string $kata, string $tuntas) =>
    '<span class="mono" style="color:'.($sudah ? 'var(--ok)' : 'var(--ink-3)').'">'.($sudah ? Tampil::rupiah($sudah) : 'Rp 0').'</span>'
    .'<span class="ket"> &middot; </span>'
    .($belum > 0
      ? '<span style="color:var(--bad)"><span class="mono">'.Tampil::rupiah($belum).'</span> belum '.$kata.'</span>'
      : '<span style="color:var(--ok)">'.$tuntas.'</span>');

  $fakta = [
    ['l' => 'Status verifikasi', 'ket' => $balai ? [
        'Keadaan tindak lanjut satuan kerja Anda menurut Inspektorat: '.mb_strtolower(HasilTelaah::M->nama($jenis)).' kalau seluruh tindak lanjut Anda di rekomendasi ini sudah '.mb_strtolower(HasilTelaah::M->nama($jenis)).'.',
        'Sampai Inspektorat hanya ada dua putusan. Kode BS, BT, dan SS milik BPK, dan baru muncul sesudah berkasnya dinilai di SIPTL.',
      ] : [
        'Keadaan rekomendasi ini menurut BPSDM: memadai kalau seluruh satuan kerja di dalamnya sudah memadai.',
        'Nomor surat CHV-nya ada di riwayat verifikasi di bawah. Suratnya rujukan dokumennya, bukan yang menentukan keadaannya — banyak rekomendasi lama yang sudah beres tapi nomor suratnya tidak pernah tercatat.',
        'Sampai Inspektorat hanya ada dua putusan: memadai dan belum memadai. Kode BS, BT, dan SS milik BPK, dan baru muncul sesudah berkasnya dinilai di SIPTL.',
        'Belum memadai adalah keadaan bakunya — termasuk selama suratnya belum terbit. Yang menjawab sudah diperiksa siapa adalah Posisi berkas.',
        'Satu satuan kerja belum beres membuat seluruh rekomendasi belum memadai, walau satuan kerja lain sudah.',
      ], 'v' => e($keadaan->nama($jenis)), 'c' => $keadaan === HasilTelaah::M ? 'var(--ok)' : 'var(--bad)'],
    ['l' => 'Bentuk tindak lanjut',
      'v' => e($bentuk->isEmpty() ? '—' : ($bentuk->count() === 1 ? $bentuk->first() : $bentuk->count().' tindak lanjut berbeda'))],
    ['l' => $balai ? 'Satuan kerja' : 'Satuan kerja dituju', 'v' => e($r->sebutSatker($peran, $u->satker_id))],
    ['l' => 'Rencana aksi', 'v' => Tampil::tgl($tgh), 'mono' => true, 'c' => $telatIni ? 'var(--verm)' : null,
      'k' => $telatIni ? Tampil::lamaTelat(\App\Models\Rekomendasi::selisih($tgh)) : null],
    ['l' => 'Target penyelesaian', 'mono' => true, 'v' => $r->target_selesai ? Tampil::tgl($r->target_selesai) : 'tidak ditetapkan'],
    /* Uangnya: tiga pertanyaan berurutan (27 Sep) — berapa yang harus
       dipulihkan, berapa yang sudah dipulihkan, berapa yang sudah diakui BPK —
       dengan yang sudah dan yang belum di baris yang sama. Dulu lima baris dari
       dua ukuran yang terbaca satu rantai: "Sisa: lunas" menghitung PEMULIHAN,
       "Belum diakui BPK" menghitung PENGAKUAN (Sisa nilai di lembar pemantauan
       mereka justru yang kedua). Kewajibannya tidak lagi merah: merah untuk
       yang belum saja. Padanan kepala rincian di prototipe. */
    ['l' => 'Nilai yang harus dipulihkan',
      'v' => $nilai ? Tampil::rupiah($nilai) : '—', 'mono' => true],
    $dana ? ['l' => 'Sudah dipulihkan', 'ket' => [
        'Yang sudah dipulihkan satuan kerja — lewat setoran ke kas negara, atau perbaikan dan pengembalian barang — sesuai buktinya.',
        $bpk
          ? 'Lunas artinya seluruh nilainya sudah dipulihkan. Pengakuan BPK dihitung terpisah di baris Diakui BPK.'
          : 'Lunas artinya seluruh nilainya sudah dipulihkan.',
      ], 'v' => $sudahBelum($dana['masuk'], $dana['sisa'], 'dipulihkan', 'lunas')] : null,
    $angsur ? ['l' => 'Rencana angsuran', 'v' => $angsur['sudah'].' dari '.$angsur['rencana'].' kali', 'k' => $angsur['kunci'] ? 'dikunci' : null] : null,
    $bpk ? ['l' => 'Diakui BPK', 'ket' => [
        'Bagian nilai rekomendasi yang sudah dinilai Sudah Sesuai (SS) oleh BPK di SIPTL.',
        'Yang sudah dipulihkan belum tentu langsung diakui: BPK menilai buktinya dan bisa mengakui sebagian. Yang belum diakui inilah Sisa nilai SIPTL di lembar pemantauan.',
      ], 'v' => $sudahBelum($diakui, $belumDiakui, 'diakui', 'seluruhnya diakui')] : null,
    /* Baris "Draf belum dikirim" dibuang (28 Sep) — hitungan kiriman
       otomatisnya tetap ada di panel kerja. Padanan prototipe. */
  ];

  /* Catatan Setba milik TIAP tindak lanjut; satuan kerja cuma membaca catatan
     tindak lanjut yang dipikulnya. Hanya di mode rincian (28 Sep). */
  $catatanTl = $r->tindakan->filter(fn ($tk) => trim((string) $tk->catatan) !== ''
    && (! $balai || $baris->contains('tindakan_id', $tk->id)));

  /* Tertutup: judulnya tetap terbaca, dan yang tersisa di bawahnya cuma
     keterangan yang paling sering ditanya — sudah beres atau belum, kapan
     batasnya, dan berapa yang masih harus dipulihkan. Bentuk tindak lanjut dan
     satuan kerjanya tidak diulang: tiket di bawah sudah menyebut keduanya. */
  $ringkas = array_values(array_filter([
    $fakta[0],
    $tgh ? ['l' => 'Rencana aksi', 'v' => e(Tampil::tgl($tgh)), 'mono' => true, 'c' => $telatIni ? 'var(--verm)' : null,
      'k' => $telatIni ? Tampil::lamaTelat(\App\Models\Rekomendasi::selisih($tgh)) : null] : null,
    /* Uangnya, yang masih tertinggal saja: pemulihan yang belum masuk, lalu —
       bagi yang memantau seluruh rekomendasi — nilai yang belum diakui BPK.
       "Sisa nilai" tidak dipakai lagi di sini: di lembar pemantauan nama itu
       milik nilai yang belum diakui, bukan pemulihan yang belum masuk. */
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
    /* Draf dan Catatan Setba tidak disebut di ringkasan (28 Sep). */
  ]));
@endphp

<div id="r-kepala" class="kepalarek">
  <div class="tanda">
    <span class="pil biru">Ref LHP <span class="asli">{{ $r->refLhp() ?: $r->kode }}</span></span>
    <x-sumber :j="$jenis" />
    <x-kode ket="Ref IDT" :isi="$r->refIdt()" />
  </div>

  <h2>{{ $r->uraian }}</h2>

  {{-- Judul, nomor, dan kode temuan pindah ke kartu "Uraian temuan" (20 Sep). --}}

  {{-- Baca selengkapnya. Tanpa skrip rinciannya yang tampil; skrip menukarnya
       dengan ringkasan saat halaman siap. --}}
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

  @if($r->alasanTd)
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--rule-2)">
      <div class="lbl" style="margin-bottom:4px">Alasan sah tidak dapat ditindaklanjuti</div>
      <div style="font-size:13px">{{ $r->alasanTd->nama }}</div>
      @if($r->catatan_td)<div style="font-size:13px;color:var(--ink-2);margin-top:4px">{{ $r->catatan_td }}</div>@endif
    </div>
  @endif
  </div>

  {{-- Ajakan membaca selengkapnya. Judulnya sendiri tidak ikut terlipat: itu
       yang menjawab "ini rekomendasi apa". --}}
  <div class="bacaselengkap">
    <button type="button" class="lipat-ajak" aria-expanded="true" data-baca-alih>
      <span data-baca-teks>Sembunyikan rincian</span><x-ikon n="ChevronDown" :s="14" />
    </button>
  </div>
  </div>
</div>
