@extends('rangka')
@section('judul', 'Log aktivitas')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;
  use App\Http\Controllers\LogController;
  use App\Support\Aktivitas;
  use App\Support\Tampil;

  /* Log aktivitas — padanan `LayarLog` prototipe (27 Sep). Bang Kamal: "DTI
     itu hanya untuk melihat jika dia merubah data, merusak data, kita tinggal
     nembak siapa pelakunya." Dua tab: Aktivitas dan Keaktifan akun. Hanya
     membaca. */
  $urut = ['setba', 'uki', 'inspektorat', 'pimpinan', 'dti', 'admin', 'satker'];
  $filter = array_filter(['rentang' => $f['rentang'] !== 'semua' ? $f['rentang'] : null, 'kelompok' => $f['kelompok'] ?: null,
    'peran' => $f['peran'] ?: null, 'q' => $f['q'] ?: null, 'akun' => $f['akun']]);
  $rinci = (int) request('rinci');
@endphp

<div class="body dm lg">
  <div class="tl-tabs dm-tabs" role="tablist" aria-label="Log aktivitas">
    <a role="tab" href="{{ route('log', $filter) }}" aria-selected="{{ $f['tab'] === 'aktivitas' ? 'true' : 'false' }}"
      @if($f['tab'] === 'aktivitas') aria-current="page" @endif><x-ikon n="History" :s="14" />Aktivitas</a>
    <a role="tab" href="{{ route('log', ['tab' => 'keaktifan']) }}" aria-selected="{{ $f['tab'] === 'keaktifan' ? 'true' : 'false' }}"
      @if($f['tab'] === 'keaktifan') aria-current="page" @endif><x-ikon n="Users" :s="14" />Keaktifan akun</a>
  </div>

  <section class="kartu dm-panel" role="tabpanel" aria-label="{{ $f['tab'] === 'aktivitas' ? 'Aktivitas' : 'Keaktifan akun' }}">
    @if($f['tab'] === 'aktivitas')
      <x-dm-kepala judul="Aktivitas" ket="Siapa melakukan apa, kapan, dan pada berkas mana." :info="[
        'Setiap perubahan tercatat: perpindahan berkas, laporan baru, Data master, hak akses, masuk dan keluar, termasuk percobaan yang ditolak.',
        'Log tidak bisa diubah atau dihapus oleh siapa pun. Hanya DTI dan Admin yang membukanya.',
        'Setiap baris juga mencatat alamat IP dan perangkatnya (buka Rincian).',
      ]">
        @if($log->total())
          <a class="btn" href="{{ route('log.unduh', $filter) }}"><x-ikon n="Download" :s="14" /> Unduh CSV</a>
        @else
          <span class="btn" aria-disabled="true"><x-ikon n="Download" :s="14" /> Unduh CSV</span>
        @endif
      </x-dm-kepala>
      {{-- Satu formulir GET: mengganti pilihan langsung mengirimnya (skrip),
           tanpa skrip lewat tombol Terapkan. --}}
      <form method="get" action="{{ route('log') }}" class="lg-filter" data-log-filter>
        <label class="dm-cari">
          <x-ikon n="Search" :s="14" />
          <input type="search" name="q" value="{{ $f['q'] }}" placeholder="Cari nama atau aktivitas" aria-label="Cari nama atau aktivitas">
        </label>
        <label class="lg-pilih"><span>Waktu</span>
          <select name="rentang" data-kirim-otomatis>
            @foreach($rentang as $k => $v)<option value="{{ $k }}" @selected($f['rentang'] === (string) $k)>{{ $v }}</option>@endforeach
          </select>
        </label>
        <label class="lg-pilih"><span>Kelompok</span>
          <select name="kelompok" data-kirim-otomatis>
            <option value="">Semua</option>
            @foreach(Aktivitas::KELOMPOK as $k => $v)<option value="{{ $k }}" @selected($f['kelompok'] === $k)>{{ $v }}</option>@endforeach
          </select>
        </label>
        <label class="lg-pilih"><span>Peran</span>
          <select name="peran" data-kirim-otomatis>
            <option value="">Semua</option>
            @foreach($urut as $k)<option value="{{ $k }}" @selected($f['peran'] === $k)>{{ P::from($k)->pendek() }}</option>@endforeach
          </select>
        </label>
        @if($f['akun'])<input type="hidden" name="akun" value="{{ $f['akun'] }}">@endif
        <button type="submit" class="btn" data-tanpa-js>Terapkan</button>
      </form>
      <div class="dm-ringkas"><span><b>{{ $log->total() }}</b> aktivitas</span></div>
      @if($log->count())
        <div class="dm-tabel">
          <table>
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Objek</th><th><span class="sr-only">Rincian</span></th></tr></thead>
            <tbody>
              @foreach($log as $x)
                @php
                  $tolak = str_contains($x->aksi, 'gagal') || str_contains($x->aksi, 'ditolak') || str_contains($x->aksi, 'dikunci');
                  $obj = $x->subjek_tipe ? ($subjek[$x->subjek_tipe.':'.$x->subjek_id] ?? null) : null;
                  $buka = $rinci === $x->id;
                @endphp
                <tr @class(['lg-tolak' => $tolak])>
                  <td class="dm-waktu" data-label="Waktu">{{ Tampil::waktuLog($x->waktu) }}</td>
                  <td data-label="Pengguna">
                    <span class="dm-orang">
                      <b>{{ $x->nama ?: 'Sistem (otomatis)' }}</b>
                      <span>{{ $x->peran ? $x->sebutanPeran().($x->peran === 'satker' && $x->satker ? ' · '.$x->satker->namaPendek() : '') : '—' }}</span>
                    </span>
                  </td>
                  <td data-label="Aktivitas"><span class="dm-ubah">{{ $x->ringkasan }}</span>@include('bagian.beda-log', ['rincian' => $x->rincian])</td>
                  <td data-label="Objek">
                    @if($obj && $obj['url'])
                      <a class="lg-objek" href="{{ $x->subjek_tipe === 'rekomendasi' ? $obj['url'].'?dari=log' : $obj['url'] }}">{{ $obj['teks'] }}</a>
                    @elseif($obj)
                      {{ $obj['teks'] }}
                    @else
                      <span class="dm-sub">—</span>
                    @endif
                  </td>
                  <td class="dm-aksi">
                    <a class="btn btn-s" href="{{ route('log', $filter + ['page' => $log->currentPage(), 'rinci' => $buka ? null : $x->id]) }}"
                      aria-expanded="{{ $buka ? 'true' : 'false' }}" aria-controls="lg-r-{{ $x->id }}" data-rinci-log>Rincian</a>
                  </td>
                </tr>
                <tr class="lg-rinci" id="lg-r-{{ $x->id }}" @unless($buka) hidden @endunless>
                  <td colspan="5">
                    {{-- x-fakta mencetak `v` sebagai HTML — semua isi dari luar
                         (perangkat = kepala User-Agent kiriman browser) wajib e(). --}}
                    <x-fakta :isi="array_values(array_filter([
                      ['l' => 'Waktu', 'v' => e(Tampil::waktuLog($x->waktu))],
                      ['l' => 'Kelompok', 'v' => e(Aktivitas::KELOMPOK[$x->kelompok] ?? $x->kelompok)],
                      ['l' => 'Kunci aksi', 'v' => e($x->aksi), 'mono' => true],
                      ['l' => 'Alamat IP', 'v' => e($x->ip ?: '—'), 'mono' => true],
                      ['l' => 'Perangkat', 'v' => e($x->agen ?: '—')],
                      ! empty($x->rincian['atas_nama']) ? ['l' => 'Atas nama', 'v' => e($x->rincian['atas_nama'])] : null,
                    ]))" />
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="kosong dm-kosong-kotak">Tidak ada aktivitas pada pilihan ini.</div>
      @endif
      @if($log->lastPage() > 1)
        <nav class="lg-halaman" aria-label="Halaman log">
          @if($log->onFirstPage())
            <span class="btn btn-s" aria-disabled="true"><x-ikon n="ChevronLeft" :s="13" /> Sebelumnya</span>
          @else
            <a class="btn btn-s" href="{{ $log->previousPageUrl() }}"><x-ikon n="ChevronLeft" :s="13" /> Sebelumnya</a>
          @endif
          <span>Halaman {{ $log->currentPage() }} dari {{ $log->lastPage() }}</span>
          @if($log->hasMorePages())
            <a class="btn btn-s" href="{{ $log->nextPageUrl() }}">Berikutnya <x-ikon n="ChevronRight" :s="13" /></a>
          @else
            <span class="btn btn-s" aria-disabled="true">Berikutnya <x-ikon n="ChevronRight" :s="13" /></span>
          @endif
        </nav>
      @endif
    @else
      <x-dm-kepala judul="Keaktifan akun" ket="Kapan setiap akun terakhir masuk dan beraktivitas. Yang paling lama tidak aktif di atas." :info="[
        'Ditandai kalau lebih dari '.LogController::HARI_DIAM.' hari tidak ada kegiatan — mungkin ada kendala di aplikasinya, atau penggunanya perlu dibantu.',
        'Aktivitas 30 hari tidak menghitung masuk dan keluar.',
      ]" />
      <div class="dm-tabel">
        <table>
          <thead><tr><th>Akun</th><th>Terakhir masuk</th><th>Terakhir beraktivitas</th><th class="num">Aktivitas 30 hari</th><th>Keadaan</th></tr></thead>
          <tbody>
            @foreach($keaktifan as $k)
              @php $a = $k->akun; @endphp
              <tr>
                <td data-label="Akun">
                  <span class="dm-orang"><b><a class="lg-objek" href="{{ route('log', ['akun' => $a->id]) }}" title="Lihat aktivitas akun ini">{{ $a->name }}</a></b>
                    <span>{{ $a->peran->pendek() }}{{ $a->peran === P::SATKER && $a->satker ? ' · '.$a->satker->namaPendek() : '' }}</span></span>
                </td>
                <td data-label="Terakhir masuk">@if($a->terakhir_masuk_pada){{ Tampil::waktuLog($a->terakhir_masuk_pada) }}@else<span class="dm-sub">Belum pernah</span>@endif</td>
                <td data-label="Terakhir beraktivitas">@if($k->terakhir_berbuat){{ Tampil::waktuLog($k->terakhir_berbuat) }}@else<span class="dm-sub">Belum ada</span>@endif</td>
                <td class="num mono" data-label="Aktivitas 30 hari">{{ $k->n30 }}</td>
                <td data-label="Keadaan">
                  @if($k->diam === null)
                    <span class="dm-keadaan diam">Belum pernah dipakai</span>
                  @elseif($k->diam > LogController::HARI_DIAM)
                    <span class="dm-keadaan diam">Diam {{ $k->diam }} hari</span>
                  @else
                    <span class="dm-keadaan aktif">Dipakai</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>
</div>
@endsection
