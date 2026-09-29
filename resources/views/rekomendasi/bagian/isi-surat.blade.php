{{-- Surat pengantar Setba — padanan `IsiSurat` prototipe: nomor, tanggal, dan
     perihal wajib; link pindaiannya dan catatan boleh menyusul, di balik
     pembuka isian tambahan. Isinya BARIS-baris saja — yang memanggilnya
     menaruhnya di panel kerja bersama baris lain, supaya seluruhnya berbagi
     satu kolom label. `kunci` membuat id isiannya unik per baris satuan kerja. --}}
<x-baris-isi label="Nomor surat" :untuk="'nomor-'.$kunci" wajib :info="[
  $ket,
  'Satu surat boleh memuat beberapa satuan kerja — nomor yang sama tinggal diisikan lagi pada barisnya masing-masing.',
]">
  <input id="nomor-{{ $kunci }}" type="text" class="mono fb-sedang" name="nomor" required aria-required="true"
    placeholder="{{ $contohNomor }}" data-surat="nomor">
</x-baris-isi>
<x-baris-isi label="Tanggal surat" :untuk="'tanggal-'.$kunci" wajib>
  <input id="tanggal-{{ $kunci }}" type="date" name="tanggal" max="{{ now()->toDateString() }}" required aria-required="true" data-surat="tanggal">
</x-baris-isi>
<x-baris-isi label="Perihal" :untuk="'perihal-'.$kunci" wajib>
  <input id="perihal-{{ $kunci }}" type="text" name="perihal" required aria-required="true"
    placeholder="{{ $contohPerihal }}" data-surat="perihal">
</x-baris-isi>
{{-- Link pindaian dan catatan boleh menyusul — suratnya sudah ditandatangani
     di luar dan tidak selalu langsung terpindai. --}}
<x-opsional judul="Link surat dan catatan" :jumlah="2">
  <x-baris-isi label="Link surat" :untuk="'link-'.$kunci">
    <input id="link-{{ $kunci }}" type="text" class="mono" name="link" placeholder="https://…" data-surat="link">
  </x-baris-isi>
  <x-baris-isi label="Catatan" :untuk="'catatan-'.$kunci">
    <input id="catatan-{{ $kunci }}" type="text" name="catatan" placeholder="Keterangan tambahan untuk penerima" data-surat="catatan">
  </x-baris-isi>
</x-opsional>
