@props(['kat' => null, 'polos' => false])

@php
  /* Kategori internal tanpa warna: merah dan hijau di aplikasi ini berarti keadaan. */
  $mati = $kat && ! $kat->aktif;
  $ket = $mati ? 'Kategori ini sudah tidak aktif di data master' : null;
@endphp

@if(! $kat)
  @if($polos)
    <span class="lbl" style="margin:0">belum dipilih</span>
  @else
    <span class="tagkat dalam">belum dipilih</span>
  @endif
@elseif($polos)
  <span class="nilaikat" @if($ket) title="{{ $ket }}" @endif>
    <span>{{ $kat->nama }}{{ $mati ? ' · nonaktif' : '' }}</span>
  </span>
@else
  <span class="tagkat" @if($ket) title="{{ $ket }}" @endif>{{ $kat->nama }}{{ $mati ? ' · nonaktif' : '' }}</span>
@endif
