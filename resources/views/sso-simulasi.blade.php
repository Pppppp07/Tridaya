@extends('rangka')
@section('judul', 'Simulasi SSO')
@section('isi')
@php
  use App\Support\DirektoriIrm;
  use App\Support\Rangka;
@endphp
{{-- Pengganti halaman masuk penyedia SSO, KHUSUS demo (SIMTLHP_SSO=
     simulasi; tidak pernah menyala di produksi). Memilih seseorang di sini =
     penyedia SSO membuktikan orang itu. Sesudahnya semua pemeriksaan berjalan
     sungguhan: NIP terdaftar, akun aktif, penyamaan atribut, log aktivitas. --}}
<x-latar-masuk />
<main class="msk tunggal">
  <section class="msk-sambut"><x-merek-masuk /></section>
  <section class="msk-panel">
    <div class="msk-kartu lebar">
      <h1>Simulasi SSO eHRM</h1>
      <p class="sub">Simulasi tanpa server Pusdatin. Pilih pegawai yang seolah-olah baru masuk lewat SSO.</p>
      <div class="pesan warn"><x-ikon n="AlertTriangle" :s="16" /><span>Halaman ini hanya ada di mode demo. Di server resmi, tombol Masuk dengan SSO membuka halaman masuk eHRM.</span></div>

      <div class="lbl" style="margin:16px 0 8px">Contoh cepat</div>
      <div class="daftarpegawai">
        @foreach($contoh as $c)
          <form method="post" action="{{ route('sso.simulasi.pilih') }}">
            @csrf
            <input type="hidden" name="nip" value="{{ $c['nip'] }}">
            <button type="submit" class="pegawai">
              <span class="rupa">{{ Rangka::inisial($c['nama']) }}</span>
              <span class="teks"><b>{{ $c['nama'] }}</b><span class="mono">NIP {{ DirektoriIrm::nipTampil($c['nip']) }}</span></span>
              <span class="ket">{{ $c['ket'] }} <x-ikon n="ChevronRight" :s="13" /></span>
            </button>
          </form>
        @endforeach
      </div>

      <div class="lbl" style="margin:18px 0 8px">Atau cari di direktori</div>
      <form method="get" action="{{ route('sso.simulasi') }}" class="cari-irm">
        <x-ikon n="Search" :s="15" />
        <input type="text" name="q" value="{{ $q }}" placeholder="Ketik nama atau NIP" aria-label="Cari pegawai" autocomplete="off">
      </form>
      @if(mb_strlen($q) >= 2)
        <div class="lbl hasil-lbl">{{ $hasil->count() ? $hasil->count().' pegawai cocok' : 'Tidak ada pegawai yang cocok.' }}</div>
        <div class="daftarpegawai">
          @foreach($hasil as $p)
            @php $a = $akun[$p['nip']] ?? null; @endphp
            <form method="post" action="{{ route('sso.simulasi.pilih') }}">
              @csrf
              <input type="hidden" name="nip" value="{{ $p['nip'] }}">
              <button type="submit" class="pegawai">
                <span class="rupa">{{ Rangka::inisial($p['nama']) }}</span>
                <span class="teks">
                  <b>{{ $p['nama'] }}</b>
                  <span class="mono">NIP {{ DirektoriIrm::nipTampil($p['nip']) }}</span>
                  <span>{{ $p['jabatan'] }} · {{ $p['unitNama'] }}</span>
                </span>
                <span class="ket">{{ $a ? ($a->aktif ? $a->peran->pendek() : 'akun nonaktif') : 'belum terdaftar' }}</span>
              </button>
            </form>
          @endforeach
        </div>
      @endif
      <p class="msk-kembali"><a class="btn" href="{{ route('masuk') }}"><x-ikon n="ArrowLeft" :s="15" /> Kembali ke halaman masuk</a></p>
    </div>
  </section>
</main>
@endsection
