@php
  use App\Support\Tampil;

  /* Meja pemberkasan ulang, per baris — padanan panelKirimUlangBaris. Setba
     menyetel dokumen yang diminta, berpegang pada pernyataan yang menolak.
     Sejak 25 Sep berbentuk panel kerja (`x-panel-kerja`). */
  $dariSiapa = $x->kembali_dari ?: 'pemeriksa';
  $dokAwal = $x->dokumen_diminta ?: [''];
  $lewat = $x->batas_perbaikan && $x->batas_perbaikan->copy()->startOfDay()->lt(now()->startOfDay());
  $pastikan = [
    'judul' => 'Kirim ulang berkas '.$x->satker->namaPendek().'?',
    'ket' => 'Berkas kembali ke meja '.$x->satker->namaPendek().' untuk pemberkasan ulang, bersama alasan penolakan '.$dariSiapa
      .($x->batas_perbaikan ? ' dan batas waktunya ('.Tampil::tgl($x->batas_perbaikan).')' : '')
      .'. Satuan kerja lain pada rekomendasi ini tidak ikut.',
    'tombol' => 'Ya, kirim ulang', 'nada' => 'kuning',
  ];
  $k = $x->id;
@endphp

<form method="post" action="{{ route('sasaran.kirimUlang', $x) }}" id="r-terus" data-form-kirim-ulang
  data-satker="{{ $x->satker->namaPendek() }}" data-dari="{{ $dariSiapa }}" data-batas="{{ $x->batas_perbaikan ? Tampil::tgl($x->batas_perbaikan) : '' }}">
  @csrf
  <x-panel-kerja ikon="RotateCcw" judul="Kirim ulang ke satuan kerja"
    :lanjut="'Berkas kembali ke '.$x->satker->namaPendek().' bersama alasan '.$dariSiapa.'.'"
    :info="[
      'Berkas yang ditolak UKI atau Inspektorat tidak langsung pulang ke satuan kerja. Setba yang mengirimkannya ulang.',
      'Alasan dan batas waktunya ditetapkan yang menolak, dan tidak diubah di sini.',
      'Yang dikirim ulang hanya satuan kerja pada baris ini. Yang lain tetap di tempatnya.',
    ]">
    <x-slot:kalimat>Berkas <b>{{ $x->satker->namaPendek() }}</b> dikembalikan untuk pemberkasan ulang.</x-slot:kalimat>
    <x-baris-isi :label="'Ditolak '.$dariSiapa">
      <div class="kerja-teks">{{ $x->alasan_perbaikan ?: 'Tanpa catatan.' }}@if($x->batas_perbaikan)<span @class(['kerja-meta', 'lewat' => $lewat])>perbaiki paling lambat {{ Tampil::tgl($x->batas_perbaikan) }}{{ $lewat ? ' — sudah lewat' : '' }}</span>@endif</div>
    </x-baris-isi>
    {{-- Setba yang menyetel dokumen yang diminta, berpegang pada pernyataan
         yang menolak (Hizkia, 14 Sep). --}}
    <x-baris-isi label="Dokumen yang diminta" gabung :kunci="'dok-ulang-'.$k" :info="[
      $x->dokumen_diminta ? 'Diisi dari permintaan '.$dariSiapa.'. Periksa dan sesuaikan kalau perlu.'
        : $dariSiapa.' tidak menyebut dokumen. Isi kalau alasannya memang meminta dokumen tertentu.',
      'Satuan kerja wajib melampirkan seluruhnya sebelum bisa mengirim ulang ke Setba.',
    ]">
      @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => $dokAwal, 'placeholder' => 'Nama dokumen', 'tambah' => 'Tambah dokumen'])
    </x-baris-isi>
    <x-baris-isi label="Keterangan Setba" :untuk="'ket-ulang-'.$k"
      :info="'Gunanya memperjelas maksud penolakan '.$dariSiapa.' supaya satuan kerja mudah memperbaikinya.'">
      <textarea id="ket-ulang-{{ $k }}" rows="2" name="keterangan" maxlength="500"
        placeholder="Perjelas maksud catatan {{ $dariSiapa }} supaya satuan kerja mudah memperbaikinya."></textarea>
    </x-baris-isi>
    <x-slot:aksi>
      <button type="submit" class="btn btn-p" data-pastikan='@json($pastikan)'>
        <x-ikon n="RotateCcw" :s="14" /> Kirim ulang ke satuan kerja
      </button>
    </x-slot:aksi>
  </x-panel-kerja>
</form>
