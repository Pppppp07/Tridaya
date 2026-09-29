@php
  use App\Enums\StatusTindakLanjut;
  use App\Support\Tampil;

  /* Putusan BPK atas satu baris, di rincian baris itu sendiri — padanan
     `SiptlBaris`. Hanya baris yang sudah naik: sebelum itu BPK belum melihat
     apa pun.

     Sejak 27 Sep kartu x-panel-kerja seperti tab-tab di bawahnya. Tanggal
     unggahnya naik ke kalimat kepala bersama nama pemilik berkasnya; barisnya
     tinggal putusan BPK dan catatannya. Garis kiri berwarna dibuang — warnanya
     sudah dibawa lencana statusnya. */
  $st = $x->status_bpk ?? StatusTindakLanjut::BT;
  $tutup = in_array($st, [StatusTindakLanjut::SS, StatusTindakLanjut::TD], true);
@endphp

@if($jenis->melewatiSiptl() && $x->siptl_tanggal)
<x-panel-kerja class="tl-siptl kerja-baca" ikon="Landmark" judul="Penilaian BPK lewat SIPTL" :info="[
  'SIPTL aplikasi milik BPK, di luar sistem ini. Setba mengunggah tindak lanjut satuan kerja ke sana, lalu menyalin putusan BPK ke sini dengan tangan.',
  'BPK tidak mengirim pemberitahuan apa pun, jadi statusnya dicek berkala di SIPTL.',
  'Putusan BPK dicatat sekali untuk tiap unggahan, lalu terkunci. Sudah Sesuai mengakhiri pemantauan baris ini; Belum Sesuai baru bisa dinilai lagi sesudah berkasnya dikirim ulang, diperbaiki, dan diunggah ulang ke SIPTL.',
  'Status BPK bisa Belum Sesuai gara-gara Unor lain yang bukan urusan BPSDM. Alasannya ditulis di catatan BPK.',
]">
  <x-slot:kalimat>Berkas <b>{{ $x->satker->namaPendek() }}</b> diunggah ke SIPTL {{ Tampil::tgl($x->siptl_tanggal) }}.</x-slot:kalimat>
  <x-baris-isi label="Status BPK" :kunci="'siptl-st-'.$x->id">
    <p class="kerja-teks"><x-cap :s="$st" />@if($st !== StatusTindakLanjut::BT && $x->tgl_pantau)<span class="kerja-meta">dipantau {{ Tampil::tgl($x->tgl_pantau) }}</span>@endif</p>
  </x-baris-isi>
  <x-baris-isi label="Catatan BPK" :kunci="'siptl-ct-'.$x->id">
    <p class="kerja-teks{{ $x->catatan_bpk ? '' : ' kerja-redup' }}">{{ $x->catatan_bpk ?: ($st === StatusTindakLanjut::BT ? 'Belum ada — BPK belum memutus.' : 'Tanpa catatan.') }}</p>
  </x-baris-isi>
  {{-- Kenapa tidak ada lagi yang bisa dicatat di sini. Tanpa kalimat ini tab
       Kerjakan yang hilang terbaca seperti sesuatu yang rusak. --}}
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
