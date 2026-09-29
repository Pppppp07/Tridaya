@props(['b'])

@php
  /* Link "Lihat" satu berkas di tab Bukti & tanggapan — membuka preview
     yang sama dengan `x-berkas` (skrip `pasangPreview`); tanpa skrip ia
     membuka rute berkas yang terotorisasi. */
  $nama = $b->nama_asli ?: $b->link;
  $data = [
      'nama' => $nama, 'jenis' => $b->jenis(), 'oleh' => $b->label_oleh,
      'tanggal' => $b->diunggah_pada ? \App\Support\Tampil::tgl($b->diunggah_pada) : '', 'link' => $b->link,
  ];
@endphp
<a class="fb-link" href="{{ route('berkas.show', $b) }}" data-preview='@json($data)' aria-label="Lihat {{ $nama }}"><x-ikon n="Eye" :s="14" /> Lihat</a>
