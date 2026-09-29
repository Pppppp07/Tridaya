@props(['nama' => null])

{{-- Kategori temuan (dari pemeriksa) sebagai nilai di daftar keterangan: teks
     biasa, pasangan x-tag-kategori `polos` untuk kategori internal. Tanpa titik
     biru sejak 29 Sep — warna di aplikasi ini berarti keadaan, dan titiknya
     membuat Kategori internal tidak sejajar dengan Sebab dan Akibat.
     Padanan NilaiKategoriTemuan di prototipe. --}}
@if($nama)
  <span class="nilaikat"><span>{{ $nama }}</span></span>
@else
  <span class="lbl" style="margin:0">belum dipilih</span>
@endif
