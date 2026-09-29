@php
  use App\Support\Tampil;
@endphp

{{-- Arsip rekomendasi: seluruh berkas rekomendasi ini di satu tempat, tanpa
     kecuali, termasuk bukti setoran. Jendela melayang dari tombol di atas
     halaman, bukan kartu di kaki halaman (rapat 18 Sep: "Gua tuh menghindari
     banyak scroll"). Padanan `JendelaArsip` prototipe (disamakan 25 Sep).

     Terbuka lewat #arsip walau skrip tidak jalan. Skripnya (pasangArsip)
     membukanya tanpa mengubah alamat, mengunci latarnya, menaruh fokus di
     tombol Tutup, lalu mengembalikannya ke tombol pembuka — sama dengan
     panduan. --}}
<div id="arsip" class="tirai tirai-arsip" data-arsip>
  <div class="lembar arsip" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="arsip-judul">
    <div class="kep">
      <span class="ic-kotak"><x-ikon n="Paperclip" :s="17" /></span>
      <span class="judul">
        <b id="arsip-judul">Arsip rekomendasi</b>
        <span class="lbl">{{ $arsip->count() }} berkas · Rekomendasi {{ $r->refLhp() ?: $r->kode }}</span>
      </span>
      <x-info :teks="[
        'Tempat mengumpulkan seluruh berkas rekomendasi ini, baik yang diunggah satuan kerja maupun unit lain.',
        'Bukti setoran ikut tersimpan di sini, dan tetap tampil juga di tabel pemulihan nilai.',
        'Berkas yang sudah dikirim tidak bisa dihapus atau ditarik siapa pun, termasuk pengunggahnya.',
        'Berkas yang keliru diganti saat berkasnya dikirim ulang untuk pemberkasan ulang. Berkas lama tetap tersimpan sebagai jejak.',
        'Berkas di sini berupa link. Jika link tersebut memuat data pribadi, tutup aksesnya di tempat penyimpanannya.',
      ]" />
      <a class="btn btn-s" href="#r-arsip" data-tutup-arsip><x-ikon n="X" :s="13" /> Tutup</a>
    </div>
    <div class="bdn">
      @forelse($arsip as $d)
        <div class="arsip-berkas">
          <x-berkas :b="$d" :penuh="true" />
          <div class="lbl">{{ $d->jenis() }} · diunggah oleh <b>{{ $d->label_oleh }}</b> · {{ Tampil::tgl($d->diunggah_pada) }}</div>
        </div>
      @empty
        <div class="arsip-kosong">
          <x-ikon n="Paperclip" :s="32" />
          <b>Belum ada berkas yang diunggah</b>
          <span class="lbl">Berkas yang diunggah satuan kerja atau pemeriksa akan tersimpan di sini.</span>
        </div>
      @endforelse
    </div>
    <div class="kak">
      <span class="lbl">Total: {{ $arsip->count() }} berkas terarsip</span>
      <a class="btn btn-s" href="#r-arsip" data-tutup-arsip>Tutup</a>
    </div>
  </div>
</div>
