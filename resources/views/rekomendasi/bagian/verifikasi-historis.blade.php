{{-- Uraian verifikasi Inspektorat dari lembar pemantauan; hanya ada pada baris data historis. --}}
@if($x->uraian_verifikasi)
<x-panel-kerja class="tl-verifikasi-historis kerja-baca" ikon="FileCheck2" judul="Uraian verifikasi Inspektorat" :info="[
  'Disalin dari kolom Uraian Verifikasi di lembar pemantauan Setba saat data historis diimpor.',
  'Surat CHV-nya, bila tercatat di lembar itu, tampil di tab Progres.',
]">
  <x-slot:kalimat>Catatan verifikasi <b>{{ $x->satker->namaPendek() }}</b> dari data historis.</x-slot:kalimat>
  <x-baris-isi label="Uraian" :kunci="'uv-'.$x->id">
    <p class="kerja-teks" style="white-space:pre-line">{{ $x->uraian_verifikasi }}</p>
  </x-baris-isi>
</x-panel-kerja>
@endif
