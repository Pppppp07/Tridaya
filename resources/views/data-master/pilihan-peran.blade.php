@php
  /* Pilihan peran di jendela Tambah pengguna dan Ubah peran — padanan
     `PilihanPeran` prototipe. Hanya peran yang boleh diberikan pengubahnya
     (PeranPengguna::bolehMemberi). */
  $ketPeran = [
    'setba' => 'Mencatat laporan, meneruskan berkas, mengurus SIPTL, dan mengatur Data master.',
    'uki' => 'Menelaah berkas yang diteruskan Setba dan mencatat hasil validasinya.',
    'inspektorat' => 'Memverifikasi berkas dan mencatat hasilnya beserta surat CHV.',
    'pimpinan' => 'Memantau Dashboard dan membaca rekomendasi. Hanya melihat.',
    'dti' => 'Menjaga data: membaca Log aktivitas dan rekomendasi. Hanya melihat.',
    'admin' => 'Pengelola teknis: semua urusan Data master, termasuk akun DTI dan Admin.',
  ];
@endphp
<div class="dm-peran-pilih" role="radiogroup" aria-label="Peran">
  @foreach($bolehBeri as $p)
    <label class="dm-opsi{{ $nilai === $p->value ? ' pilih' : '' }}">
      <input type="radio" name="peran" value="{{ $p->value }}" @checked($nilai === $p->value) required>
      <b>{{ $p->pendek() }}</b><span>{{ $ketPeran[$p->value] ?? '' }}</span>
    </label>
  @endforeach
</div>
