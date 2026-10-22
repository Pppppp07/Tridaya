@props(['simbolSaja' => false, 'gelap' => false])
@php
  $aset = 'brand/infax/'.($gelap ? 'simbol-gelap.svg' : 'simbol.svg');
  $asetGelap = 'brand/infax/simbol-gelap.svg';
@endphp
{{-- Identitas dibaca sekali, termasuk saat sidebar hanya menampilkan simbol. --}}
<div {{ $attributes->class(['infax-logo', 'infax-logo-gelap' => $gelap]) }}
  role="img" aria-label="{{ config('app.display_name') }}">
  <img class="infax-simbol {{ $gelap ? '' : 'infax-simbol-terang' }}" src="{{ asset($aset) }}?v={{ filemtime(public_path($aset)) }}"
    width="48" height="48" alt="" aria-hidden="true" draggable="false">
  @unless($gelap)
    <img class="infax-simbol infax-simbol-gelap" src="{{ asset($asetGelap) }}?v={{ filemtime(public_path($asetGelap)) }}"
      width="48" height="48" alt="" aria-hidden="true" draggable="false">
  @endunless
  @unless($simbolSaja)
    <div class="infax-teks" aria-hidden="true">
      <span class="infax-nama">@if(config('app.display_name') === 'infaX')infa<span class="infax-x">X</span>@else{{ config('app.display_name') }}@endif</span>
    </div>
  @endunless
</div>
