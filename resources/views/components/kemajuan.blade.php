@props(['rek'])

@php
  $u = auth()->user();
  $jenis = $rek->jenis();
  $k = \App\Support\TindakLanjutRingkas::kemajuan($rek, $u->peranAktif(), $u->satkerAktifId());
@endphp

@if(! $k['dari'])
  <span class="lbl">—</span>
@else
  <div class="kemajuan">
    <b>{{ $k['selesai'] }} dari {{ $k['dari'] }} {{ $k['satuan'] }} selesai</b>
    <span class="batang" aria-hidden="true"><i style="width:{{ round($k['selesai'] / $k['dari'] * 100) }}%"></i></span>
    {{-- LHP yang belum ada di SIPTL tidak punya rangkuman SIPTL. --}}
    @if(! $jenis->melewatiSiptl())
      <span class="lbl">Rangkuman Itjen: {{ \App\Enums\HasilTelaah::from($k['rangkuman'])->nama($jenis) }}</span>
    @elseif($k['rangkuman'])
      <span class="lbl">Rangkuman SIPTL: {{ $k['rangkuman'] }}</span>
    @endif
  </div>
@endif
