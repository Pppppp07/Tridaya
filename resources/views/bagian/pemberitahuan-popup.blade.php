@php
  use App\Support\Pemberitahuan;

  /* Pemberitahuan yang muncul — padanan `Popup` (26 Sep): di kanan atas, di bawah
     lonceng asalnya; hanya pemberitahuan BARU yang belum pernah dimunculkan sebagai pop-up; menutup
     sendiri sesudah 8 detik (berhenti selama ditunjuk atau difokus; sisanya
     dibawa ke halaman berikutnya lewat simtlhp.js). Satu pemberitahuan ditampilkan
     isinya, lebih dari satu cukup jumlahnya. */
  $satu = $jumlah === 1;
  $r = $k->rekomendasi;
  $rupa = $satu ? Pemberitahuan::rupa($k) : ['nada' => 'biru', 'ikon' => 'Bell'];
  $bentuk = $k->tindakan?->bentuk?->nama;
@endphp
<div class="popup" role="status" aria-live="polite" data-popup>
  <span class="kb-ikon {{ $rupa['nada'] }}" aria-hidden="true"><x-ikon :n="$rupa['ikon']" :s="15" /></span>
  <div class="teks">
    @if($satu)
      <div class="kb-apa"><b>{{ $k->label_pelaku }}</b> &mdash; {{ $k->aksi }}</div>
      <div class="kb-meta">
        <x-sumber :j="$r?->jenis() ?? 'LHP'" /><span class="mono">{{ $r?->kode }}</span>
        <span>&middot; {{ Pemberitahuan::sebutSatker($k, auth()->user()) }}@if($bentuk) &middot; {{ $bentuk }}@endif</span>
      </div>
    @else
      <div class="kb-apa"><b>{{ $jumlah }} pemberitahuan baru</b></div>
      <div class="kb-meta"><span>Terbaru: {{ $k->label_pelaku }} &mdash; {{ $k->aksi }}</span></div>
    @endif
    <div class="kb-aksi">
      @if($satu)
        <a class="btn btn-s btn-p" href="{{ route('pemberitahuan.buka', $k) }}">Buka <x-ikon n="ChevronRight" :s="13" /></a>
      @else
        <a class="btn btn-s btn-p" href="{{ route('pemberitahuan') }}" data-lihat-pemberitahuan>Lihat <x-ikon n="ChevronRight" :s="13" /></a>
      @endif
    </div>
  </div>
  <button class="tutup" type="button" aria-label="Tutup pemberitahuan" data-tutup-popup><x-ikon n="X" :s="14" /></button>
  <span class="kb-sisa" aria-hidden="true"></span>
</div>
