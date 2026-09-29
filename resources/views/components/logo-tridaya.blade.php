@props(['simbolSaja' => false, 'gelap' => false])
@php
  $aset = 'brand/tridaya/'.($gelap ? 'simbol-gelap.svg' : 'simbol.svg');
@endphp
{{-- Simbol dari sumber SVG desain Tridaya; tulisan tetap memakai Manrope 800.
     Nama dibaca sekali, termasuk saat sidebar hanya menampilkan simbol. --}}
<div {{ $attributes->class(['tridaya-logo', 'tridaya-logo-gelap' => $gelap]) }}
  role="img" aria-label="Tridaya">
  <img class="tridaya-simbol {{ $gelap ? '' : 'tridaya-simbol-terang' }}" src="{{ asset($aset) }}?v={{ filemtime(public_path($aset)) }}"
    width="48" height="48" alt="" aria-hidden="true" draggable="false">
  @unless($gelap)
    <img class="tridaya-simbol tridaya-simbol-gelap" src="{{ asset('brand/tridaya/simbol-gelap.svg') }}"
      width="48" height="48" alt="" aria-hidden="true" draggable="false">
  @endunless
  @unless($simbolSaja)
    <div class="tridaya-teks" aria-hidden="true">
      <span class="tridaya-nama">tridaya</span>
    </div>
  @endunless
</div>
