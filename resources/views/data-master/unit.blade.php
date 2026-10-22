@php
  use App\Support\DirektoriIrm;
  use Illuminate\Support\Arr;

  /* Tab Satuan kerja: filter, urutan (PusatData::saringUnit), riwayat per unit, ekspor. */
  $paramUnduh = Arr::except($param, ['tab']);
  $pastikanNonaktif = fn ($u, $jalan) => ['judul' => 'Nonaktifkan '.$u->namaPendek().'?',
    'ket' => $jalan.' rekomendasi yang ditugaskan ke satuan kerja ini belum selesai. Pekerjaan lama dan izin masuk penanggung jawab tetap berlaku, tetapi satuan kerja ini tidak tersedia saat mencatat laporan baru.',
    'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false];
@endphp
<x-dm-kepala judul="Satuan kerja" ket="Kelola satuan kerja dan penanggung jawab tindak lanjutnya." :info="[
  'Daftar unit kerja yang dapat menerima penugasan tindak lanjut.',
  'Kode satker adalah kode resmi 6 angka, dipakai untuk mencocokkan data lama dan akun SSO.',
  'Penanggung jawab adalah user yang mengisi dan mengirim tindak lanjut untuk unit tersebut.',
  'Status aktif menentukan ketersediaan unit untuk penugasan baru. Menonaktifkan unit tidak menghentikan pekerjaan yang sudah ada atau izin login penanggung jawabnya.',
]">
  <form method="get" action="{{ route('master') }}" class="dm-cari" role="search" data-filter-baris="dm-t-unit">
    <input type="hidden" name="tab" value="unit">
    @foreach(['filter', 'urut', 'arah'] as $n)
      @if(isset($param[$n]))<input type="hidden" name="{{ $n }}" value="{{ $param[$n] }}">@endif
    @endforeach
    <x-ikon n="Search" :s="14" />
    <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari satuan kerja, kode, atau penanggung jawab" aria-label="Cari satuan kerja, kode satker, atau penanggung jawab">
  </form>
  <a class="btn" href="{{ route('master.unit.unduh', $paramUnduh) }}" download><x-ikon n="Download" :s="14" /> Ekspor CSV</a>
  <a class="btn btn-p" href="{{ $ke(['jendela' => 'unit']) }}"><x-ikon n="Plus" :s="14" /> Tambah satuan kerja</a>
</x-dm-kepala>

<div class="dm-filter" role="group" aria-label="Filter satuan kerja">
  @foreach([['semua', 'Semua'], ['aktif', 'Aktif'], ['nonaktif', 'Nonaktif'], ['tanpa-pj', 'Tanpa penanggung jawab']] as [$k, $nama])
    <a href="{{ $ke(['filter' => $k === 'semua' ? null : $k]) }}" aria-pressed="{{ $f['filter'] === $k ? 'true' : 'false' }}">{{ $nama }} <span class="dm-n">{{ $jmlUnit[$k] }}</span></a>
  @endforeach
</div>
<div class="dm-ringkas">
  <span><b>{{ $jmlUnit['aktif'] }}</b> aktif dari {{ $jmlUnit['semua'] }}</span>
  @if($tanpaPj > 0)
    <span class="awas"><x-ikon n="AlertTriangle" :s="13" /> {{ $tanpaPj }} belum punya penanggung jawab aktif</span>
  @else
    <span class="ok"><x-ikon n="CheckCircle2" :s="13" /> Semua satuan kerja aktif punya penanggung jawab aktif</span>
  @endif
</div>

<div class="dm-tabel">
  <table id="dm-t-unit">
    <thead><tr>
      {!! $kolomUrut('nama', 'Satuan kerja') !!}
      <th>Kode satker</th>
      <th>Penanggung jawab</th>
      {!! $kolomUrut('rekomendasi', 'Rekomendasi', 'num') !!}
      <th>Status</th><th><span class="sr-only">Tindakan</span></th>
    </tr></thead>
    <tbody>
      @foreach($unitTampil as $u)
        @php
          $pj = $u->penanggungJawab;
          $pakai = $pakaiUnit[$u->id] ?? null;
          $terkunci = isset($kunciUnit[$u->id]);
        @endphp
        <tr id="unit-{{ $u->id }}" data-cari="{{ $u->nama }} {{ $u->namaPendek() }} {{ $u->kode_satker }} {{ $pj?->name }} {{ $pj?->nip }}"
          @class(['mati' => ! $u->aktif, 'disorot' => $sorot === 'unit-'.$u->id])>
          <td class="dm-nama" data-label="Satuan kerja">
            <b>{{ $u->namaPendek() }}@if($terkunci) @include('data-master.kunci', ['ket' => 'Sudah dipakai berkas — nama tidak bisa diganti']) @endif</b>
            <span>{{ $u->nama }}</span>
          </td>
          <td class="mono" data-label="Kode satker">{{ $u->kode_satker ?? '—' }}</td>
          <td data-label="Penanggung jawab">
            <div class="dm-pj">
              @if($pj)
                <span class="dm-orang">
                  <b>{{ $pj->name }}</b>
                  <span class="mono">NIP {{ DirektoriIrm::nipTampil($pj->nip) }}</span>
                  <span>{{ $pj->email }}</span>
                </span>
              @else
                <span class="dm-belum"><x-ikon n="AlertTriangle" :s="13" /> Belum ada yang aktif</span>
              @endif
              <a class="btn btn-s{{ $pj ? '' : ' btn-p' }}" href="{{ route('master', ['pj' => $u->id]) }}"
                aria-label="{{ ($pj ? 'Ganti penanggung jawab ' : 'Pilih penanggung jawab ').$u->namaPendek() }}">{{ $pj ? 'Ganti' : 'Pilih dari IRM' }}</a>
            </div>
          </td>
          <td class="num" data-label="Rekomendasi">
            <span class="mono">{{ $pakai['rek'] ?? 0 }}</span>
            @if(($pakai['jalan'] ?? 0) > 0)<span class="dm-sub">{{ $pakai['jalan'] }} berjalan</span>@endif
          </td>
          <td data-label="Status">{!! $keadaan($u->aktif) !!}</td>
          <td class="dm-aksi">
            <a class="btn btn-s" href="{{ $ke(['jendela' => 'riwayat-'.$u->id]) }}" aria-label="Riwayat {{ $u->namaPendek() }}"><x-ikon n="History" :s="13" /> Riwayat</a>
            <a class="btn btn-s" href="{{ $ke(['jendela' => 'unit-'.$u->id]) }}"><x-ikon n="Pencil" :s="13" /> Ubah</a>
            <form method="post" action="{{ route('master.unit.saklar', $u) }}">
              @csrf
              <input type="hidden" name="kembali" value="{{ $kembali }}">
              <button class="btn btn-s" type="submit"
                @if($u->aktif && ($pakai['jalan'] ?? 0) > 0) data-pastikan='@json($pastikanNonaktif($u, $pakai['jalan']))' @endif>{{ $u->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
          </td>
        </tr>
      @endforeach
      {!! $kosongCari($unitTampil->count()) !!}
      @if($unitTampil->isEmpty() && $cari === '')
        <tr class="dm-kosong-baris"><td colspan="9" class="dm-kosong">Tidak ada satuan kerja yang sesuai dengan filter.</td></tr>
      @endif
    </tbody>
  </table>
</div>
