@php
  use App\Enums\PeranPengguna as P;
  use App\Support\DirektoriIrm;
  use App\Support\PusatData;
  use App\Support\Rangka;
  use App\Support\Tampil;
  use Illuminate\Support\Arr;

  /* Tab Pengguna: filter, urutan, tindakan massal, detail, ekspor. Semuanya ikut alamat
     (`?peran=uki&masuk=sepi`). */
  $adaFilter = $f['filter'] !== 'semua' || $f['peran'] !== '' || $f['masuk'] !== '' || $f['q'] !== '';
  $paramUnduh = Arr::except($param, ['tab']);
  $dapatDipilih = $akunTampil->filter(fn ($a) => ! $alasanTolak($a))->count();
  /* Tindakan massal: `{n}` diganti skrip dengan jumlah akun yang dicentang. */
  $pastikanMassal = [
    'nonaktif' => ['judul' => 'Nonaktifkan {n} akun?',
      'ket' => 'User tidak bisa masuk dan semua sesinya langsung diakhiri. Riwayat aktivitas tetap tersimpan. Akun bisa diaktifkan kembali kapan saja.',
      'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false],
    'sesi' => ['judul' => 'Akhiri sesi {n} akun?',
      'ket' => 'Semua sesi akun yang dipilih akan diakhiri. User perlu masuk ulang lewat SSO; akunnya tetap aktif.',
      'tombol' => 'Ya, akhiri sesi', 'nada' => 'jingga', 'balik' => false],
  ];
@endphp
<x-dm-kepala judul="User & akses" ket="Kelola user, peran, izin masuk, dan cakupan aksesnya." :info="[
  'Daftar user yang terdaftar di aplikasi, beserta peran, cakupan Satker, dan izin loginnya.',
  'Peran menentukan fitur yang boleh digunakan. User dengan beberapa peran bekerja menggunakan satu peran aktif.',
  'Izin masuk menentukan apakah akun boleh login. Menonaktifkannya juga mengakhiri sesi yang sedang aktif.',
]">
  <form method="get" action="{{ route('master') }}" class="dm-cari" role="search" data-filter-baris="dm-t-pengguna">
    <input type="hidden" name="tab" value="pengguna">
    @foreach(['filter', 'peran', 'masuk', 'urut', 'arah'] as $n)
      @if(isset($param[$n]))<input type="hidden" name="{{ $n }}" value="{{ $param[$n] }}">@endif
    @endforeach
    <x-ikon n="Search" :s="14" />
    <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama, NIP, atau email" aria-label="Cari nama, NIP, atau email">
  </form>
  <details class="dm-tools">
    <summary class="btn"><x-ikon n="SlidersHorizontal" :s="14" /> Opsi lainnya</summary>
    <div class="dm-tools-isi">
      <a href="{{ route('master.pengguna.unduh', $paramUnduh) }}" download><x-ikon n="Download" :s="14" /> Ekspor CSV</a>
      @if($bolehBeri)<a href="{{ $ke(['jendela' => 'impor']) }}"><x-ikon n="ListPlus" :s="14" /> Tambah beberapa user</a>@endif
    </div>
  </details>
  @if($bolehBeri)
    <a class="btn btn-p" href="{{ $ke(['jendela' => 'pengguna']) }}"><x-ikon n="UserPlus" :s="14" /> Tambah user</a>
  @endif
</x-dm-kepala>

<div class="dm-filter" role="group" aria-label="Filter user">
  @foreach([['semua', 'Semua'], ['pusat', 'Petugas pusat'], ['pj', 'Penanggung jawab satuan kerja'], ['nonaktif', 'Nonaktif']] as [$k, $nama])
    <a href="{{ $ke(['filter' => $k === 'semua' ? null : $k]) }}" aria-pressed="{{ $f['filter'] === $k ? 'true' : 'false' }}">{{ $nama }} <span class="dm-n">{{ $jmlAkun[$k] }}</span></a>
  @endforeach
</div>

<form class="dm-saring" method="get" action="{{ route('master') }}" data-master-saring>
  <input type="hidden" name="tab" value="pengguna">
  @if($f['filter'] !== 'semua')<input type="hidden" name="filter" value="{{ $f['filter'] }}">@endif
  @if($f['q'] !== '')<input type="hidden" name="cari" value="{{ $f['q'] }}">@endif
  @if($f['urut'])<input type="hidden" name="urut" value="{{ $f['urut'] }}"><input type="hidden" name="arah" value="{{ $f['arah'] }}">@endif
  <label class="lg-pilih"><span>Peran</span>
    <select name="peran" data-kirim-master>
      <option value="">Semua peran</option>
      @foreach(PusatData::URUT_PERAN as $k)<option value="{{ $k }}" @selected($f['peran'] === $k)>{{ P::from($k)->pendek() }}</option>@endforeach
    </select>
  </label>
  <label class="lg-pilih"><span>Terakhir masuk</span>
    <select name="masuk" data-kirim-master>
      @foreach(PusatData::PILIHAN_MASUK as $k => $nama)<option value="{{ $k }}" @selected($f['masuk'] === (string) $k)>{{ $nama }}</option>@endforeach
    </select>
  </label>
  <button type="submit" class="btn" data-tanpa-js>Terapkan</button>
  @if($adaFilter)
    <a class="btn btn-s dm-atur-ulang" href="{{ $keTab('pengguna') }}"><x-ikon n="RotateCcw" :s="13" /> Reset filter</a>
  @endif
  <span class="dm-hitung" aria-live="polite" data-master-hitung="dm-t-pengguna" data-total="{{ $jmlAkun['semua'] }}">{{ $akunTampil->count() }} dari {{ $jmlAkun['semua'] }} akun</span>
</form>

{{-- Tindakan massal: kotak centang di tabel menunjuk formulir ini lewat atribut `form`
     (tabelnya sudah berisi formulir per baris). --}}
@if($dapatDipilih > 0)
  <form id="dm-massal" class="dm-massal" method="post" action="{{ route('master.pengguna.massal') }}" data-massal
    role="region" aria-label="Tindakan untuk akun yang dipilih">
    @csrf
    <input type="hidden" name="kembali" value="{{ $kembali }}">
    <span data-massal-teks>Centang akun di tabel untuk menonaktifkan, mengaktifkan, atau mengakhiri sesinya sekaligus.</span>
    <div class="dm-massal-aksi">
      <button type="submit" name="aksi" value="nonaktif" class="btn btn-s" data-pastikan='@json($pastikanMassal['nonaktif'])'>Nonaktifkan</button>
      <button type="submit" name="aksi" value="aktif" class="btn btn-s">Aktifkan kembali</button>
      <button type="submit" name="aksi" value="sesi" class="btn btn-s" data-pastikan='@json($pastikanMassal['sesi'])'><x-ikon n="LogOut" :s="13" /> Akhiri sesi</button>
      <button type="button" class="btn btn-s" data-batal-pilih hidden>Batal pilih</button>
    </div>
  </form>
@endif

<div class="dm-tabel">
  <table id="dm-t-pengguna">
    <thead><tr>
      <th class="dm-cek">
        <input type="checkbox" data-pilih-semua hidden aria-label="Pilih semua akun yang tampil dan bisa diubah" @disabled($dapatDipilih === 0)>
      </th>
      {!! $kolomUrut('nama', 'Nama') !!}
      {!! $kolomUrut('peran', 'Peran') !!}
      {!! $kolomUrut('masuk', 'Terakhir masuk') !!}
      <th>Izin masuk</th><th><span class="sr-only">Tindakan</span></th>
    </tr></thead>
    <tbody>
      @foreach($akunTampil as $a)
        @php
          $tolak = $alasanTolak($a);
        @endphp
        <tr id="akun-{{ $a->id }}" data-cari="{{ $a->name }} {{ $a->nip }} {{ $a->email }} {{ implode(' ', array_map(fn ($p) => $p->pendek(), $a->semuaPeran())) }} {{ $a->satker?->nama }} {{ $a->punyaPeran(P::PIMPINAN) ? $a->daftarSatkerDipimpin(true) : '' }}"
          @class(['mati' => ! $a->aktif, 'disorot' => $sorot === 'akun-'.$a->id])>
          <td class="dm-cek">
            @unless($tolak)
              <input type="checkbox" name="id[]" value="{{ $a->id }}" form="dm-massal" aria-label="Pilih {{ $a->name }}">
            @endunless
          </td>
          <td data-label="Nama">
            <span class="dm-akun">
              <span class="rupa" aria-hidden="true">{{ Rangka::inisial($a->name) }}</span>
              <span class="dm-orang">
                <b>{{ $a->name }}@if($a->is($saya))<span class="dm-anda">Anda</span>@endif</b>
                <span class="mono">NIP {{ DirektoriIrm::nipTampil($a->nip) }}</span>
              </span>
            </span>
          </td>
          <td data-label="Peran">
            <span class="dm-peran-daftar">@foreach($a->semuaPeran() as $p)<span class="dm-peran">{{ $p->pendek() }}</span>@endforeach</span>
            @if($a->peran === P::SATKER)
              <span class="dm-sub">{{ $a->satker?->namaPendek() }}</span>
            @endif
            @if($a->punyaPeran(P::PIMPINAN))
              <span class="dm-sub">Pimpinan · {{ $a->sebutSatkerDipimpin() }}</span>
            @endif
          </td>
          <td data-label="Terakhir masuk">@if($a->terakhir_masuk_pada){{ Tampil::waktuLog($a->terakhir_masuk_pada) }}@else<span class="dm-sub">Belum pernah</span>@endif</td>
          <td data-label="Izin masuk">{!! $keadaan($a->aktif, true) !!}</td>
          <td class="dm-aksi">
            <a class="btn btn-s" href="{{ $ke(['jendela' => 'detail-'.$a->id]) }}" aria-label="Kelola akses {{ $a->name }}"><x-ikon n="ShieldCheck" :s="13" /> Kelola akses</a>
          </td>
        </tr>
      @endforeach
      {!! $kosongCari($akunTampil->count()) !!}
      @if($akunTampil->isEmpty() && $cari === '')
        <tr class="dm-kosong-baris"><td colspan="9" class="dm-kosong">Tidak ada akun yang sesuai dengan filter.</td></tr>
      @endif
    </tbody>
  </table>
</div>
