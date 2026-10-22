@php
  use App\Support\Tampil;
  use Illuminate\Support\Arr;

  /* Tab Riwayat perubahan: potongan log data master dan hak akses. Unduhan CSV memuat semua
     baris yang cocok filternya. */
  $rentang = ['7' => '7 hari', '30' => '30 hari', '90' => '90 hari', 'semua' => 'Semua waktu'];
  $batas = max(30, (int) request('batas', 30));
  $adaFilter = $f['filter'] !== 'semua' || $f['rentang'] !== 'semua' || $f['pelaku'] > 0 || $f['q'] !== '';
  $paramUnduh = Arr::except($param, ['tab']);
@endphp
<x-dm-kepala judul="Log admin" ket="Perubahan user, akses, satuan kerja, dan referensi." :info="[
  'Riwayat perubahan user, hak akses, satuan kerja, dan referensi di Data Master.',
  'Setiap baris memuat waktu, pelaku, aktivitas, target, dan hasil. Buka Detail untuk melihat data sebelum dan sesudah perubahan.',
]">
  <form method="get" action="{{ route('master') }}" class="dm-cari" role="search">
    <input type="hidden" name="tab" value="riwayat">
    @foreach(['filter', 'rentang', 'pelaku'] as $n)
      @if(isset($param[$n]))<input type="hidden" name="{{ $n }}" value="{{ $param[$n] }}">@endif
    @endforeach
    <x-ikon n="Search" :s="14" />
    <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama atau perubahan" aria-label="Cari nama atau perubahan">
  </form>
  <a class="btn" href="{{ route('master.riwayat.unduh', $paramUnduh) }}" download><x-ikon n="Download" :s="14" /> Ekspor CSV</a>
</x-dm-kepala>

<div class="dm-filter" role="group" aria-label="Filter perubahan">
  @foreach(['semua' => 'Semua', 'unit' => 'Satuan kerja', 'pengguna' => 'User & penanggung jawab', 'temuan' => 'Kategori temuan', 'intern' => 'Kategori internal', 'sifat' => 'Sifat', 'bentuk' => 'Bentuk tindak lanjut'] as $k => $nama)
    <a href="{{ $ke(['filter' => $k === 'semua' ? null : $k, 'batas' => null]) }}" aria-pressed="{{ $f['filter'] === $k ? 'true' : 'false' }}">{{ $nama }}</a>
  @endforeach
</div>

<form class="dm-saring" method="get" action="{{ route('master') }}" data-master-saring>
  <input type="hidden" name="tab" value="riwayat">
  @if($f['filter'] !== 'semua')<input type="hidden" name="filter" value="{{ $f['filter'] }}">@endif
  @if($f['q'] !== '')<input type="hidden" name="cari" value="{{ $f['q'] }}">@endif
  <label class="lg-pilih"><span>Rentang waktu</span>
    <select name="rentang" data-kirim-master>
      @foreach($rentang as $k => $nama)<option value="{{ $k }}" @selected($f['rentang'] === (string) $k)>{{ $nama }}</option>@endforeach
    </select>
  </label>
  <label class="lg-pilih"><span>Oleh</span>
    <select name="pelaku" data-kirim-master>
      <option value="">Semua user</option>
      @foreach($pelaku as $p)<option value="{{ $p->user_id }}" @selected($f['pelaku'] === (int) $p->user_id)>{{ $p->nama }}</option>@endforeach
    </select>
  </label>
  <button type="submit" class="btn" data-tanpa-js>Terapkan</button>
  @if($adaFilter)
    <a class="btn btn-s dm-atur-ulang" href="{{ $keTab('riwayat') }}"><x-ikon n="RotateCcw" :s="13" /> Reset filter</a>
  @endif
  <span class="dm-hitung" aria-live="polite">{{ $jumlahRiwayat }} dari {{ $totalRiwayat }} perubahan</span>
</form>

@if($riwayat->count())
  <div class="dm-tabel dm-admin-log" tabindex="0" role="region" aria-label="Log admin, geser untuk melihat semua kolom">
    <table>
      <thead>@include('data-master.log-kolom')</thead>
      <tbody>
        @foreach($riwayat as $x)
          @include('bagian.jejak-administrasi', ['x' => $x])
        @endforeach
      </tbody>
    </table>
  </div>
@else
  <div class="kosong dm-kosong-kotak">{{ $totalRiwayat > 0 ? 'Tidak ada perubahan yang cocok.' : 'Belum ada perubahan data master atau hak akses.' }}</div>
@endif
@if($jumlahRiwayat > $riwayat->count())
  <div class="dm-lagi">
    <a class="btn" href="{{ $ke(['batas' => $batas + 30]) }}">
      Tampilkan lebih banyak ({{ $jumlahRiwayat - $riwayat->count() }} lagi)
    </a>
  </div>
@endif
