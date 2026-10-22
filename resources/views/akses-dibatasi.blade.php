@extends($rangkaMasuk ?? 'rangka')
@section('judul', ($akses ?? true) ? 'Tidak memiliki akses' : 'Gagal masuk')
@section('isi')
{{-- Pegawai yang lolos SSO tapi tidak diberi akses di Data master (belum didaftarkan atau
     dinonaktifkan). --}}
<x-latar-masuk />
<main class="msk tunggal">
  <section class="msk-sambut"><x-merek-masuk /></section>
  <section class="msk-panel">
    <div class="msk-kartu">
      @if($akses ?? true)
        <span class="ic-kotak besar kuning msk-ikon-atas"><x-ikon n="Lock" :s="20" /></span>
        <h1>Tidak memiliki akses</h1>
        <p class="sub">Anda tidak memiliki akses untuk sistem ini. Hubungi Admin atau pihak berwenang atas sistem ini.</p>
        <x-fakta :isi="array_values(array_filter([
          $id && $id->nama ? ['l' => 'Nama', 'v' => e($id->nama)] : null,
          $id && $id->nip ? ['l' => 'NIP', 'v' => e(\App\Support\DirektoriIrm::nipTampil($id->nip)), 'mono' => true] : null,
          $id && $id->unit ? ['l' => 'Unit', 'v' => e($id->unit)] : null,
          ['l' => 'Keterangan', 'v' => e($alasan)],
        ]))" />
        <p class="msk-teks">Saat menghubungi Admin, sebutkan NIP atau email akun Anda. Admin mengatur akses di Data Master &rarr; User &amp; akses.</p>
      @else
        <span class="ic-kotak besar kuning msk-ikon-atas"><x-ikon n="AlertTriangle" :s="20" /></span>
        <h1>Gagal masuk</h1>
        <p class="sub">{{ $alasan }}</p>
        <p class="msk-teks">Coba masuk sekali lagi. Bila tetap gagal, hubungi Admin.</p>
      @endif
      <p class="msk-kembali"><a class="btn" href="{{ route('masuk') }}"><x-ikon n="ArrowLeft" :s="15" /> Kembali ke halaman masuk</a></p>
    </div>
  </section>
</main>
@endsection
