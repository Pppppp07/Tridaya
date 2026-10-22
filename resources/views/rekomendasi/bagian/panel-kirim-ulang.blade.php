@php
  use App\Support\Tampil;

  /* Meja pemberkasan ulang per baris: Setba menyetel dokumen yang diminta, berpegang pada
     pernyataan penolaknya. */
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
      'Setba meninjau catatan pengembalian dari UKI atau Inspektorat, lalu mengirim ulang berkas ke satuan kerja.',
      'Catatan pemeriksa dan batas waktu perbaikan tidak diubah melalui form ini.',
      'Pengiriman ulang hanya berlaku untuk penugasan yang sedang dibuka. Penugasan lainnya tidak berubah.',
    ]">
    <x-slot:kalimat>Berkas <b>{{ $x->satker->namaPendek() }}</b> dikembalikan untuk pemberkasan ulang.</x-slot:kalimat>
    <x-baris-isi :label="'Ditolak '.$dariSiapa">
      <div class="kerja-teks">{{ $x->alasan_perbaikan ?: 'Tanpa catatan.' }}@if($x->batas_perbaikan)<span @class(['kerja-meta', 'lewat' => $lewat])>perbaiki paling lambat {{ Tampil::tgl($x->batas_perbaikan) }}{{ $lewat ? ' — sudah lewat' : '' }}</span>@endif</div>
    </x-baris-isi>
    <x-baris-isi label="Dokumen yang diminta" gabung :kunci="'dok-ulang-'.$k" :info="[
      $x->dokumen_diminta ? 'Daftar awal mengikuti permintaan '.$dariSiapa.'. Periksa dan sesuaikan dengan catatan perbaikan.'
        : 'Tambahkan dokumen yang perlu dilengkapi sesuai catatan '.$dariSiapa.'.',
      'Satuan kerja perlu melengkapi seluruh dokumen yang diminta sebelum mengirim ulang tanggapan ke Setba.',
    ]">
      @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => $dokAwal, 'placeholder' => 'Nama dokumen', 'tambah' => 'Tambah dokumen'])
    </x-baris-isi>
    <x-baris-isi label="Keterangan Setba" :untuk="'ket-ulang-'.$k"
      :info="'Petunjuk tambahan untuk membantu satuan kerja memperbaiki tindak lanjut sesuai catatan '.$dariSiapa.'.'">
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
