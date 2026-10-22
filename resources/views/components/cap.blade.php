@props(['s' => null, 'jenis' => null, 'rek' => null, 'satker' => null])

@php
  /* Lencana status SIPTL. LHA tidak punya status BPK — yang tampil putusan Inspektorat. LHP yang
     belum ada di SIPTL tidak berlencana. `rek`: status dihitung dari barisnya; `satker`: satuan
     kerja yang melihat membaca keadaannya sendiri. */
  $jenis = $jenis instanceof \App\Enums\SumberLaporan ? $jenis
    : (\App\Enums\SumberLaporan::tryFrom((string) $jenis) ?? $rek?->jenis());
  $s = $rek ? $rek->statusBpkUntuk($satker)
    : ($s instanceof \App\Enums\StatusTindakLanjut ? $s : \App\Enums\StatusTindakLanjut::tryFrom((string) $s));
@endphp

@if($jenis && ! $jenis->melewatiSiptl())
  @if($rek)
    @php $h = $rek->keadaanUntuk($satker); @endphp
    <span class="cap {{ $h->cap() }}">{{ $h->nama($jenis) }}</span>
  @endif
@elseif($s)
  <span class="cap {{ $s->cap() }}">{{ $s->value }} · {{ $s->pendek() }}</span>
@endif
