@php
  /* "Sebelum → sesudah" dari rincian log (aturannya di App\Support\BedaLog). */
  $butir = \App\Support\BedaLog::butir($rincian ?? null);
@endphp
@if($butir)
  <span class="dm-beda" aria-label="Nilai sebelum dan sesudah perubahan">
    @foreach($butir as $b)
      <span>{{ $b['nama'] }}: @if($b['ada_sebelum'])<s>{{ $b['sebelum'] }}</s> <x-ikon n="ArrowRight" :s="11" aria-label="menjadi" /> @endif<b>{{ $b['sesudah'] }}</b></span>
    @endforeach
  </span>
@endif
