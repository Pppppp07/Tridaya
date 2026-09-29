@extends('rangka')
@section('judul', 'Profil & pengaturan')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;
  use App\Support\DirektoriIrm;
  use App\Support\Rangka;
  use App\Support\Tampil;

  /* Profil & pengaturan — padanan `LayarAkun` prototipe (28 Sep). Kerangkanya
     sama dengan Data master: kartu pengenal akun, deret tab (link
     `?tab=`), lalu satu kartu. Isi tiap tab di resources/views/akun/. */
  $sebutan = $u->peran === P::SATKER
    ? 'Satuan kerja · '.($u->satker?->namaPendek() ?? '')
    : Rangka::pengguna($u)['peran'];
  $tabs = [
    ['k' => 'profil', 'nama' => 'Profil', 'ikon' => 'UserRound'],
    ['k' => 'keamanan', 'nama' => 'Keamanan', 'ikon' => 'ShieldCheck'],
    ['k' => 'pemberitahuan', 'nama' => 'Pemberitahuan', 'ikon' => 'Bell'],
    ['k' => 'tampilan', 'nama' => 'Tampilan', 'ikon' => 'SlidersHorizontal'],
    ['k' => 'aktivitas', 'nama' => 'Aktivitas akun', 'ikon' => 'History'],
  ];
@endphp

<div class="body dm ak">
  <section class="ak-kepala" aria-label="Akun Anda">
    <span class="rupa" aria-hidden="true">{{ Rangka::inisial($u->name) }}</span>
    <span class="teks">
      <h2>{{ $u->name }}</h2>
      <span class="peran">{{ $sebutan }}</span>
      <span class="meta">
        @if($u->email)<span><x-ikon n="Mail" :s="13" /> {{ $u->email }}</span>@endif
        @if($u->nip)<span class="mono">NIP {{ DirektoriIrm::nipTampil($u->nip) }}</span>@endif
        @if($u->terakhir_masuk_pada)<span><x-ikon n="Clock" :s="13" /> Masuk {{ Tampil::sejak($u->terakhir_masuk_pada) }}</span>@endif
      </span>
    </span>
    <span class="dm-keadaan aktif">Akun aktif</span>
  </section>

  <div class="tl-tabs dm-tabs" role="tablist" aria-label="Profil dan pengaturan">
    @foreach($tabs as $t)
      <a role="tab" id="ak-tab-{{ $t['k'] }}" href="{{ route('akun', ['tab' => $t['k']]) }}"
        aria-selected="{{ $tab === $t['k'] ? 'true' : 'false' }}" @if($tab === $t['k']) aria-current="page" @endif>
        <x-ikon :n="$t['ikon']" :s="14" />{{ $t['nama'] }}
      </a>
    @endforeach
  </div>

  <section class="kartu dm-panel" role="tabpanel" aria-labelledby="ak-tab-{{ $tab }}">
    @include('akun.'.$tab)
  </section>
  {{-- Jalur keluar tetap tersedia saat tombol akun menjadi link biasa
       ke halaman ini (JavaScript dimatikan atau gagal dimuat). --}}
  <form method="post" action="{{ route('keluar') }}">
    @csrf
    <button type="submit" class="btn"><x-ikon n="LogOut" :s="16" /> Keluar dari akun</button>
  </form>
</div>
@endsection
