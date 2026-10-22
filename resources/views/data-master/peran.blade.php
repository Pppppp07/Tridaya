@php
  use App\Enums\PeranPengguna as P;
  use App\Support\PusatData;

  /* Tab Peran & akses: siapa boleh apa dan berapa akun aktif per peran. Hanya dibaca. */
@endphp
<x-dm-kepala judul="Panduan peran" ket="Hak akses tiap peran mengikuti alur kerja. Atur peran user melalui Daftar user." :info="[
  'Perbandingan halaman, tindakan, dan cakupan data yang tersedia untuk tiap peran.',
  'Jumlah akun aktif dihitung untuk setiap peran. User dengan beberapa peran dapat terhitung pada lebih dari satu baris.',
]" />
<div class="dm-tabel">
  <table>
    <thead><tr><th>Peran</th><th>Halaman yang bisa dibuka</th><th>Yang bisa dilakukan</th><th class="num">Akun aktif</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
    <tbody>
      @foreach(PusatData::URUT_PERAN as $k)
        @php $p = P::from($k); @endphp
        <tr>
          <td class="dm-nama" data-label="Peran">
            <b>{{ $p->pendek() }}</b><span>{{ $p->nama() }}</span>
            @if($p->hanyaMelihat())<span class="dm-sub"><x-ikon n="Eye" :s="12" /> Hanya melihat</span>@endif
          </td>
          <td data-label="Halaman yang bisa dibuka">
            <span class="dm-halaman">@foreach($p->halaman() as $h)<span>{{ $h }}</span>@endforeach</span>
            @if($p->terbatasSatker())<span class="dm-sub">Hanya data Satker yang ditetapkan</span>@endif
          </td>
          <td data-label="Yang bisa dilakukan">
            <ul class="dm-hak">@foreach($p->hak() as $h)<li>{{ $h }}</li>@endforeach</ul>
          </td>
          <td class="num" data-label="Akun aktif"><span class="mono">{{ $perAkun[$k] ?? 0 }}</span></td>
          <td class="dm-aksi">
            <a class="btn btn-s" href="{{ $keTab('pengguna', ['peran' => $k]) }}">Lihat akun <x-ikon n="ArrowRight" :s="13" /></a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
