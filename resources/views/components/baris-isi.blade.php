@props(['label', 'untuk' => null, 'kunci' => null, 'info' => null, 'wajib' => false, 'kosong' => false, 'gabung' => false, 'bila' => null, 'infoBila' => null])

{{-- Satu baris isian formulir Catat laporan baru — padanan `BarisIsi`
     prototipe: label di kiri, isian di kanan, seperti lembar isian surat
     (Hizkia, 24 Sep: "berderet kebawah seperti mengisi dokument"). Di lajur
     sempit labelnya naik ke atas isian. Sejak 25 Sep juga dipakai panel kerja
     di tab Kerjakan dan tab Bukti & tanggapan.

     Label dikaitkan lewat `untuk` (id isiannya), bukan dengan membungkus isian
     di dalam <label>: ikon Info di samping label juga tombol, dan label yang
     membungkus meneruskan kliknya ke tombol pertama di dalamnya. Baris `gabung`
     berisi beberapa isian atau keping pilihan; ia jadi kelompok berlabel
     (`kunci` = id labelnya — harus unik kalau labelnya berulang di satu
     halaman, misalnya satu per baris satuan kerja).

     `wajib` memberi bintang merah di belakang label (Hizkia, 24 Sep: "beri
     tanda atau semacam simbol untuk inputan yang wajib di isi … jangan menaruh
     tulisan 'tidak wajib'"). `kosong` menandai kelompok wajib yang belum diisi,
     supaya "Tunjukkan" bisa menemukannya.

     Panel putusan punya isian yang wajibnya bergantung pada putusan yang
     dipilih: `bila` memasang bintang tersembunyi yang dimunculkan skrip kalau
     putusannya sama (`data-bintang-bila`), `infoBila` begitu juga untuk ikon
     Info-nya. Atribut lain diteruskan ke barisnya. --}}
@php $idLabel = 'fbl-'.($untuk ?: $kunci ?: md5($label)); @endphp
<div {{ $attributes->class(['fb-brs']) }} @if($gabung) role="group" aria-labelledby="{{ $idLabel }}" @endif
  @if($wajib && $gabung) data-wajib="{{ $kosong ? 'kosong' : 'isi' }}" @endif>
  {{-- Bintang dan ikon Info menempel langsung pada labelnya, tanpa spasi di
       antaranya — jaraknya diatur margin, seperti di prototipe. Satu baris,
       dan bintangnya lewat echo: arahan Blade yang menempel pada arahan lain
       (`@endif@if`) tidak dikenali. --}}
  @php
    $bintang = $wajib ? '<span class="fb-bintang" aria-hidden="true">*</span>'
      : ($bila ? '<span class="fb-bintang" aria-hidden="true" data-bintang-bila="'.e($bila).'" hidden>*</span>' : '');
  @endphp
  <span class="fb-lbl">@if($untuk)<label for="{{ $untuk }}" id="{{ $idLabel }}">{{ $label }}</label>@else<span id="{{ $idLabel }}">{{ $label }}@if($wajib)<span class="fb-sr"> (wajib)</span>@endif</span>@endif{!! $bintang !!}@if($info)<x-info :teks="$info" :data-info-bila="$infoBila" :hidden="(bool) $infoBila" />@endif</span>
  <div class="fb-isian">{{ $slot }}</div>
</div>
