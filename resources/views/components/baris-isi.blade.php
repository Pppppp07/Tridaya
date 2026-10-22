@props(['label', 'untuk' => null, 'kunci' => null, 'info' => null, 'wajib' => false, 'kosong' => false, 'gabung' => false, 'bila' => null, 'infoBila' => null])

{{-- Satu baris isian: label di kiri, isian di kanan; di lajur sempit label naik ke atas. --}}
@php $idLabel = 'fbl-'.($untuk ?: $kunci ?: md5($label)); @endphp
<div {{ $attributes->class(['fb-brs']) }} @if($gabung) role="group" aria-labelledby="{{ $idLabel }}" @endif
  @if($wajib && $gabung) data-wajib="{{ $kosong ? 'kosong' : 'isi' }}" @endif>
  {{-- Bintang dan ikon Info menempel pada label; satu baris dan bintangnya lewat echo, karena
       `@endif@if` tidak dikenali Blade. --}}
  @php
    $bintang = $wajib ? '<span class="fb-bintang" aria-hidden="true">*</span>'
      : ($bila ? '<span class="fb-bintang" aria-hidden="true" data-bintang-bila="'.e($bila).'" hidden>*</span>' : '');
  @endphp
  <span class="fb-lbl">@if($untuk)<label for="{{ $untuk }}" id="{{ $idLabel }}">{{ $label }}</label>@else<span id="{{ $idLabel }}">{{ $label }}@if($wajib)<span class="fb-sr"> (wajib)</span>@endif</span>@endif{!! $bintang !!}@if($info)<x-info :teks="$info" :data-info-bila="$infoBila" :hidden="(bool) $infoBila" />@endif</span>
  <div class="fb-isian">{{ $slot }}</div>
</div>
