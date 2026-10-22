@props(['judul', 'ikon' => null, 'nada' => '', 'n' => null, 'kartu' => true, 'idIsi' => null])

@php
  /* Kartu berlipat pada rincian rekomendasi; tertutup sejak awal, kepalanya meringkas isi (slot
     `ringkas`). */
  $idIsi = $idIsi ?: (($attributes->get('id') ?: 'lipat-'.\Illuminate\Support\Str::slug($judul)).'-isi');
@endphp

<section {{ $attributes->class([$kartu ? 'card' : null, 'lipat', 'buka']) }} data-lipat>
  <div class="lipat-kep" role="button" tabindex="0" aria-expanded="true" aria-controls="{{ $idIsi }}" data-lipat-kep>
    @if($ikon)<span class="ic-kotak {{ $nada }}"><x-ikon :n="$ikon" :s="17" /></span>@endif
    <span class="lipat-judul">
      <b>{{ $judul }}@isset($info){{ $info }}@endisset</b>
      @isset($ringkas)<span class="lipat-ringkas" data-lipat-ringkas hidden>{{ $ringkas }}</span>@endisset
    </span>
    @if($n !== null)<span class="n">{{ $n }}</span>@endif
    <span class="lipat-ajak"><span data-lipat-ajak>Sembunyikan</span><x-ikon n="ChevronDown" :s="14" /></span>
  </div>
  <div id="{{ $idIsi }}" class="lipat-isi" data-lipat-isi>{{ $slot }}</div>
</section>
