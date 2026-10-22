@php
  use App\Enums\StatusTindakLanjut;
  use App\Support\Tampil;

  /* Putusan BPK atas satu baris yang sudah naik ke SIPTL, dalam kartu x-panel-kerja. */
  $st = $x->status_bpk ?? StatusTindakLanjut::BT;
  $tutup = $st === StatusTindakLanjut::SS;
@endphp

@if($jenis->melewatiSiptl() && $x->sudahSiptl())
<x-panel-kerja class="tl-siptl kerja-baca" ikon="Landmark" judul="Penilaian BPK lewat SIPTL" :info="[
  'Status dan catatan BPK atas bukti tindak lanjut yang diunggah ke SIPTL, disalin Setba dari hasil pemantauan.',
  'SS berarti sudah sesuai, BS belum sesuai, dan BT belum ditindaklanjuti. Hasil ini terpisah dari verifikasi Inspektorat.',
]">
  <x-slot:kalimat>@if($x->siptl_tanggal)Berkas <b>{{ $x->satker->namaPendek() }}</b> diunggah ke SIPTL {{ Tampil::tgl($x->siptl_tanggal) }}.@elseStatus SIPTL <b>{{ $x->satker->namaPendek() }}</b> berasal dari data historis. Tanggal unggah belum tercatat.@endif</x-slot:kalimat>
  <x-baris-isi label="Status BPK" :kunci="'siptl-st-'.$x->id">
    <p class="kerja-teks"><x-cap :s="$st" />@if($st !== StatusTindakLanjut::BT && $x->tgl_pantau)<span class="kerja-meta">dipantau {{ Tampil::tgl($x->tgl_pantau) }}</span>@endif</p>
  </x-baris-isi>
  <x-baris-isi label="Catatan BPK" :kunci="'siptl-ct-'.$x->id">
    <p class="kerja-teks{{ $x->catatan_bpk ? '' : ' kerja-redup' }}">{{ $x->catatan_bpk ?: ($st === StatusTindakLanjut::BT ? 'Belum ada — BPK belum memutus.' : 'Tanpa catatan.') }}</p>
  </x-baris-isi>
  {{-- Menjelaskan kenapa tidak ada lagi yang bisa dicatat (tanpa ini terbaca seperti rusak). --}}
  @if($tutup || $st === StatusTindakLanjut::BS)
    <p class="tl-siptl-kunci">
      <x-ikon :n="$tutup ? 'CheckCircle2' : 'RotateCcw'" :s="14" />
      <span>{{ $tutup
        ? 'Putusan ini sudah terkunci. Pemantauan tindak lanjut ini selesai.'
        : 'Putusan ini sudah terkunci. Statusnya baru bisa dinilai lagi sesudah berkasnya dikirim ulang ke satuan kerja dan diunggah ulang ke SIPTL.' }}</span>
    </p>
  @endif
</x-panel-kerja>
@endif
