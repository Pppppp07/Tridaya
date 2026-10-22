@php
  use App\Enums\HasilTelaah;
  use App\Enums\StatusTindakLanjut;
  use App\Support\Tampil;

  /* Riwayat satu baris penugasan — isi tab Progres pada tabel tindak lanjut. */
  $SUMBER = \App\Support\RiwayatTindakLanjut::SUMBER;
  $keping = function (?string $kode) use ($jenis) {
      if (! $kode) {
          return '';
      }
      if ($s = StatusTindakLanjut::tryFrom($kode)) {
          return '<span class="cap '.$s->cap().'" title="'.e($s->pendek()).'">'.$kode.'</span>';
      }
      $h = HasilTelaah::tryFrom($kode);

      return $h ? view('components.cap-hasil', ['jenis' => $jenis, 'hasil' => $h])->render() : e($kode);
  };
@endphp

{{-- Satu kartu: kepala berpita abu, pemilih periode (bila lebih dari satu), lalu perjalanan
     berkas dan catatan peristiwanya. --}}
@php
  $jml = count($kartu['baris']);
  $kini = $kartu['periodeKini'];
  $berbabak = $kini > 1;
  $pertama = $kartu['baris'][0]['id'] ?? null;
  $idRw = 'rw-'.str_replace('|', '-', $kartu['kunci']);
@endphp
<x-panel-kerja class="rw-riwayat tl-rw" data-riwayat-baris="{{ $kartu['kunci'] }}" ikon="Clock" judul="Progres"
  :info="[
    'Riwayat pengiriman, pemeriksaan, perubahan status, dan pengembalian untuk satu Satker pada satu bentuk tindak lanjut. Peristiwa terbaru tampil paling atas.',
    'Jalur periode menunjukkan urutan perpindahan berkas. Warna kotak pemeriksa mengikuti hasil UKI, Inspektorat, atau BPK yang tercatat pada tahap tersebut.',
  ]">
  <x-slot:kalimat><span title="{{ $jenis->melewatiSiptl()
    ? 'Satu periode ditutup kalau BPK menolak lewat SIPTL dan berkasnya dikembalikan ke satuan kerja.'
    : 'Satu periode ditutup kalau Inspektorat menilai belum sesuai dan berkasnya dikembalikan ke satuan kerja.' }}">Periode {{ $kini }}</span>{{ $jml ? ' · terbaru '.Tampil::tgl($kartu['baris'][0]['tanggal']) : '' }}</x-slot:kalimat>

  {{-- Pemilih periode, hanya bila lebih dari satu; periode yang sedang berjalan terbuka lebih
       dulu. --}}
  @if($berbabak)
    <div class="rw-periode" role="tablist" aria-label="Pilih periode" data-pilih-periode>
      @foreach(collect($kartu['babak'])->sortBy('k') as $g)
        <button type="button" role="tab" id="{{ $idRw }}-p{{ $g['k'] }}" aria-controls="{{ $idRw }}-isi{{ $g['k'] }}"
          aria-selected="{{ $g['k'] === $kini ? 'true' : 'false' }}" tabindex="{{ $g['k'] === $kini ? 0 : -1 }}"
          @class(['kini' => $g['k'] === $kini]) data-periode="{{ $g['k'] }}">
          <b>Periode {{ $g['k'] }}</b>
          <small>{{ $g['k'] === $kini ? 'berjalan' : ($g['tutup'] ? 'ditutup '.Tampil::tgl($g['tutup']) : 'selesai') }}</small>
        </button>
      @endforeach
    </div>
  @endif

  {{-- Satu isi per periode, terbaru dulu. Penanda terakhir hanya di periode yang berjalan. --}}
  @foreach($kartu['babak'] as $g)
    @php $jalur = $g['k'] === $kini ? $kartu['jalur'] : ($g['jalur'] ?: [['tempat' => 'Satker', 'tanggal' => null]]); @endphp
    <div class="rw-periode-isi" id="{{ $idRw }}-isi{{ $g['k'] }}" data-isi-periode="{{ $g['k'] }}"
      @if($berbabak) role="tabpanel" aria-labelledby="{{ $idRw }}-p{{ $g['k'] }}" @endif @if($g['k'] !== $kini) hidden @endif>
      <div class="fb-subkep kerja-subkep"><b>Perjalanan periode {{ $g['k'] }}</b></div>
      <div class="rw-rute" role="group" tabindex="0" aria-label="Perjalanan berkas periode {{ $g['k'] }}, urut dari awal ke akhir">
        @foreach($jalur as $i => $j)
          @php
            $akhir = $g['k'] === $kini && $i === count($jalur) - 1;
            $hasilJalur = $j['hasil'] ?? null;
          @endphp
          <div class="rw-ruas" style="--i: {{ $i }}">
            @if($i > 0)<x-ikon n="ArrowRight" :s="14" class="rw-panah" />@endif
            <span @class(['rw-tempat', 'kini' => $akhir, 'selesai' => $j['tempat'] === 'Selesai'])
              @if($hasilJalur) data-hasil="{{ $hasilJalur['kelas'] }}" data-peristiwa="{{ $hasilJalur['peristiwa'] }}" @endif
              @if($akhir) aria-current="step" @endif
              title="{{ $j['tanggal'] ? Tampil::tgl($j['tanggal']) : 'awal periode' }}">
              <b>@if($j['tempat'] === 'Selesai')<x-ikon n="CheckCircle2" :s="13" />@endif{{ $j['tempat'] }}</b>
              <small>{{ $j['tanggal'] ? Tampil::tgl($j['tanggal']) : 'Awal periode' }}</small>
            </span>
          </div>
        @endforeach
      </div>

      <div class="fb-subkep kerja-subkep rw-subkep">
        <b>Catatan peristiwa</b>
        @if(count($g['isi']))<span class="fb-ket"><x-ikon n="ChevronDown" :s="13" />Terbaru di atas</span>@endif
      </div>
      @if(! count($g['isi']))
        <p class="kerja-teks kerja-kosong">{{ $jml ? 'Belum ada peristiwa di periode ini.' : 'Belum ada perubahan status yang tercatat untuk tindak lanjut ini.' }}</p>
      @else
      <div class="rw-tabel-wrap">
    <table class="tabriwayat" aria-label="Progres tindak lanjut {{ $kartu['satker']->namaPendek() }}">
      <thead>
        <tr>
          <th scope="col" style="width:118px">Tanggal</th>
          <th scope="col" style="width:96px">Sumber</th>
          <th scope="col" style="width:215px">Peristiwa &amp; hasil</th>
          <th scope="col">Catatan</th>
          @if($kartu['adaSurat'])<th scope="col" style="width:190px">Surat terkait</th>@endif
        </tr>
      </thead>
      <tbody>
          @foreach($g['isi'] as $b)
            @php
              [$kelas, $namaSumber] = $SUMBER[$b['sumber']] ?? ['siptl', $b['sumber'] ?: '—'];
              $terkini = $b['id'] === $pertama;
              $ket = $b['ket'] ?? null;
              $judul = $ket ?: match ($b['sumber']) {
                  'UKI' => 'Hasil validasi UKI',
                  'ITJEN' => 'Hasil verifikasi Inspektorat',
                  'SIPTL' => (($b['dari'] ?? '') === 'BS' && ($b['ke'] ?? '') === 'BT') ? 'Dikirim ulang ke satker'
                      : ((empty($b['dari']) && ($b['ke'] ?? '') === 'BT') ? 'Diunggah ke SIPTL' : 'Hasil penilaian BPK'),
                  default => 'Perubahan status',
              };
              $ke = $b['ke'] ?? '';
              $arti = $ke ? (StatusTindakLanjut::tryFrom($ke)?->pendek() ?? HasilTelaah::tryFrom($ke)?->nama($jenis) ?? '') : '';
              $catatan = (string) ($b['catatan'] ?? '');
              /* Jam pencatatan (24 jam). Bila dicatat pada hari lain dari tanggal peristiwa,
                 tanggal pencatatannya ikut disebut. */
              $dicatat = ! empty($b['pelaku']['waktu']) ? \Illuminate\Support\Carbon::parse($b['pelaku']['waktu']) : null;
              $hariSama = $dicatat && $dicatat->toDateString() === (string) $b['tanggal'];
            @endphp
            <tr @class(['terkini' => $terkini])>
              <td class="rw-tanggal">
                <time datetime="{{ $hariSama ? $dicatat->format('Y-m-d\TH:i') : $b['tanggal'] }}">{{ Tampil::tgl($b['tanggal']) }}</time>
                @if($hariSama)
                  <span class="rw-pukul">{{ $dicatat->format('H.i') }} WIB</span>
                @elseif($dicatat)
                  <span class="rw-dicatat">dicatat <time datetime="{{ $dicatat->format('Y-m-d\TH:i') }}">{{ Tampil::tglJam($dicatat) }} WIB</time></span>
                @endif
                @if($terkini)<span class="rw-terbaru">Terbaru</span>@endif
              </td>
              {{-- Kelas rw-sel-*: di panel sempit tiap peristiwa jadi blok bertumpuk. --}}
              <td class="rw-sumber"><span class="asal {{ $kelas }}">{{ $namaSumber }}</span></td>
              <td class="rw-sel-peristiwa">
                <div class="rw-peristiwa">
                  <div class="rw-judul-peristiwa">{{ $judul }}</div>
                  @if(! $ket && $ke)
                    <div class="rw-perubahan" aria-label="{{ ! empty($b['dari']) && $b['dari'] !== $ke ? 'Status '.$b['dari'].' menjadi '.$ke : 'Status '.$ke }}">
                      @if(! empty($b['dari']) && $b['dari'] !== $ke)
                        <span class="rw-status-lama">{!! $keping($b['dari']) !!}</span><x-ikon n="ArrowRight" :s="13" />
                      @endif
                      {!! $keping($ke) !!}
                    </div>
                    @if($arti)<span class="rw-arti-hasil">{{ $arti }}</span>@endif
                  @endif
                </div>
              </td>
              <td class="rw-sel-catatan">
                @if($catatan === '')
                  <span class="rw-redup">Tidak ada catatan tambahan.</span>
                @elseif(mb_strlen($catatan) <= 180)
                  <p class="rw-catatan-teks">{{ $catatan }}</p>
                @else
                  <details class="rw-catatan-panjang">
                    <summary><span class="rw-cuplikan">{{ preg_replace('/\s+\S*$/u', '', mb_substr($catatan, 0, 180)) }}…</span>
                      <span class="rw-baca"><span class="rw-buka">Baca selengkapnya</span><span class="rw-tutup">Tutup catatan</span><x-ikon n="ChevronDown" :s="12" /></span>
                    </summary>
                    <p class="rw-catatan-teks">{{ $catatan }}</p>
                  </details>
                @endif
                @if(! empty($b['tolak']))
                  <div class="rw-pengembalian">
                    {{ ! empty($b['kembali']) ? 'Berkas dikembalikan ke satuan kerja' : 'Berkas ke Setba untuk dikirim ulang' }}{{ ! empty($b['batasWaktu']) ? ' · perbaiki paling lambat '.Tampil::tgl($b['batasWaktu']) : '' }}
                  </div>
                @endif
                @if(! empty($b['dokumenDiminta']))
                  <details class="rw-dokumen"><summary>{{ count($b['dokumenDiminta']) }} dokumen diminta untuk perbaikan</summary>
                    <ul>@foreach($b['dokumenDiminta'] as $nama)<li>{{ $nama }}</li>@endforeach</ul>
                  </details>
                @endif
                @if(! empty($b['kembali']['keteranganSetba']))
                  <div style="font-size:12.5px;margin-top:3px">Keterangan Setba: {{ $b['kembali']['keteranganSetba'] }}</div>
                @endif
                @if(! empty($b['catatanRek']))<div class="lbl" style="margin-top:3px">{{ $b['catatanRek'] }}</div>@endif
                {{-- Pencatatnya; jamnya di kolom Tanggal. --}}
                @php
                  $pelaku = $b['pelaku'] ?? null;
                  $namaPelaku = $pelaku['nama'] ?? null;
                  $peranPelaku = ($pelaku['peran'] ?? '') ?: ($b['oleh'] ?? '');
                  $berlakuRek = ($b['lingkup'] ?? '') === 'rek';
                @endphp
                @if($namaPelaku || $peranPelaku || $berlakuRek)
                  <div class="rw-pencatat">
                    @if($namaPelaku || $peranPelaku)
                      <span class="rw-oleh">Oleh @if($namaPelaku)<b class="rw-pelaku">{{ $namaPelaku }}</b>{{ $peranPelaku ? ' ('.$peranPelaku.')' : '' }}@else{{ $peranPelaku }}@endif</span>
                    @endif
                    @if($berlakuRek)<span>{{ ($namaPelaku || $peranPelaku) ? ' · ' : '' }}berlaku untuk seluruh rekomendasi</span>@endif
                  </div>
                @endif
              </td>
              @if($kartu['adaSurat'])
                <td class="rw-surat">
                  @if(! empty($b['nomor']))
                    <div class="rw-nomor-surat"><x-ikon n="FileText" :s="13" /><span>{{ $b['nomor'] }}</span></div>
                  @endif
                  @if(! empty($b['nomorLhv']) || ! empty($b['tglLhv']))
                    <div class="lbl" style="margin-bottom:4px">LHV {{ $b['nomorLhv'] ?? '' }}{{ ! empty($b['tglLhv']) ? ' · '.Tampil::tgl($b['tglLhv']) : '' }}</div>
                  @endif
                  @if(! empty($b['berkas']))
                    <x-berkas :b="$b['berkas']" :jenis="$b['judulBerkas'] ?? null" :oleh="$b['oleh'] ?? null" :tanggal="$b['tanggal']" />
                  @elseif(empty($b['nomor']) && empty($b['nomorLhv']) && empty($b['tglLhv']))
                    <span class="rw-redup">Tidak tercatat</span>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
      </tbody>
    </table>
      </div>
      @endif
    </div>
  @endforeach
</x-panel-kerja>
