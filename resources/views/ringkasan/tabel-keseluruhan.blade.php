{{-- Tabel keseluruhan — padanan `TabelKeseluruhan`: SATU tabel bertingkat,
     tahun lalu satuan kerja lalu angka-angkanya. Hizkia (24 Sep): "informasi
     tertinggi nya ada di tahun, lalu kedua Satuan kerja". Tiap tahun punya
     baris ringkas di atas baris satuan kerjanya dan bisa ditutup (awalnya
     tertutup semua). Semua hitungan status per TINDAK LANJUT, supaya baris
     satuan kerja menjumlah tepat ke baris tahunnya; kolom Rekomendasi
     menghitung rekomendasi unik. Angka yang ada isinya sekaligus pintasan ke
     rekomendasi di baliknya. --}}
@php
  $urut = $k['urut'];
  $rasio = fn ($b) => $b['M'] + $b['BM'] > 0 ? $b['M'] / ($b['M'] + $b['BM']) : -1;
  $pct = fn ($b) => (int) round(max(0, $rasio($b)) * 100);
  $kodeBpk = array_merge(['SS', 'BS', 'BT'], $tk['adaTD'] ? ['TD'] : []);
  /* `lepas`: kolom tanpa kelompok di kepala tingkat pertama. `awal`: kolom
     pertama sebuah kelompok (bergaris kiri). */
  $KOLOM = array_merge([
    ['k' => 'rek', 'nama' => 'Rekomendasi', 'lepas' => true],
    ['k' => 'tugas', 'nama' => 'Tindak lanjut', 'lepas' => true],
    ['k' => 'M', 'nama' => $kataM, 'awal' => true],
    ['k' => 'BM', 'nama' => $kataBM],
    ['k' => 'pct', 'nama' => '% '.mb_strtolower($kataM)],
    ['k' => 'lhp', 'nama' => 'LHP', 'awal' => true],
  ], array_map(fn ($x) => ['k' => $x, 'nama' => $x], $kodeBpk), [
    ['k' => 'nilai', 'nama' => 'Nilai', 'awal' => true, 'rp' => true],
    ['k' => 'sisaBpk', 'nama' => 'Sisa SIPTL', 'rp' => true],
    ['k' => 'sisa', 'nama' => 'Sisa Inspektorat', 'rp' => true],
  ]);
  $GRUP = [['nama' => 'Status BPSDM', 'n' => 3], ['nama' => 'Status SIPTL', 'n' => 1 + count($kodeBpk)], ['nama' => 'Nilai (Rp)', 'n' => 3]];

  /* Urutan. Kolom angka: tahun menurut jumlah tahunnya, satuan kerja menurut
     angkanya sendiri di dalam tahun itu. Pengurut tetap: belum memadai
     terbanyak, sisa terbesar, lalu urutan alami. */
  $nilai = fn ($b, $kk) => $kk === 'pct' ? $rasio($b) : ($b[$kk] ?? 0);
  $master = fn ($a, $b) => ($a['urutNama'] <=> $b['urutNama']) ?: strnatcasecmp((string) $a['nama'], (string) $b['nama']);
  $bandingAnak = function ($a, $b) use ($urut, $nilai, $master) {
    $c = $urut['k'] === 'nama' ? $master($a, $b) : ($urut['k'] === 'tahun' ? 0 : $nilai($a, $urut['k']) <=> $nilai($b, $urut['k']));
    return ($c ? $urut['arah'] * $c : 0) ?: ($b['BM'] <=> $a['BM']) ?: ($b['sisa'] <=> $a['sisa']) ?: $master($a, $b);
  };
  $bandingTahun = function ($a, $b) use ($urut, $nilai) {
    $c = $urut['k'] === 'tahun' ? ((int) $a['k'] <=> (int) $b['k'])
      : ($urut['k'] === 'nama' ? 0 : $nilai($a['jumlah'], $urut['k']) <=> $nilai($b['jumlah'], $urut['k']));
    return ($c ? $urut['arah'] * $c : 0) ?: ((int) $b['k'] <=> (int) $a['k']);
  };
  $kelompokTk = $tk['kelompok'];
  usort($kelompokTk, $bandingTahun);
  $kelasSel = fn ($x) => trim(($x['k'] === 'pct' ? 'pct' : 'num').(! empty($x['awal']) ? ' awal-grup' : '').($urut['k'] === $x['k'] ? ' aktif' : ''));
@endphp
@php
  /* Judul kolom = tombol pengurut. Klik pertama: terbesar dulu (tahun:
     terbaru dulu; satuan kerja: urutan data master); klik lagi: kebalikannya. */
  $kepala = function ($kk, $isi, $kelas, $rentang = null) use ($urut) {
    $aktif = $urut['k'] === $kk;
    return ['k' => $kk, 'isi' => $isi, 'kelas' => $kelas, 'rentang' => $rentang, 'aktif' => $aktif];
  };
  $kepalaAtas = array_merge(
    [$kepala('tahun', 'Tahun', 'kol-tahun', 2), $kepala('nama', 'Satuan kerja', 'nama', 2)],
    array_map(fn ($x) => $kepala($x['k'], $x['nama'], 'num', 2), array_values(array_filter($KOLOM, fn ($x) => ! empty($x['lepas']))))
  );
  $kepalaBawah = array_map(fn ($x) => $kepala($x['k'], $x['nama'], trim(($x['k'] !== 'pct' ? 'num' : '').(! empty($x['awal']) ? ' awal-grup' : ''))),
    array_values(array_filter($KOLOM, fn ($x) => empty($x['lepas']))));
@endphp
<div class="dsb-tk-wadah">
  <table class="dsb-tk">
    <thead>
      <tr>
        @foreach($kepalaAtas as $h)
          @include('ringkasan.tk-kepala', ['h' => $h])
        @endforeach
        @foreach($GRUP as $g)
          <th colspan="{{ $g['n'] }}" class="dsb-tk-grup awal-grup">{{ $g['nama'] }}</th>
        @endforeach
      </tr>
      <tr>
        @foreach($kepalaBawah as $h)
          @include('ringkasan.tk-kepala', ['h' => $h])
        @endforeach
      </tr>
    </thead>
    @foreach($kelompokTk as $g)
      @php
        $terbuka = in_array((string) $g['k'], $k['buka'], true);
        $anak = $g['baris'];
        usort($anak, $bandingAnak);
      @endphp
      <tbody>
        <tr class="tahun">
          <th scope="rowgroup" class="kol-tahun{{ $urut['k'] === 'tahun' ? ' aktif' : '' }}">
            <button type="submit" name="ubah" value="{{ 'buka:'.$g['k'] }}" class="dsb-tk-lipat" aria-expanded="{{ $terbuka ? 'true' : 'false' }}"
              title="{{ $terbuka ? 'Tutup satuan kerjanya' : 'Buka satuan kerjanya' }}">
              <x-ikon n="ChevronDown" :s="14" />{{ $g['k'] }}
            </button>
          </th>
          <td class="nama">{{ count($g['baris']) }} satuan kerja</td>
          @include('ringkasan.tk-sel', ['b' => $g['jumlah'], 'letak' => ['tahun' => (string) $g['k'], 'satker' => '', 'label' => 'tahun '.$g['k']]])
        </tr>
        @if($terbuka)
          @foreach($anak as $b)
            <tr>
              <td class="kol-tahun" aria-hidden="true"></td>
              <th scope="row" class="nama{{ $urut['k'] === 'nama' ? ' aktif' : '' }}">{{ $b['nama'] }}</th>
              @include('ringkasan.tk-sel', ['b' => $b, 'letak' => ['tahun' => (string) $g['k'], 'satker' => (string) $b['k'], 'label' => $b['nama'].', '.$g['k']]])
            </tr>
          @endforeach
        @endif
      </tbody>
    @endforeach
    <tfoot>
      <tr class="jumlah">
        <th scope="row" colspan="2" class="kol-jumlah">Jumlah seluruhnya</th>
        @include('ringkasan.tk-sel', ['b' => $tk['total'], 'letak' => ['tahun' => '', 'satker' => '', 'label' => 'semua tahun']])
      </tr>
    </tfoot>
  </table>
</div>
