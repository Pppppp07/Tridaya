@props(['nama' => null])

{{-- Kategori temuan sebagai nilai di daftar keterangan (tanpa warna). --}}
@if($nama)
  <span class="nilaikat"><span>{{ $nama }}</span></span>
@else
  <span class="lbl" style="margin:0">belum dipilih</span>
@endif
