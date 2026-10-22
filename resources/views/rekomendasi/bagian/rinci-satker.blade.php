@php
  use App\Support\Tampil;

  /* Rincian satu satuan kerja pada satu bentuk tindak lanjut: yang diminta, dilaporkan, disetor,
     dan berkasnya. `$kembali` (alasan, dari, batas) ada bila berkasnya sedang dikembalikan. */
  $kembali ??= null;
  $minta = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item;
  $jawab = $r->tanggapan->where('sasaran_id', $x->id);
  /* Kiriman terakhir satuan kerja ini (pengirim dan jam di baris sendiri). Data contoh lama
     tanpa jam; urutan dari id. */
  $kirimTerakhir = $jawab->sortByDesc(fn ($t) => sprintf('%012d-%012d', $t->created_at?->getTimestamp() ?? 0, $t->id))->first();
  $setor = $r->pemulihan->where('sasaran_id', $x->id);
  /* Berkas kiriman satuan kerja ini untuk bentuk ini; bukti setor tampil di barisnya sendiri. */
  $berkas = $r->lampiran->filter(fn ($d) => $d->label_oleh === $x->satker->namaPendek()
    && (! $d->tindakan_id || $d->tindakan_id === $x->tindakan_id));
  $dana = (int) $x->nilai > 0 ? $r->progresDana($x->satker_id, $x->tindakan_id) : null;
  $tolak = $r->totalTolakan($x->satker_id, $x->tindakan_id);

  /* Dokumen yang diminta dan buktinya jadi satu daftar. */
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

  /* Catatan penilai tidak diulang bila sama persis dengan alasan pengembalian. */
  $sama = fn ($a, $b) => mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
  $catatanPenilai = $x->catatan && ! ($kembali && $sama($x->catatan, $kembali['alasan'])) ? $x->catatan : '';
  $lewat = $kembali && $kembali['batas'] && $kembali['batas']->copy()->startOfDay()->lt(now()->startOfDay());
  /* Kiriman terakhir: tanggal tanggapan atau berkas yang paling baru. */
  $terakhir = $jawab->pluck('tanggal')->merge($berkas->pluck('diunggah_pada'))->filter()
    ->map(fn ($t) => \Illuminate\Support\Carbon::parse($t)->toDateString())->sort()->last();
  $adaKiriman = $jawab->isNotEmpty() || $berkas->isNotEmpty() || $setor->isNotEmpty();
  /* Draf yang belum dikirim hanya terbaca Setba selama berkasnya di meja satuan kerja; satuan kerja
     sendiri membacanya di formulir. Link berkas di draf tidak ditampilkan. */
  $draf = $peran === \App\Enums\PeranPengguna::SETBA && $x->pos() === \App\Enums\PosisiBerkas::SATKER ? $x->draf : null;
  $siapDraf = $draf ? collect($draf->penuhi ?? [])->map(fn ($v) => (int) $v)
    ->intersect($minta->where('terpenuhi', false)->pluck('id'))->values() : collect();
  $kemajuanDraf = $draf ? \App\Aksi\Kemajuan::kalimat(\App\Aksi\Kemajuan::hitung($r, $x, $siapDraf->all(), $draf->setoran ?? []), 'siap') : '';
  $kosong = ! $kembali && $minta->isEmpty() && ! $dana && ! ($tolak > 0) && $setor->isEmpty()
    && $jawab->isEmpty() && $berkas->isEmpty() && ! $catatanPenilai && ! $draf;
  /* Id label tiap baris unik per satuan kerja: satu halaman memuat banyak. */
  $k = 'rinci-'.$x->id.'-';
@endphp

{{-- Satu kartu seperti panel kerja: kepala berpita abu, lalu baris berlabel. Urutan: alasan
     pengembalian, dokumen, tanggapan, pemulihan. --}}
<x-panel-kerja class="tl-bukti kerja-baca" ikon="Paperclip" judul="Bukti dan tanggapan" :info="[
  'Uraian tindakan, dokumen bukti, dan pemulihan nilai yang telah dikirim Satker untuk penugasan ini.',
  'Dokumen yang diminta menunjukkan bukti yang harus dilengkapi; tanggapan menjelaskan tindakan dan hasilnya.',
]">
  <x-slot:kalimat>@if($adaKiriman)Kiriman <b>{{ $x->satker->namaPendek() }}</b>{{ $terakhir ? ', terbaru '.Tampil::tgl($terakhir) : '' }}.{{ $draf ? ' Draf berikutnya sedang disiapkan.' : '' }}@else{{ 'Belum ada kiriman dari ' }}<b>{{ $x->satker->namaPendek() }}</b>{{ $draf ? ' — draf sedang disiapkan.' : '.' }}@endif</x-slot:kalimat>

  {{-- Alasan pengembalian: baris pertama, berwarna kuning. --}}
  @if($kembali)
    <x-baris-isi :label="'Ditolak '.($kembali['dari'] ?: 'pemeriksa')" :kunci="$k.'kembali'">
      <div class="kerja-awas">
        <x-ikon n="AlertTriangle" :s="15" />
        <span>{{ $kembali['alasan'] }}@if($kembali['batas'])<span @class(['kerja-meta', 'lewat' => $lewat])>perbaiki paling lambat {{ Tampil::tgl($kembali['batas']) }}{{ $lewat ? ' — sudah lewat' : '' }}</span>@endif</span>
      </div>
    </x-baris-isi>
  @endif

  {{-- Draf: siapa dan kapan menyimpan, kemajuannya, dan uraian sementaranya. --}}
  @if($draf)
    <x-baris-isi label="Draf satuan kerja" gabung :kunci="$k.'draf'" :info="[
      'Isian yang sedang disiapkan satuan kerja dan belum dikirim. Isinya masih bisa berubah.',
      'Berkas baru berpindah ke Setba saat satuan kerja menekan Kirim ke Setba. Link berkas di draf tidak ditampilkan.',
    ]">
      <div class="kerja-daftar" data-rinci="draf">
        <div class="kerja-teks"><span class="pil warn">Belum dikirim</span> Disimpan <b class="rw-pelaku">{{ $draf->penyimpan?->name ?? $x->satker->namaPendek() }}</b><span class="kerja-meta">{{ $draf->updated_at ? Tampil::tglJam($draf->updated_at) : Tampil::tgl($draf->terakhir) }}{{ $draf->kali > 1 ? ' · '.$draf->kali.' kali disimpan' : '' }}</span></div>
        @if($kemajuanDraf)
          <div class="kerja-teks">{{ \Illuminate\Support\Str::ucfirst($kemajuanDraf) }}</div>
        @endif
        @if($draf->uraian)
          <div class="kerja-teks kerja-redup">{{ $draf->uraian }}</div>
        @endif
      </div>
    </x-baris-isi>
  @endif

  {{-- Yang terlampir tampil dengan judul berkasnya; yang belum tetap berbaris, dan yang sudah
       disiapkan di draf disebut begitu. --}}
  @if($minta->isNotEmpty())
    <x-baris-isi label="Dokumen yang diminta" gabung :kunci="$k.'dok'" data-rinci="dokumen">
      <ul class="ceklis kerja-dok">
        @foreach($minta as $n => $item)
          @php $d = $buktiButir[$n]; @endphp
          <li @class(['belum' => ! $d])>
            <span @class(['tanda', 'ok' => (bool) $d])><x-ikon :n="$d ? 'CheckCircle2' : 'Circle'" :s="16" /></span>
            <span class="isi"><span class="nama">{{ $d ? $d->labelTampil() : $item->nama }}</span></span>
            @if($d)<x-lihat-berkas :b="$d" />@else<span class="kerja-meta">{{ $siapDraf->contains($item->id) ? 'disiapkan di draf' : 'belum dilampirkan' }}</span>@endif
          </li>
        @endforeach
      </ul>
      <span class="fb-catatan">{{ $minta->where('terpenuhi', true)->count() }} dari {{ $minta->count() }} sudah terlampir{{ $siapDraf->isNotEmpty() ? ' · '.$siapDraf->count().' disiapkan di draf' : '' }}</span>
    </x-baris-isi>
  @endif

  {{-- Kiriman di luar daftar yang diminta; judulnya hanya bila memang ada. --}}
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

  {{-- Pengirim dan jam kiriman terakhir, di atas tanggapannya. Seluruh kiriman ada di tab
       Progres. --}}
  @if($kirimTerakhir)
    <x-baris-isi label="Dikirim oleh" :kunci="$k.'pengirim'">
      <div class="kerja-teks">
        <b class="rw-pelaku">{{ $kirimTerakhir->pencatat?->name ?? $x->satker->namaPendek() }}</b><span class="kerja-meta">{{ $kirimTerakhir->created_at ? Tampil::tglJam($kirimTerakhir->created_at) : Tampil::tgl($kirimTerakhir->tanggal) }}</span>
      </div>
    </x-baris-isi>
  @endif

  @if($jawab->isNotEmpty())
    <x-baris-isi label="Tanggapan satuan kerja" gabung :kunci="$k.'tanggapan'">
      <div class="kerja-daftar">
        @foreach($jawab as $t)
          {{-- Tanggal hanya bila kirimannya lebih dari satu. --}}
          <div class="kerja-teks">{{ $t->uraian }}@if($jawab->count() > 1)<span class="kerja-meta">{{ Tampil::tgl($t->tanggal) }}</span>@endif</div>
        @endforeach
      </div>
    </x-baris-isi>
  @endif

  {{-- Keduanya ditulis penuh supaya tidak terbaca dua besaran berbeda. Batang hijau bila lunas. --}}
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
      'Nilai pemulihan yang ditandai tidak diterima BPK pada tindak lanjut ini.',
      'Bukti lama tetap tersimpan. Nilai ini perlu ditindaklanjuti ulang sesuai catatan BPK.',
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

  {{-- Baris yang belum dikerjakan tetap punya kartunya. --}}
  @if($kosong)
    <p class="kerja-teks kerja-kosong">Tanggapan dan bukti tampil di sini setelah dikirim satuan kerja.</p>
  @endif
</x-panel-kerja>
