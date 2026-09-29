@props(['s' => null, 'jenis' => null, 'rek' => null, 'satker' => null])

@php
  /* Lencana status BPK. Jenis yang tidak pernah sampai ke SIPTL — LHA — tidak
     punya status BPK; yang berlaku baginya putusan Inspektorat, dan itu yang
     ditampilkan. Penjaganya di sini, bukan di tiap pemanggil.

     `satker` (27 Sep): diisi id satuan kerja kalau yang melihat akun satuan
     kerja — ia membaca keadaan tindak lanjutnya sendiri, bukan seluruh
     rekomendasi (Rekomendasi::keadaanUntuk, statusBpkUntuk). */
  $jenis = $jenis instanceof \App\Enums\SumberLaporan ? $jenis : \App\Enums\SumberLaporan::tryFrom((string) $jenis);
  $s = $s instanceof \App\Enums\StatusTindakLanjut ? $s : \App\Enums\StatusTindakLanjut::tryFrom((string) $s);
  if ($rek && $satker) {
    $s = $rek->statusBpkUntuk($satker);
  }
@endphp

@if($jenis && ! $jenis->melewatiSiptl())
  @if($rek)
    @php $h = $rek->keadaanUntuk($satker); @endphp
    <span class="cap {{ $h->cap() }}">{{ $h->nama($jenis) }}</span>
  @endif
@elseif($s)
  <span class="cap {{ $s->cap() }}">{{ $s->value }} · {{ $s->pendek() }}</span>
@endif
