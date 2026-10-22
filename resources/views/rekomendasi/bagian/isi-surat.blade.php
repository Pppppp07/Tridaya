{{-- Surat pengantar Setba: nomor, tanggal, perihal wajib; link dan catatan boleh menyusul.
     Isinya baris-baris saja, ditaruh pemanggil di panel kerjanya. --}}
<x-baris-isi label="Nomor surat" :untuk="'nomor-'.$kunci" wajib :info="[
  'Nomor '.lcfirst(rtrim($ket, '.')).'. Salin sesuai surat yang digunakan untuk penerusan berkas.',
  'Jika satu surat mencakup beberapa penugasan, gunakan nomor yang sama pada penugasan yang tercantum dalam surat tersebut.',
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
<x-opsional judul="Link surat dan catatan" :jumlah="2">
  <x-baris-isi label="Link surat" :untuk="'link-'.$kunci">
    <input id="link-{{ $kunci }}" type="text" class="mono" name="link" placeholder="https://…" data-surat="link">
  </x-baris-isi>
  <x-baris-isi label="Catatan" :untuk="'catatan-'.$kunci">
    <input id="catatan-{{ $kunci }}" type="text" name="catatan" placeholder="Keterangan tambahan untuk penerima" data-surat="catatan">
  </x-baris-isi>
</x-opsional>
