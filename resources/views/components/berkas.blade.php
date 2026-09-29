@props(['b' => null, 'nama' => null, 'jenis' => null, 'oleh' => null, 'tanggal' => null, 'link' => null, 'penuh' => false])

@php
  /* Berkas dari model Lampiran, atau dari isian lepas (nama + link). */
  if ($b) {
      $nama ??= $b->nama_asli ?: $b->link;
      $jenis ??= $b->jenis();
      $oleh ??= $b->label_oleh;
      $tanggal ??= $b->diunggah_pada;
      $link ??= $b->link;
  }
  /* Hanya alamat web yang dijadikan link (27 Sep). Isian lama yang bukan
     alamat web — `javascript:` misalnya — tetap terbaca di preview, tapi
     tidak pernah masuk `href`. */
  $linkSah = \App\Rules\LinkAman::sah($link);
  $data = [
      'nama' => $nama, 'jenis' => $jenis, 'oleh' => $oleh,
      'tanggal' => $tanggal ? \App\Support\Tampil::tgl($tanggal) : '', 'link' => $link,
  ];
@endphp

{{-- Berkasnya tidak disimpan di server — yang disimpan link-nya. Menekannya
     membuka preview (skrip); tanpa skrip ia langsung membuka rute berkas
     yang terotorisasi, atau link-nya. --}}
@if($nama)
  <a class="berkas" @if($penuh) style="width:100%" @endif
    href="{{ $b ? route('berkas.show', $b) : ($linkSah ? $link : '#') }}"
    data-preview='@json($data)'
    title="{{ $link ? 'Buka link '.$nama : 'Buka '.$nama }}">
    <x-ikon :n="$link ? 'ExternalLink' : 'Paperclip'" :s="13" class="ic" />
    <span class="nm">{{ $nama }}</span>
    <span style="flex:1"></span>
    <x-ikon n="Eye" :s="13" class="ic" />
  </a>
@endif
