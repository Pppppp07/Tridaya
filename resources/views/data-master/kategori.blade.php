@php
  /* Tab Kategori & referensi: empat daftar dalam satu bentuk tabel (urutan, dipakai berapa,
     gabungkan, tambah banyak, ekspor). */
  $isi = [
    'temuan' => [
      'judul' => 'Kategori temuan', 'ket' => 'Pilihan kategori saat mencatat temuan, dipisah menurut sumber laporannya.',
      'info' => [
        'Klasifikasi temuan pada laporan pemeriksaan. Pilihannya dibedakan untuk LHP BPK dan LHA Inspektorat.',
        'Kategori aktif tersedia saat mencatat temuan baru. Menonaktifkan kategori tidak mengubah temuan yang sudah menggunakannya.',
      ],
      'tambah' => 'Tambah kategori',
    ],
    'intern' => [
      'judul' => 'Kategori internal', 'ket' => 'Pengelompokan temuan milik BPSDM sendiri.',
      'info' => [
        'Pengelompokan temuan untuk kebutuhan monitoring dan rekap internal BPSDM.',
        'Kategori ini dicatat terpisah dari kategori temuan pada laporan pemeriksaan.',
      ],
      'tambah' => 'Tambah kategori',
    ],
    'sifat' => [
      'judul' => 'Sifat rekomendasi', 'ket' => 'Pilihan sifat saat mencatat rekomendasi.',
      'info' => [
        'Jenis kewajiban pada rekomendasi, misalnya perbaikan administratif atau pemulihan nilai.',
        'Pengaturan pemulihan nilai menentukan apakah nilai kewajiban perlu dicatat untuk tiap Satker yang ditugaskan.',
      ],
      'tambah' => 'Tambah sifat',
    ],
    'bentuk' => [
      'judul' => 'Bentuk tindak lanjut', 'ket' => 'Pilihan bentuk saat mencatat tindak lanjut di Catat laporan baru.',
      'info' => [
        'Jenis tindakan yang dilakukan Satker untuk memenuhi rekomendasi.',
        'Dokumen usulan adalah daftar bukti awal untuk bentuk tersebut. Daftar ini terisi saat bentuk dipilih dan dapat disesuaikan dengan penugasannya.',
      ],
      'tambah' => 'Tambah bentuk',
    ],
  ][$tab];

  $butir = match ($tab) {
    'temuan' => collect($kategoriTemuan[$f['sumber']] ?? [])->values(),
    'intern' => $kategori->values(),
    'sifat'  => $sifat->values(),
    'bentuk' => $bentuk->values(),
  };
  $pakai = fn ($x) => (int) match ($tab) {
    'temuan' => $terpakaiTemuan[$x->id] ?? 0,
    'intern' => $terpakai[$x->id] ?? 0,
    'sifat'  => $pakaiSifat[$x->id] ?? 0,
    'bentuk' => $pakaiBentuk[$x->id] ?? 0,
  };
  $idBaris = fn ($x) => match ($tab) { 'temuan' => 'kt-', 'intern' => 'kat-', 'sifat' => 'sifat-', 'bentuk' => 'bentuk-' }.$x->id;
  $rutePaket = match ($tab) {
    'temuan' => ['saklar' => 'master.temuan.saklar'],
    'intern' => ['saklar' => 'master.saklar'],
    'sifat'  => ['saklar' => 'master.sifat.saklar'],
    'bentuk' => ['saklar' => 'master.bentuk.saklar'],
  };
  $dipakaiSemua = $butir->map(fn ($x) => $pakai($x))->all();
  $terbanyak = max(1, ...($dipakaiSemua ?: [1]));
  /* Sifat dan bentuk mengisi formulir, jadi yang aktif terakhir tidak bisa dinonaktifkan. */
  $jagaTerakhir = in_array($tab, ['sifat', 'bentuk'], true);
  $aktifN = $butir->where('aktif', true)->count();
  $namaKolom = ['sifat' => 'Nama sifat', 'bentuk' => 'Nama bentuk'][$tab] ?? 'Nama kategori';
  $kolomPakai = ['sifat' => 'Rekomendasi', 'bentuk' => 'Tindak lanjut'][$tab] ?? 'Dipakai temuan';
  $satuanPakai = ['sifat' => 'rekomendasi', 'bentuk' => 'tindak lanjut'][$tab] ?? 'temuan';
  $sebutButir = $tab === 'bentuk' ? 'bentuk' : 'kategori';
  $sumberUnduh = $tab === 'temuan' ? ['filter' => $f['sumber']] : [];
  $sumberBaru = $tab === 'temuan' ? ['sumber' => $f['sumber']] : [];
@endphp
<x-dm-kepala :judul="$isi['judul']" :ket="$isi['ket']" :info="$isi['info']">
  <details class="dm-tools">
    <summary class="btn"><x-ikon n="SlidersHorizontal" :s="14" /> Opsi lainnya</summary>
    <div class="dm-tools-isi">
      <a href="{{ route('master.daftar.unduh', [$tab] + $sumberUnduh) }}" download><x-ikon n="Download" :s="14" /> Ekspor CSV</a>
      <a href="{{ $ke(['jendela' => 'banyak-'.$tab]) }}"><x-ikon n="ListPlus" :s="14" /> Tambah beberapa</a>
    </div>
  </details>
  <a class="btn btn-p" href="{{ $ke(['jendela' => $tab] + $sumberBaru) }}"><x-ikon n="Plus" :s="14" /> {{ $isi['tambah'] }}</a>
</x-dm-kepala>

<div class="dm-filter" role="group" aria-label="Daftar kategori">
  @foreach([
    ['temuan', 'LHP', 'Temuan LHP', count($kategoriTemuan['LHP'] ?? [])],
    ['temuan', 'LHA', 'Temuan LHA', count($kategoriTemuan['LHA'] ?? [])],
    ['intern', null, 'Kategori internal', $kategori->count()],
    ['sifat', null, 'Sifat rekomendasi', $sifat->count()],
    ['bentuk', null, 'Bentuk tindak lanjut', $bentuk->count()],
  ] as [$sub, $seg, $namaChip, $jml])
    <a href="{{ $keTab($sub, $seg ? ['filter' => $seg] : []) }}"
      aria-pressed="{{ $tab === $sub && (! $seg || $f['sumber'] === $seg) ? 'true' : 'false' }}">{{ $namaChip }} <span class="dm-n">{{ $jml }}</span></a>
  @endforeach
</div>

<div class="dm-tabel">
  <table>
    <thead><tr>
      <th class="dm-urutan"><span class="sr-only">Urutan</span></th>
      <th>{{ $namaKolom }}</th>
      @if($tab === 'sifat')<th>Menuntut penyetoran</th>@endif
      @if($tab === 'bentuk')<th>Dokumen usulan</th>@endif
      <th class="num">{{ $kolomPakai }}</th>
      <th>Status</th><th><span class="sr-only">Tindakan</span></th>
    </tr></thead>
    <tbody>
      @foreach($butir as $i => $x)
        @php
          $n = $pakai($x);
          $terakhir = $jagaTerakhir && $x->aktif && $aktifN === 1;
          $adaTujuan = $butir->contains(fn ($y) => $y->aktif && $y->id !== $x->id && ($tab !== 'sifat' || (bool) $y->perlu_nilai === (bool) $x->perlu_nilai));
          $pastikan = ['judul' => 'Nonaktifkan '.$sebutButir.' ini?',
            'ket' => $n.' '.$satuanPakai.' masih memakainya. Keterangannya tetap terbaca, tapi '.$sebutButir.' ini tidak lagi muncul saat mencatat laporan baru.',
            'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false];
          $dok = (array) ($x->dokumen ?? []);
        @endphp
        <tr id="{{ $idBaris($x) }}" @class(['mati' => ! $x->aktif, 'disorot' => $sorot === $idBaris($x)])>
          <td class="dm-urutan" data-label="Urutan">
            <form class="dm-geser" method="post" action="{{ route('master.urut', [$tab, $x->id]) }}">
              @csrf
              <button type="submit" name="arah" value="atas" class="dm-ikon-btn" @disabled($i === 0) aria-label="Naikkan {{ $x->nama }}" title="Naikkan"><x-ikon n="ArrowUp" :s="13" /></button>
              <button type="submit" name="arah" value="bawah" class="dm-ikon-btn" @disabled($i === $butir->count() - 1) aria-label="Turunkan {{ $x->nama }}" title="Turunkan"><x-ikon n="ArrowDown" :s="13" /></button>
            </form>
          </td>
          <td class="dm-nama" data-label="{{ $namaKolom }}">
            <b>{{ $x->nama }}@if($n > 0) @include('data-master.kunci', ['ket' => $tab === 'sifat' ? 'Sudah dipakai rekomendasi — tidak bisa diubah' : 'Sudah dipakai '.$satuanPakai.' — nama tidak bisa diganti']) @endif</b>
          </td>
          @if($tab === 'sifat')<td data-label="Menuntut penyetoran">{{ $x->perlu_nilai ? 'Ya' : 'Tidak' }}</td>@endif
          @if($tab === 'bentuk')
            <td data-label="Dokumen usulan" @if($dok) title="{{ implode("\n", $dok) }}" @endif>{{ $dok ? count($dok).' dokumen' : '—' }}</td>
          @endif
          <td class="num" data-label="{{ $kolomPakai }}">
            <span class="dm-pakai"><span class="mono">{{ $n }}</span>
              <span class="dm-bar" aria-hidden="true"><i style="width: {{ round(($n / $terbanyak) * 100) }}%"></i></span></span>
          </td>
          <td data-label="Status">{!! $keadaan($x->aktif) !!}</td>
          <td class="dm-aksi">
            {{-- Bentuk yang sudah dipakai tetap bisa diubah dokumen usulannya. --}}
            @if($n === 0 || $tab === 'bentuk')
              <a class="btn btn-s" href="{{ $ke(['jendela' => $tab.'-'.$x->id]) }}"><x-ikon n="Pencil" :s="13" /> Ubah</a>
            @endif
            @if($adaTujuan)
              <a class="btn btn-s" href="{{ $ke(['jendela' => 'gabung-'.$tab.'-'.$x->id]) }}" aria-label="Gabungkan {{ $x->nama }} ke {{ $sebutButir }} lain"><x-ikon n="GitMerge" :s="13" /> Gabungkan</a>
            @endif
            <form method="post" action="{{ route($rutePaket['saklar'], $x) }}">
              @csrf
              <button class="btn btn-s" type="submit" @disabled($terakhir)
                @if($terakhir) title="Minimal satu {{ $tab === 'bentuk' ? 'bentuk' : 'sifat' }} harus tetap aktif" @endif
                @if($tab !== 'sifat' && $x->aktif && $n > 0) data-pastikan='@json($pastikan)' @endif>{{ $x->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
          </td>
        </tr>
      @endforeach
      @if($butir->isEmpty())
        <tr class="dm-kosong-baris"><td colspan="9" class="dm-kosong">Belum ada data. Tambahkan melalui tombol di atas.</td></tr>
      @endif
    </tbody>
  </table>
</div>
