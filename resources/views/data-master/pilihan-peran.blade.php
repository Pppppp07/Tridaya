@php
  /* Pilihan peran yang boleh diberikan pengubahnya (PeranPengguna::bolehMemberi). `$namaIsian` =
     nama isian radionya. */
  $ketPeran = [
    'setba' => 'Mencatat laporan, meneruskan berkas, dan mengurus SIPTL.',
    'uki' => 'Menelaah berkas yang diteruskan Setba dan mencatat hasil validasinya.',
    'inspektorat' => 'Memverifikasi berkas dan mencatat hasilnya beserta surat CHV.',
    'pimpinan' => 'Memantau Dashboard, laporan, dan rekomendasi Satker yang dipimpin. Hanya melihat.',
    'dti' => 'Memantau Log aktivitas dan membaca rekomendasi. Hanya melihat.',
    'admin' => 'Mengelola Data Master: user, akses, satuan kerja, dan kategori.',
  ];
@endphp
@php $banyak = $banyak ?? false; $nilaiBanyak = $banyak ? (array) $nilai : []; @endphp
<div class="dm-peran-pilih" role="{{ $banyak ? 'group' : 'radiogroup' }}" aria-label="Peran">
  @foreach($bolehBeri as $p)
    <label class="dm-opsi{{ $nilai === $p->value ? ' pilih' : '' }}">
      <input type="{{ $banyak ? 'checkbox' : 'radio' }}" name="{{ $banyak ? 'perans[]' : ($namaIsian ?? 'peran') }}" value="{{ $p->value }}" @checked($banyak ? in_array($p->value, $nilaiBanyak, true) : $nilai === $p->value) @required(! $banyak)>
      <b>{{ $p->pendek() }}</b><span>{{ $ketPeran[$p->value] ?? '' }}</span>
      @unless($banyak)<span class="dm-hak-terpilih">
        <strong>Hak peran ini</strong>
        @foreach($p->hak() as $hak)<span>• {{ $hak }}</span>@endforeach
      </span>@endunless
    </label>
  @endforeach
</div>
