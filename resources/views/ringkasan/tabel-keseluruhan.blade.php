{{-- Tabel keseluruhan bertingkat: tahun → satuan kerja → angka. --}}
@php
  $urut = $k['urut'];
  $rasio = fn ($b) => $b['M'] + $b['BM'] > 0 ? $b['M'] / ($b['M'] + $b['BM']) : -1;
  $pct = fn ($b) => (int) round(max(0, $rasio($b)) * 100);
  $kodeBpk = ['SS', 'BS', 'BT'];
  /* Kolom Belum diunggah hanya bila ada isinya; kolom SIPTL hanya untuk LHP. */
  $belumKol = $adaSiptl && $tk['total']['belumUnggah'] > 0;
  /* `lepas`: kolom tanpa kelompok di kepala tingkat pertama. `awal`: kolom pertama kelompok
     (bergaris kiri). */
  /* Satuan hitungnya tindak lanjut. Rekomendasi tidak ditampilkan: satu rekomendasi bisa dipikul
     beberapa satuan kerja, jadi angkanya tidak bisa dijumlah per baris. */
  $KOLOM = array_merge([
    ['k' => 'tugas', 'nama' => 'Tindak lanjut', 'lepas' => true],
    ['k' => 'M', 'nama' => $kataM, 'awal' => true],
    ['k' => 'BM', 'nama' => $kataBM],
    ['k' => 'pct', 'nama' => '% '.mb_strtolower($kataM)],
  ], $adaSiptl ? array_merge([
    /* Status SIPTL hanya dari yang sudah diunggah; Jumlah = SS + BS + BT. */
    ['k' => 'siptl', 'nama' => 'Jumlah', 'awal' => true],
  ], array_map(fn ($x) => ['k' => $x, 'nama' => $x], $kodeBpk), $belumKol ? [['k' => 'belumUnggah', 'nama' => 'Belum diunggah']] : []) : [], [
    ['k' => 'nilai', 'nama' => 'Nilai', 'awal' => true, 'rp' => true],
  ], $adaSiptl ? [['k' => 'sisaBpk', 'nama' => 'Sisa SIPTL', 'rp' => true]] : [], [
    ['k' => 'sisa', 'nama' => 'Sisa Inspektorat', 'rp' => true],
  ]);
  $GRUP = array_merge([['nama' => 'Status BPSDM', 'n' => 3, 'info' => $INFO_BPSDM]],
    $adaSiptl ? [['nama' => 'Status SIPTL', 'n' => 1 + count($kodeBpk) + ($belumKol ? 1 : 0), 'info' => $belumKol
      ? ['Jumlah = SS + BS + BT: tindak lanjut LHP yang sudah dicatat di SIPTL.',
        'Belum diunggah: sudah memadai, tapi unggahannya ke SIPTL belum dicatat Setba. Tidak termasuk Jumlah.']
      : null]] : [],
    [['nama' => 'Nilai (Rp)', 'n' => $adaSiptl ? 3 : 2]]);

  /* Urutan kolom angka: tahun menurut jumlahnya, satuan kerja menurut angkanya di dalam tahun
     itu. */
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
  /* Judul kolom = tombol pengurut: pertama terbesar dulu, lagi kebalikannya. */
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
          <th colspan="{{ $g['n'] }}" class="dsb-tk-grup awal-grup">{{ $g['nama'] }}@if(! empty($g['info'])) <x-info :teks="$g['info']" />@endif</th>
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
