@props(['ikon', 'judul', 'info' => null, 'wajib' => false, 'ket' => '', 'lanjut' => '', 'aksiTersembunyi' => false])

{{-- Panel kerja di tab Kerjakan untuk semua peran: bentuk formulir yang sama. --}}
<section {{ $attributes->class(['kerja', 'fb']) }} aria-label="{{ $judul }}">
  <div class="kerja-kep">
    <span class="fb-no"><x-ikon :n="$ikon" :s="15" /></span>
    <span class="kerja-judul">
      <b>{{ $judul }}@if($info)<x-info :teks="$info" />@endif</b>
      @isset($kalimat)<span>{{ $kalimat }}</span>@endisset
    </span>
  </div>
  <div class="kerja-isi">
    @if($wajib)<p class="fb-legenda"><span class="fb-bintang" aria-hidden="true">*</span> wajib diisi</p>@endif
    {{ $slot }}
  </div>
  @isset($aksi)
    <div class="kerja-aksi" data-aksi-kerja @if($aksiTersembunyi) hidden @endif>
      {{ $aksi }}
      <span class="kerja-ket{{ $ket ? '' : ' lanjut' }}" data-ket-kerja @if(! $ket && ! $lanjut) hidden @endif><x-ikon :n="$ket ? 'AlertTriangle' : 'ArrowRight'" :s="15" /><span>{{ $ket ?: $lanjut }}</span></span>
    </div>
  @endisset
</section>
