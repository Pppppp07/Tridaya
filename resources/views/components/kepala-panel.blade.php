@props(['no' => null, 'judul' => '', 'kosong' => '', 'ringkas' => '', 'ok' => false])

{{-- Kepala panel yang sedang ditampilkan — padanan `KepalaPanel`: judul utuh,
     ringkasan satu baris, keadaannya, dan tombol Hapus (slot `aksi`). Bukan
     tombol: panelnya dipilih lewat tabnya. Judulnya ikut berubah selagi judul
     temuan atau uraiannya diketik (skrip, `data-kep-judul`). --}}
<div class="fb-kep">
  <div class="fb-alih fb-diam">
    @if($no !== null)<span class="fb-no">{{ $no }}</span>@endif
    <span class="fb-judul">
      <b @if($judul === '') class="fb-kosong" @else title="{{ $judul }}" @endif data-kep-judul data-kosong="{{ $kosong }}">{{ $judul !== '' ? $judul : $kosong }}</b>
      @if($ringkas)<span class="fb-ringkas">{{ $ringkas }}</span>@endif
    </span>
    <span class="fb-status{{ $ok ? ' ok' : '' }}">
      @if($ok)<x-ikon n="CheckCircle2" :s="14" />@else<x-ikon n="Circle" :s="14" />@endif
      <span>{{ $ok ? 'Lengkap' : 'Belum lengkap' }}</span>
    </span>
  </div>
  {{ $aksi ?? '' }}
</div>
