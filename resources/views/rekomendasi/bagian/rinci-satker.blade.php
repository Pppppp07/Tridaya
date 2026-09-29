@php
  use App\Support\Tampil;

  /* Rincian satu satuan kerja pada satu bentuk tindak lanjut — padanan
     RinciSatker. Yang diminta, yang dilaporkan, setorannya, dan berkasnya:
     semuanya dikerucutkan ke baris ini. `$kembali` (alasan, dari, batas) ada
     kalau berkasnya sedang dikembalikan untuk pemberkasan ulang. */
  $kembali ??= null;
  $minta = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item;
  $jawab = $r->tanggapan->where('sasaran_id', $x->id);
  $setor = $r->pemulihan->where('sasaran_id', $x->id);
  /* Berkas kiriman satuan kerja ini untuk bentuk ini; bukti setor tidak ikut
     — ia tampil di barisnya sendiri. */
  $berkas = $r->lampiran->filter(fn ($d) => $d->label_oleh === $x->satker->namaPendek()
    && (! $d->tindakan_id || $d->tindakan_id === $x->tindakan_id));
  $dana = (int) $x->nilai > 0 ? $r->progresDana($x->satker_id, $x->tindakan_id) : null;
  $tolak = $r->totalTolakan($x->satker_id, $x->tindakan_id);

  /* Dokumen yang diminta dan buktinya jadi SATU daftar. Dulu dua bagian
     berturut-turut yang menyebut hal yang sama: daftar berkas terkirim, lalu
     ceklis berisi nama dokumen yang sama persis. Kata Hizkia (20 Sep):
     "menampilkan informasi berulang, jadi gabungkan tampilannya jadi satu
     bagian informasi saja".

     Berkasnya dicocokkan ke butir yang dipenuhinya lewat tabel sambungnya,
     lalu lewat jenis berkas — `label_jenis` memang disalin dari nama dokumen
     yang diminta. Berkas yang tidak terpakai satu butir pun berarti kiriman di
     luar yang diminta. */
  $minta = $minta->values();
  $dipakai = collect();
  $buktiButir = $minta->map(function ($i) use ($berkas, $dipakai) {
      if (! $i->terpenuhi) {
          return null;
      }
      $d = $berkas->first(fn ($b) => ! $dipakai->contains($b->id) && $i->lampiran->contains('id', $b->id))
        ?: $berkas->first(fn ($b) => ! $dipakai->contains($b->id)
            && mb_strtolower(trim($b->jenis())) === mb_strtolower(trim($i->nama)));
      if ($d) {
          $dipakai->push($d->id);
      }

      return $d;
  })->all();
  $tambahan = $berkas->reject(fn ($b) => $dipakai->contains($b->id));

  /* Catatan penilai tidak diulang kalau bunyinya sama persis dengan alasan
     pengembalian yang sudah tertulis di baris teratas. */
  $sama = fn ($a, $b) => mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
  $catatanPenilai = $x->catatan && ! ($kembali && $sama($x->catatan, $kembali['alasan'])) ? $x->catatan : '';
  $lewat = $kembali && $kembali['batas'] && $kembali['batas']->copy()->startOfDay()->lt(now()->startOfDay());
  /* Kiriman terakhir: tanggal tanggapan atau berkas yang paling baru. */
  $terakhir = $jawab->pluck('tanggal')->merge($berkas->pluck('diunggah_pada'))->filter()
    ->map(fn ($t) => \Illuminate\Support\Carbon::parse($t)->toDateString())->sort()->last();
  $adaKiriman = $jawab->isNotEmpty() || $berkas->isNotEmpty() || $setor->isNotEmpty();
  $kosong = ! $kembali && $minta->isEmpty() && ! $dana && ! ($tolak > 0) && $setor->isEmpty()
    && $jawab->isEmpty() && $berkas->isEmpty() && ! $catatanPenilai;
  /* Id label tiap baris unik per satuan kerja: satu halaman memuat banyak. */
  $k = 'rinci-'.$x->id.'-';
@endphp

{{-- Satu kartu seperti panel kerja di tab sebelahnya (25 Sep): kepala berpita
     abu, lalu baris berlabel di kiri yang dipisah garis tipis — pindah tab tidak
     mengubah cara membacanya. Urutannya: alasan pengembalian kalau berkasnya
     sedang dikembalikan, dokumen (bahan pemeriksaan pertama), tanggapan, lalu
     rincian nilai. --}}
<x-panel-kerja class="tl-bukti kerja-baca" ikon="Paperclip" judul="Bukti dan tanggapan" :info="[
  'Berkas yang sudah dikirim tidak bisa dihapus atau ditarik siapa pun, termasuk pengunggahnya.',
  'Berkas yang keliru diganti saat berkasnya dikirim ulang untuk pemberkasan ulang. Berkas lama tetap tersimpan di Arsip rekomendasi.',
]">
  <x-slot:kalimat>@if($adaKiriman)Kiriman <b>{{ $x->satker->namaPendek() }}</b>{{ $terakhir ? ', terbaru '.Tampil::tgl($terakhir) : '' }}.@else{{ 'Belum ada kiriman dari ' }}<b>{{ $x->satker->namaPendek() }}</b>.@endif</x-slot:kalimat>

  {{-- Dulu kotak kuning "Catatan pengembalian" di atas deret tab. Sekarang
       baris pertama kartunya, masih berwarna kuning: inilah alasan berkasnya
       sedang diperbaiki. --}}
  @if($kembali)
    <x-baris-isi :label="'Ditolak '.($kembali['dari'] ?: 'pemeriksa')" :kunci="$k.'kembali'">
      <div class="kerja-awas">
        <x-ikon n="AlertTriangle" :s="15" />
        <span>{{ $kembali['alasan'] }}@if($kembali['batas'])<span @class(['kerja-meta', 'lewat' => $lewat])>perbaiki paling lambat {{ Tampil::tgl($kembali['batas']) }}{{ $lewat ? ' — sudah lewat' : '' }}</span>@endif</span>
      </div>
    </x-baris-isi>
  @endif

  {{-- Yang sudah terlampir tampil dengan judul berkasnya — nama dokumennya
       memang judul berkas itu. Yang belum tetap berbaris, supaya daftarnya
       menyebut seluruh yang diminta. --}}
  @if($minta->isNotEmpty())
    <x-baris-isi label="Dokumen yang diminta" gabung :kunci="$k.'dok'" data-rinci="dokumen">
      <ul class="ceklis kerja-dok">
        @foreach($minta as $n => $item)
          @php $d = $buktiButir[$n]; @endphp
          <li @class(['belum' => ! $d])>
            <span @class(['tanda', 'ok' => (bool) $d])><x-ikon :n="$d ? 'CheckCircle2' : 'Circle'" :s="16" /></span>
            <span class="isi"><span class="nama">{{ $d ? $d->labelTampil() : $item->nama }}</span></span>
            @if($d)<x-lihat-berkas :b="$d" />@else<span class="kerja-meta">belum dilampirkan</span>@endif
          </li>
        @endforeach
      </ul>
      <span class="fb-catatan">{{ $minta->where('terpenuhi', true)->count() }} dari {{ $minta->count() }} sudah terlampir</span>
    </x-baris-isi>
  @endif

  {{-- Kiriman di luar daftar yang diminta. Judulnya cuma muncul kalau memang
       ada — kata Hizkia: "jika tidak ada dokumen tambahan yang dimasukkan,
       informasi terkait judul atau keterangan dokumen tambahan jangan
       ditampilkan". Kalau tidak ada dokumen yang diminta sama sekali, berkas
       ini bukan "tambahan", melainkan satu-satunya bukti yang dikirim. --}}
  @if($tambahan->isNotEmpty())
    <x-baris-isi :label="$minta->isNotEmpty() ? 'Dokumen tambahan' : 'Bukti yang sudah dikirim'" gabung :kunci="$k.'tambahan'">
      <ul class="ceklis kerja-dok">
        @foreach($tambahan as $d)
          <li>
            <span class="tanda"><x-ikon :n="$d->link ? 'ExternalLink' : 'Paperclip'" :s="15" /></span>
            <span class="isi"><span class="nama">{{ $d->labelTampil() }}</span></span>
            <x-lihat-berkas :b="$d" />
          </li>
        @endforeach
      </ul>
    </x-baris-isi>
  @endif
  @if(! $kosong && $berkas->isEmpty() && $minta->isEmpty())
    <x-baris-isi label="Bukti yang sudah dikirim" :kunci="$k.'bukti'">
      <p class="kerja-teks kerja-redup">Belum ada.</p>
    </x-baris-isi>
  @endif

  @if($jawab->isNotEmpty())
    <x-baris-isi label="Tanggapan satuan kerja" gabung :kunci="$k.'tanggapan'">
      <div class="kerja-daftar">
        @foreach($jawab as $t)
          <div class="kerja-teks">{{ $t->uraian }}<span class="kerja-meta">{{ Tampil::tgl($t->tanggal) }}</span></div>
        @endforeach
      </div>
    </x-baris-isi>
  @endif

  {{-- Keduanya ditulis penuh: "Rp 18 jt" di samping "Rp 17.800.000" terbaca
       seperti dua besaran yang berbeda. Batangnya hijau kalau sudah lunas. --}}
  @if($dana)
    <x-baris-isi label="Pemulihan nilai" gabung :kunci="$k.'pulih'">
      <div class="kerja-setor-kep">
        <span @class(['bar', 'dana' => $dana['sisa'] !== 0])><i style="width:{{ min(100, $dana['target'] ? $dana['masuk'] / $dana['target'] * 100 : 0) }}%"></i></span>
        <b class="mono">{{ $dana['masuk'] ? Tampil::rupiah($dana['masuk']) : 'Rp 0' }}</b>
        <span>dari {{ Tampil::rupiah($dana['target']) }}</span>
        <span class="fb-catatan">{{ $dana['sisa'] === 0 ? 'lunas' : 'sisa '.Tampil::rupiah($dana['sisa']) }}</span>
      </div>
    </x-baris-isi>
  @endif
  {{-- Disebut tersendiri, bukan diam-diam mengurangi angka di atasnya. --}}
  @if($tolak > 0)
    <x-baris-isi label="Tidak diterima BPK" gabung :kunci="$k.'tolak'" :info="[
      'Nilai yang sudah disetor tapi buktinya tidak diterima BPK.',
      'Bukti setornya tetap tersimpan — yang dicabut hanya pengakuannya, jadi sisa tagihannya terbuka lagi sebesar ini.',
    ]">
      <div class="kerja-teks"><span class="mono" style="color:var(--bad)">{{ Tampil::rupiah($tolak) }}</span><span class="kerja-meta">harus ditindaklanjuti ulang</span></div>
    </x-baris-isi>
  @endif
  @if($setor->isNotEmpty())
    <x-baris-isi label="Bukti setor" gabung :kunci="$k.'setor'">
      <ul class="kerja-setoran">
        @foreach($setor as $s)
          <li><b class="mono">{{ Tampil::rupiah($s->nilai) }}</b><span class="mono ntpn">NTPN {{ $s->ntpn }}</span><span class="kerja-meta">{{ Tampil::tgl($s->tanggal) }}</span></li>
        @endforeach
      </ul>
    </x-baris-isi>
  @endif
  {{-- Kekurangannya disebut apa adanya, dan diberi nama. --}}
  @if($catatanPenilai)
    <x-baris-isi label="Catatan penilai" :kunci="$k.'catatan'">
      <div class="kerja-teks">{{ $catatanPenilai }}</div>
    </x-baris-isi>
  @endif

  {{-- Baris yang belum dikerjakan sama sekali tetap punya kartunya — dulu ia
       kosong begitu saja, dan yang membukanya tidak tahu apakah memang belum
       ada isinya atau tampilannya yang rusak. --}}
  @if($kosong)
    <p class="kerja-teks kerja-kosong">Tanggapan dan bukti tampil di sini setelah dikirim satuan kerja.</p>
  @endif
</x-panel-kerja>
