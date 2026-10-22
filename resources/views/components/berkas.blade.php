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
  /* Hanya alamat web yang dijadikan link; isian lain (mis. `javascript:`) tidak pernah masuk
     `href`. */
  $linkInfo = \App\Support\KebijakanBukti::periksa($link);
  $linkSah = $linkInfo['boleh'] && ! $b?->ditolak_pada;
  $buka = $linkSah ? ($b ? route('berkas.buka', $b) : \App\Support\KebijakanBukti::pintu($link)) : '';
  $alasan = $b?->ditolak_pada ? 'Bukti ditolak: '.$b->alasan_penolakan : ($link ? $linkInfo['alasan'] : '');
  $data = [
      'nama' => $nama, 'jenis' => $jenis, 'oleh' => $oleh,
      'tanggal' => $tanggal ? \App\Support\Tampil::tgl($tanggal) : '', 'link' => $link,
      'buka' => $buka, 'alasan_link' => $alasan,
  ];
@endphp

{{-- Yang disimpan link-nya. Menekannya membuka preview (skrip); tanpa skrip membuka rute berkas
     yang terotorisasi. --}}
@if($nama)
  <a class="berkas" @if($penuh) style="width:100%" @endif
    href="{{ $b ? route('berkas.show', $b) : ($buka ?: '#') }}"
    data-preview='@json($data)'
    title="{{ $link ? 'Buka link '.$nama : 'Buka '.$nama }}">
    <x-ikon :n="$link ? 'ExternalLink' : 'Paperclip'" :s="13" class="ic" />
    <span class="nm">{{ $nama }}</span>
    @if($link && ! $linkSah)<span class="lbl">Perlu diperbarui</span>@endif
    <span style="flex:1"></span>
    <x-ikon n="Eye" :s="13" class="ic" />
  </a>
@endif
