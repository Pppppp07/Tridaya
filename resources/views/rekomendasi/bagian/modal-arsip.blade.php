@php
  use App\Support\Tampil;
@endphp

{{-- Arsip rekomendasi: seluruh berkasnya di satu jendela (dari tombol di atas halaman). --}}
<div id="arsip" class="tirai tirai-arsip" data-arsip>
  <div class="lembar arsip" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="arsip-judul">
    <div class="kep">
      <span class="ic-kotak"><x-ikon n="Paperclip" :s="17" /></span>
      <span class="judul">
        <b id="arsip-judul">Arsip rekomendasi</b>
        <span class="lbl">{{ $arsip->count() }} berkas · Rekomendasi {{ $r->refLhp() ?: $r->kode }}</span>
      </span>
      <x-info :teks="[
        'Kumpulan dokumen yang terkait dengan rekomendasi ini, termasuk surat, bukti tindak lanjut, dan bukti pemulihan nilai.',
        'Setiap dokumen menampilkan jenis, pengirim, dan tanggal pencatatannya. File dibuka melalui link ke layanan penyimpanan asal.',
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
