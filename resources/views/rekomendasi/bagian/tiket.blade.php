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
  $perluTk = $isi->filter(fn ($x) => RincianController::aksiBaris($r, $x, $peran, $u->satkerAktifId()))->count();
  $adaBpk = $jenis->melewatiSiptl();
  $idTiket = 'tiket-'.$tk->id;
  /* Jumlah yang dinyatakan sesuai BPK — hanya LHP, dan hanya sesudah ada yang naik. */
  $naikTk = $adaBpk && $isi->contains(fn ($x) => $x->sudahSiptl());
  $sesuaiTk = $isi->filter(fn ($x) => $x->sudahSiptl() && $x->status_bpk?->value === 'SS')->count();
@endphp

<div class="tlblok tl-tiket" data-tiket>
  <button type="button" class="kep" aria-expanded="false" aria-controls="{{ $idTiket }}" data-buka-tiket>
    <span class="tiket-utama">
      <span class="no">{{ $k + 1 }}</span>
      <span class="inti">
        <span class="judultl">
          {{-- Bentuk di luar daftar dari data historis tampil dengan teks aslinya. --}}
          <span class="jenistl">Bentuk tindak lanjut{{ ! $tk->bentuk && $tk->bentuk_teks ? ' · teks data lama' : '' }}</span>
          <span class="nama">{{ $tk->namaBentuk() }}</span>
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
              <x-ikon n="Calendar" :s="12" />Rencana aksi {{ Tampil::tgl($tk->tgl_renaksi) }}{{ $tk->klasifikasi_renaksi ? ' · '.$tk->klasifikasi_renaksi : '' }}
            </span>
          @elseif($tk->klasifikasi_renaksi)
            <span class="tiket-info"><x-ikon n="Calendar" :s="12" />Rencana aksi {{ $tk->klasifikasi_renaksi }}</span>
          @endif
          @if($tk->keterangan_paket)
            <span class="tiket-info tiket-paket" title="Keterangan paket: {{ $tk->keterangan_paket }}"><x-ikon n="Tag" :s="12" /><span class="tiket-paket-isi">{{ $tk->keterangan_paket }}</span></span>
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

  {{-- Tabel satuan kerja; tertutup oleh skrip, jadi tanpa skrip isinya tetap terbaca. --}}
  <div class="tw tl-tabel-wrap" id="{{ $idTiket }}" data-isi-tiket>
    <table class="tabtl tl-tabel" aria-label="Tindak lanjut satuan kerja" data-tabel-tl data-satu="{{ $isi->count() === 1 ? 1 : 0 }}">
      {{-- Lebar kolom lewat kelas; di bawah 1024 px tiap baris jadi blok bertumpuk. --}}
      <colgroup>
        <col class="c-no"><col><col class="c-nilai"><col><col>
        <col class="c-hasil"><col class="c-hasil">
        @if($adaBpk)<col class="c-hasil">@endif<col class="c-aksi">
      </colgroup>
      <thead><tr>
        <th class="num kolno">No</th><th>Satuan kerja</th><th class="num">Nilai</th>
        <th>Posisi berkas</th>
        <th title="Catatan pada peristiwa terbaru di Progres tindak lanjut">Catatan terakhir</th>
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
            $aks = RincianController::aksiBaris($r, $x, $peran, $u->satkerAktifId());
            $kunci = $x->tindakan_id.'|'.$x->satker_id;
            /* Kolom Catatan terakhir = baris "Terbaru" di tab Riwayat baris ini. */
            $catatan = \App\Support\RiwayatTindakLanjut::catatanTerakhir($riwayat, $kunci);
            $kartuRiwayat = collect($riwayat)->firstWhere('kunci', $kunci);
            $idRinci = 'rinci-'.$x->id;
            /* Tanggal SIPTL menempel di keterangan posisinya. */
            $tglSiptl = ! $adaBpk || ! $x->siptl_tanggal ? ''
              : (in_array($x->status_bpk?->value, ['SS', 'BS'], true) && $x->tgl_pantau
                ? ' · dipantau '.Tampil::tgl($x->tgl_pantau)
                : ' · diunggah '.Tampil::tgl($x->siptl_tanggal));
            $perbaikan = in_array($pos, [PosisiBerkas::SATKER, PosisiBerkas::SETBA_KEMBALI], true) ? $x->alasan_perbaikan : '';
            /* Draf yang sedang disiapkan terbaca di kolom posisi — untuk Setba dan pemiliknya saja. */
            $drafBaris = $pos === PosisiBerkas::SATKER
              && in_array($peran, [\App\Enums\PeranPengguna::SETBA, \App\Enums\PeranPengguna::SATKER], true) ? $x->draf : null;
            $penilaian = [
              ['nama' => 'Validasi UKI', 'hasil' => $x->hasil_uki ? $x->hasil_uki->nama($jenis) : 'Belum tercatat'],
              ['nama' => 'Verifikasi Inspektorat', 'hasil' => $x->hasil ? $x->hasil->nama($jenis) : 'Belum tercatat'],
            ];
            if ($adaBpk) {
              /* Catatan BPK ikut terbaca saat keping SIPTL-nya disentuh. */
              $penilaian[] = ['nama' => 'Penilaian BPK', 'hasil' => $x->sudahSiptl()
                ? ($x->status_bpk ?? \App\Enums\StatusTindakLanjut::BT)->pendek() : 'Belum tercatat',
                'ket' => $x->sudahSiptl() ? (string) $x->catatan_bpk : ''];
            }
          @endphp
          <tr class="bukaan" data-baris-tl="{{ $idRinci }}" data-ada-aksi="{{ $aks ? 1 : 0 }}"
            data-satker="{{ $x->satker_id }}" data-tindakan="{{ $x->tindakan_id }}">
            <td class="num kolno mono">{{ $i + 1 }}</td>
            <td class="k-nama"><button type="button" class="tl-buka-nama" aria-expanded="false" aria-controls="{{ $idRinci }}" title="{{ $x->satker->nama }}" data-buka-baris>
              <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>{{ $x->satker->namaPendek() }}
            </button></td>
            {{-- "nihil", bukan "kosong": kelas `kosong` milik kotak "tidak ada data". --}}
            <td class="num mono k-nilai{{ (int) $x->nilai > 0 ? '' : ' nihil' }}" data-label="Nilai" @if((int) $x->nilai > 0) style="font-weight:600" @endif>{{ (int) $x->nilai > 0 ? Tampil::rupiah($x->nilai) : '—' }}</td>
            <td class="k-posisi"><span>{{ $info['judul'] }}</span><span class="tl-di-meja">{{ $info['selesai'] ? 'Selesai' : 'Di '.$info['pemegang'] }}{{ $tglSiptl }}</span>@if($x->tanggal_acuan_historis)<span class="tl-di-meja">Historis · acuan {{ Tampil::tgl($x->tanggal_acuan_historis) }}</span>@endif
              @if($drafBaris)<span class="tl-di-meja" data-draf-baris>Draf disimpan {{ Tampil::tgl($drafBaris->updated_at ?? $drafBaris->terakhir) }}</span>@endif</td>
            <td class="k-catatan" data-label="Catatan terakhir" data-catatan-terakhir>
              @if($catatan)
                <span class="tl-catatan{{ $catatan['teks'] === '' ? ' nihil' : '' }}" @if($catatan['teks'] !== '') title="{{ $catatan['teks'] }}" @endif>{{ $catatan['teks'] !== '' ? $catatan['teks'] : 'Tidak ada catatan tambahan.' }}</span>
                <span class="tl-di-meja">{{ $catatan['ket'] }}</span>
              @else
                <span class="tl-di-meja" title="Belum ada peristiwa di Progres tindak lanjut">—</span>
              @endif
            </td>
            @foreach($penilaian as $p)
              @php
                /* Sesuai (LHA) = S; SS milik BPK (Sudah sesuai). */
                $kode = ['Memadai' => 'M', 'Belum memadai' => 'BM', 'Sesuai' => 'S', 'Belum sesuai' => 'BS',
                  'Sudah sesuai' => 'SS', 'Belum ditindaklanjuti' => 'BT'][$p['hasil']] ?? null;
                $nada = in_array($kode, ['M', 'S', 'SS'], true) ? 'baik' : (in_array($kode, ['BM', 'BS'], true) ? 'kurang' : '');
                $judulKeping = ! empty($p['ket']) ? $p['hasil'].' — '.$p['ket'] : $p['hasil'];
              @endphp
              {{-- Nama pendek supaya tombol Kerjakan tidak terdorong keluar layar. --}}
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
                  @include('rekomendasi.bagian.siptl-baris', ['x' => $x])
                  @include('rekomendasi.bagian.verifikasi-historis', ['x' => $x])
                  <div class="tl-panel-nav">
                    <div class="tl-tabs" role="tablist" aria-label="Isi tindak lanjut">
                      <button type="button" role="tab" aria-selected="true" data-tab-tl="bukti"><x-ikon n="Paperclip" :s="14" />Bukti &amp; tanggapan</button>
                      @if($aks)
                        <button type="button" role="tab" aria-selected="false" data-tab-tl="kerja"><x-ikon n="ListChecks" :s="14" />Kerjakan</button>
                      @endif
                      <button type="button" role="tab" aria-selected="false" data-tab-tl="riwayat"><x-ikon n="Clock" :s="14" />Progres</button>
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
                  {{-- Ujung rincian ditandai dengan nama pemiliknya, plus jalan menutupnya. --}}
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
