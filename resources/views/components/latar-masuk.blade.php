{{-- Latar bergerak halaman masuk, Simulasi SSO, dan Akses dibatasi (28 Sep).
     Hiasan belaka: tiga gumpal warna yang melayang pelan, jaring titik yang
     bergeser, dan butiran yang naik. Tanpa skrip dan tanpa gambar — rupanya
     di simtlhp-tambahan.css ("halaman masuk"). Pembaca layar melewatinya. --}}
<div class="msk-latar" aria-hidden="true">
  <span class="msk-gumpal g1"></span>
  <span class="msk-gumpal g2"></span>
  <span class="msk-gumpal g3"></span>
  <span class="msk-jaring"><i></i></span>
  <span class="msk-butiran">@for($i = 0; $i < 10; $i++)<i></i>@endfor</span>
</div>
