@props(['kat' => null, 'polos' => false])

@php
  /* `kat` model Referensi kategori internal — padanan `TagKategori` prototipe.
     Tanpa warna sejak 27 Sep. Bang Kamal: "Ini biru, biru sama merah apa
     artinya?" — merah dan hijau di aplikasi ini berarti keadaan, jadi kategori
     yang berwarna terbaca sebagai keadaan. Kolom `warna` tetap di basis data,
     tidak dipakai lagi. */
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
