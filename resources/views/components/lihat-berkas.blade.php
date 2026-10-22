@props(['b'])

@php
  /* Link "Lihat" satu berkas: preview lewat skrip; tanpa skrip membuka rute berkas yang
     terotorisasi. */
  $nama = $b->nama_asli ?: $b->link;
  $linkInfo = \App\Support\KebijakanBukti::periksa($b->link);
  $data = [
      'nama' => $nama, 'jenis' => $b->jenis(), 'oleh' => $b->label_oleh,
      'tanggal' => $b->diunggah_pada ? \App\Support\Tampil::tgl($b->diunggah_pada) : '', 'link' => $b->link,
      'buka' => $linkInfo['boleh'] && ! $b->ditolak_pada ? route('berkas.buka', $b) : '',
      'alasan_link' => $b->ditolak_pada ? 'Bukti ditolak: '.$b->alasan_penolakan : $linkInfo['alasan'],
  ];
@endphp
<a class="fb-link" href="{{ route('berkas.show', $b) }}" data-preview='@json($data)' aria-label="Lihat {{ $nama }}"><x-ikon n="Eye" :s="14" /> Lihat</a>
