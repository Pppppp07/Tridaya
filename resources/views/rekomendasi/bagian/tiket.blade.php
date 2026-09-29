@php
  use App\Enums\HasilTelaah;
  use App\Enums\PosisiBerkas;
  use App\Http\Controllers\RincianController;
  use App\Support\Tampil;

  /* Satuan kerja hanya melihat bagiannya sendiri — `$baris` sudah dipangkas. */
  $isi = $baris->where('tindakan_id', $tk->id)->values();
@endphp

@if($isi->isNotEmpty())
@php
  $lewatRenaksi = $tk->tgl_renaksi && $tk->tgl_renaksi->copy()->startOfDay()->lt(now()->startOfDay()) && ! $r->tanpaTenggat();
  $memadaiTk = $isi->filter(fn ($x) => $x->hasil === HasilTelaah::M)->count();
  $perluTk = $isi->filter(fn ($x) => RincianController::aksiBaris($r, $x, $peran, $u->satker_id))->count();
  $adaBpk = $jenis->melewatiSiptl();
  $idTiket = 'tiket-'.$tk->id;
  /* Berapa yang sudah dinyatakan sesuai oleh BPK — hanya LHP, dan hanya begitu
     ada satu yang naik; sebelum itu angkanya selalu nol. */
  $naikTk = $adaBpk && $isi->contains(fn ($x) => $x->siptl_tanggal);
  $sesuaiTk = $isi->filter(fn ($x) => $x->siptl_tanggal && in_array($x->status_bpk?->value, ['SS', 'TD'], true))->count();
@endphp

<div class="tlblok tl-tiket" data-tiket>
  <button type="button" class="kep" aria-expanded="false" aria-controls="{{ $idTiket }}" data-buka-tiket>
    <span class="tiket-utama">
      <span class="no">{{ $k + 1 }}</span>
      <span class="inti">
        {{-- Nama tiket diberi keterangan jenisnya: "Lainnya sesuai LHP" saja
             tidak menyebut bahwa itu nama bentuk tindak lanjut. --}}
        <span class="judultl">
          <span class="jenistl">Bentuk tindak lanjut</span>
          <span class="nama">{{ $tk->bentuk?->nama ?? 'Tindak lanjut' }}</span>
        </span>
        <span class="meta">
          {{-- Angka satuan kerja tidak disebutkan kepada satuan kerja. --}}
          @if(! $balai)
            <span class="tiket-info"><x-ikon n="Users" :s="12" />{{ $isi->count() }} satuan kerja</span>
            <span class="tiket-info"><x-ikon n="CheckCircle2" :s="12" />{{ $memadaiTk }} {{ mb_strtolower(HasilTelaah::M->nama($jenis)) }}</span>
            @if($naikTk)
              <span class="tiket-info"><x-ikon n="Landmark" :s="12" />{{ $sesuaiTk }} sesuai BPK</span>
            @endif
          @endif
          @if($tk->tgl_renaksi)
            <span class="tiket-info" @if($lewatRenaksi) style="color:var(--bad)" @endif>
              <x-ikon n="Calendar" :s="12" />Rencana aksi {{ Tampil::tgl($tk->tgl_renaksi) }}
            </span>
          @endif
        </span>
      </span>
    </span>
    <span class="tiket-sisi">
      @if($perluTk > 0)<span class="pil biru">{{ $perluTk }} perlu dikerjakan</span>@endif
      <span class="ajak">
        <span data-ajak-tutup hidden>Tutup rincian</span><span data-ajak-buka>Lihat satuan kerja</span>
        <span class="panahbaris"><x-ikon n="ChevronRight" :s="14" /></span>
      </span>
    </span>
  </button>

  {{-- Tabel satuan kerja — padanan RuangTindakLanjut. Tertutup sejak awal;
       skrip yang menyembunyikannya, jadi tanpa skrip isinya tetap terbaca. --}}
  <div class="tw tl-tabel-wrap" id="{{ $idTiket }}" data-isi-tiket>
    <table class="tabtl tl-tabel" aria-label="Tindak lanjut satuan kerja" data-tabel-tl data-satu="{{ $isi->count() === 1 ? 1 : 0 }}">
      {{-- Lebar kolomnya lewat kelas: di layar sempit diatur ulang, dan di bawah
           1024 px tiap baris jadi blok bertumpuk (22 Sep). --}}
      <colgroup>
        <col class="c-no"><col><col class="c-nilai"><col><col>
        <col class="c-hasil"><col class="c-hasil">
        @if($adaBpk)<col class="c-hasil">@endif<col class="c-aksi">
      </colgroup>
      <thead><tr>
        <th class="num kolno">No</th><th>Satuan kerja</th><th class="num">Nilai</th>
        <th>Posisi berkas</th>
        <th title="Catatan pada peristiwa terbaru di riwayat tindak lanjut">Catatan terakhir</th>
        <th class="selhasil" title="Hasil validasi UKI">UKI</th>
        <th class="selhasil" title="Hasil verifikasi Inspektorat">Itjen</th>
        @if($adaBpk)<th class="selhasil" title="Status BPK yang dicatat dari SIPTL">SIPTL</th>@endif
        <th class="kolaksi">Aksi</th>
      </tr></thead>
      <tbody>
        @foreach($isi as $i => $x)
          @php
            $pos = $x->pos();
            $info = $x->presentasi($jenis);
            $aks = RincianController::aksiBaris($r, $x, $peran, $u->satker_id);
            $kunci = $x->tindakan_id.'|'.$x->satker_id;
            /* Kolom Catatan terakhir = baris "Terbaru" di tab Riwayat baris ini. */
            $catatan = \App\Support\RiwayatTindakLanjut::catatanTerakhir($riwayat, $kunci);
            $kartuRiwayat = collect($riwayat)->firstWhere('kunci', $kunci);
            $idRinci = 'rinci-'.$x->id;
            /* Tanggal SIPTL menempel di keterangan posisinya — dulu kolom
               sendiri di tabel kedua kartu Urusan SIPTL. */
            $tglSiptl = ! $adaBpk || ! $x->siptl_tanggal ? ''
              : (in_array($x->status_bpk?->value, ['SS', 'TD', 'BS'], true) && $x->tgl_pantau
                ? ' · dipantau '.Tampil::tgl($x->tgl_pantau)
                : ' · diunggah '.Tampil::tgl($x->siptl_tanggal));
            $perbaikan = in_array($pos, [PosisiBerkas::SATKER, PosisiBerkas::SETBA_KEMBALI], true) ? $x->alasan_perbaikan : '';
            $penilaian = [
              ['nama' => 'Validasi UKI', 'hasil' => $x->hasil_uki ? $x->hasil_uki->nama($jenis) : 'Belum tercatat'],
              ['nama' => 'Verifikasi Inspektorat', 'hasil' => $x->hasil ? $x->hasil->nama($jenis) : 'Belum tercatat'],
            ];
            if ($adaBpk) {
              /* Catatan BPK ikut terbaca saat keping SIPTL-nya disentuh. */
              $penilaian[] = ['nama' => 'Penilaian BPK', 'hasil' => $x->siptl_tanggal
                ? ($x->status_bpk ?? \App\Enums\StatusTindakLanjut::BT)->pendek() : 'Belum diunggah ke SIPTL',
                'ket' => $x->siptl_tanggal ? (string) $x->catatan_bpk : ''];
            }
          @endphp
          <tr class="bukaan" data-baris-tl="{{ $idRinci }}" data-ada-aksi="{{ $aks ? 1 : 0 }}"
            data-satker="{{ $x->satker_id }}" data-tindakan="{{ $x->tindakan_id }}">
            <td class="num kolno mono">{{ $i + 1 }}</td>
            <td class="k-nama"><button type="button" class="tl-buka-nama" aria-expanded="false" aria-controls="{{ $idRinci }}" title="{{ $x->satker->nama }}" data-buka-baris>
              <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>{{ $x->satker->namaPendek() }}
            </button></td>
            {{-- "nihil", bukan "kosong" (27 Sep): kelas kosong milik kotak "tidak
                 ada data" bergaris putus-putus, dan dulu ikut menggambar kotak itu
                 di tiap sel nilai yang kosong. --}}
            <td class="num mono k-nilai{{ (int) $x->nilai > 0 ? '' : ' nihil' }}" data-label="Nilai" @if((int) $x->nilai > 0) style="font-weight:600" @endif>{{ (int) $x->nilai > 0 ? Tampil::rupiah($x->nilai) : '—' }}</td>
            <td class="k-posisi"><span>{{ $info['judul'] }}</span><span class="tl-di-meja">{{ $info['selesai'] ? 'Selesai' : 'Di '.$info['pemegang'] }}{{ $tglSiptl }}</span></td>
            <td class="k-catatan" data-label="Catatan terakhir" data-catatan-terakhir>
              @if($catatan)
                <span class="tl-catatan{{ $catatan['teks'] === '' ? ' nihil' : '' }}" @if($catatan['teks'] !== '') title="{{ $catatan['teks'] }}" @endif>{{ $catatan['teks'] !== '' ? $catatan['teks'] : 'Tidak ada catatan tambahan.' }}</span>
                <span class="tl-di-meja">{{ $catatan['ket'] }}</span>
              @else
                <span class="tl-di-meja" title="Belum ada peristiwa di riwayat tindak lanjut">—</span>
              @endif
            </td>
            @foreach($penilaian as $p)
              @php
                $kode = ['Memadai' => 'M', 'Belum memadai' => 'BM', 'Sesuai' => 'SS', 'Belum sesuai' => 'BS',
                  'Sudah sesuai' => 'SS', 'Belum ditindaklanjuti' => 'BT', 'Tidak dapat ditindaklanjuti' => 'TD'][$p['hasil']] ?? null;
                $nada = in_array($kode, ['M', 'SS'], true) ? 'baik' : (in_array($kode, ['BM', 'BS'], true) ? 'kurang' : '');
                $judulKeping = ! empty($p['ket']) ? $p['hasil'].' — '.$p['ket'] : $p['hasil'];
              @endphp
              {{-- Nama pendeknya sama dengan kepala kolom — di layar sempit nama
                   panjang penilainya mendorong tombol Kerjakan keluar layar. --}}
              <td class="selhasil k-hasil{{ $loop->index }}" data-label="{{ ['UKI', 'Itjen', 'SIPTL'][$loop->index] ?? $p['nama'] }}">
                @if($kode)
                  <span class="tl-status {{ $nada }}" title="{{ $judulKeping }}" aria-label="{{ $judulKeping }}">{{ $kode }}</span>
                @else
                  <span class="tl-di-meja" title="{{ $p['hasil'] }}" aria-label="{{ $p['hasil'] }}">—</span>
                @endif
              </td>
            @endforeach
            <td class="kolaksi">
              <button type="button" class="btn btn-s{{ $aks ? ' btn-p' : '' }}" aria-controls="{{ $idRinci }}" aria-expanded="false" data-kerjakan>
                <span data-label-tutup hidden><x-ikon n="ChevronUp" :s="13" />Tutup</span>
                <span data-label-buka><x-ikon n="ListChecks" :s="13" />{{ $aks ? 'Kerjakan' : 'Lihat' }}</span>
              </button>
            </td>
          </tr>
          <tr class="lebar" data-rinci-tl="{{ $idRinci }}">
            <td colspan="{{ $adaBpk ? 9 : 8 }}">
              <div id="{{ $idRinci }}" class="isilebar">
                <section class="tl-detail" aria-label="Tindak lanjut {{ $x->satker->namaPendek() }}" data-detail-tl>
                  @include('rekomendasi.bagian.rel-baris', ['posisi' => $pos, 'kembaliDari' => $x->kembali_dari,
                    'nama' => $x->satker->namaPendek(), 'keadaan' => $info['judul']])
                  @include('rekomendasi.bagian.siptl-baris', ['x' => $x])
                  {{-- Catatan pengembalian dulu kotak kuning di sini, di atas deret
                       tab. Sejak 25 Sep baris pertama kartu Bukti & tanggapan. --}}
                  <div class="tl-panel-nav">
                    <div class="tl-tabs" role="tablist" aria-label="Isi tindak lanjut">
                      <button type="button" role="tab" aria-selected="true" data-tab-tl="bukti"><x-ikon n="Paperclip" :s="14" />Bukti &amp; tanggapan</button>
                      @if($aks)
                        <button type="button" role="tab" aria-selected="false" data-tab-tl="kerja"><x-ikon n="ListChecks" :s="14" />Kerjakan</button>
                      @endif
                      {{-- Dulu link "Lihat riwayat" di ujung kanan bilah ini, yang
                           menggulir ke kartu riwayat di bawah tabel. Kata Hizkia
                           (21 Sep): "tombol lihat riwayat dipindahkan ke samping
                           tombol bukti, kerjakan". --}}
                      <button type="button" role="tab" aria-selected="false" data-tab-tl="riwayat"><x-ikon n="Clock" :s="14" />Riwayat</button>
                    </div>
                  </div>
                  <div role="tabpanel" class="tl-panel" data-panel-tl="bukti">
                    @include('rekomendasi.bagian.rinci-satker', ['x' => $x, 'kembali' => $perbaikan
                      ? ['alasan' => $perbaikan, 'dari' => $x->kembali_dari, 'batas' => $x->batas_perbaikan] : null])
                  </div>
                  @if($aks)
                    <div role="tabpanel" class="tl-panel tl-form" data-panel-tl="kerja" hidden>
                      @if($peran === \App\Enums\PeranPengguna::SATKER)
                        @include('rekomendasi.bagian.panel-balai', ['x' => $x])
                      @elseif($pos === PosisiBerkas::SETBA_KEMBALI)
                        @include('rekomendasi.bagian.panel-kirim-ulang', ['x' => $x])
                      @elseif(in_array($pos, [PosisiBerkas::SETBA_TINJAU, PosisiBerkas::SETBA_TERUSKAN], true))
                        @include('rekomendasi.bagian.panel-teruskan', ['x' => $x])
                      @elseif($pos === PosisiBerkas::TUNTAS)
                        @include('rekomendasi.bagian.panel-siptl', ['x' => $x])
                      @else
                        @include('rekomendasi.bagian.panel-periksa', ['x' => $x])
                      @endif
                    </div>
                  @endif
                  @if($kartuRiwayat)
                    <div role="tabpanel" class="tl-panel" data-panel-tl="riwayat" hidden>
                      @include('rekomendasi.bagian.riwayat-baris', ['kartu' => $kartuRiwayat])
                    </div>
                  @endif
                  {{-- Ujung rinciannya ditandai, dengan nama pemiliknya (27 Sep) — dan
                       jalan menutupnya tanpa menggulir kembali ke barisnya. --}}
                  <div class="tl-detail-kaki">
                    <span>Akhir rincian tindak lanjut <b>{{ $x->satker->namaPendek() }}</b></span>
                    <button type="button" class="btn btn-s" data-tutup-rinci><x-ikon n="ChevronUp" :s="13" />Tutup rincian</button>
                  </div>
                </section>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif
