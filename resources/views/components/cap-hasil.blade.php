@props(['jenis', 'hasil' => null, 'gaya' => null])

@php
  /* Dua keadaan; yang kosong ikut belum memadai. Singkatan mengikuti jenis laporan (LHP M/BM,
     LHA S/BS). */
  $h = $hasil instanceof \App\Enums\HasilTelaah ? $hasil : (\App\Enums\HasilTelaah::tryFrom((string) $hasil) ?? \App\Enums\HasilTelaah::BM);
  $jenis = $jenis instanceof \App\Enums\SumberLaporan ? $jenis : \App\Enums\SumberLaporan::from($jenis);
@endphp
<span class="cap {{ $h->cap() }}" @if($gaya) style="{{ $gaya }}" @endif title="{{ $h->nama($jenis) }}">{{ $h->kode($jenis) }}</span>
