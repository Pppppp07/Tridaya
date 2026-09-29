@props(['judul', 'ikon' => null, 'nada' => '', 'n' => null, 'kartu' => true, 'idIsi' => null])

@php
  /* Satu kartu berlipat pada rincian rekomendasi — padanan komponen `Lipat`.

     Kata Hizkia (17 Sep): tampilan informasinya "defaultnya dia bakal tertutup
     menyisahkan beberapa informasi atau bisa judulnya saja". Kepalanya menyebut
     isinya secara ringkas lewat slot `ringkas`; ikon Info lewat slot `info`.

     Digambar TERBUKA: tanpa skrip seluruh isinya tetap terbaca. Skrip yang
     melipatnya saat halaman siap (pasangLipat), dan yang membukanya lagi kalau
     pemberitahuan menunjuk bagian ini. */
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
