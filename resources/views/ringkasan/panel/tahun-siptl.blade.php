{{-- Per tahun · SIPTL (hanya LHP yang sudah diunggah); yang belum selesai di dasar batang supaya
     sejajar di garis nol. --}}
@php
  use App\Support\Dasbor;

  $kosongTahun = ['M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0, 'lhp' => 0, 'siptl' => 0, 'SS' => 0, 'BS' => 0, 'BT' => 0, 'belumUnggah' => 0, 'sisaBpk' => 0, 'tugas' => 0];
  $barisTahun = array_map(fn ($th) => ($d['tahun'][$th] ?? $kosongTahun + ['k' => $th]), $tahunSemua);
  $nB = array_sum(array_column($barisTahun, 'siptl'));
  /* Belum diunggah di dasar batang: paling jauh dari selesai. */
  $belumTampil = array_sum(array_column($barisTahun, 'belumUnggah')) > 0;
  $tinggi = fn ($o) => $o['siptl'] + ($belumTampil ? $o['belumUnggah'] : 0);
  $nTampil = array_sum(array_map($tinggi, $barisTahun));
  $adaLhp = array_sum(array_column($barisTahun, 'lhp')) > 0;
  $jmlTahun = fn ($kk) => array_sum(array_column($barisTahun, $kk));
  $seg = $belumTampil ? ['belumUnggah', 'BT', 'BS', 'SS'] : ['BT', 'BS', 'SS'];
  $sebutSeg = fn ($x) => $x === 'belumUnggah' ? 'belum diunggah' : $x.' '.$kecil($STATUS($x));
  $sk = Dasbor::skala(max(1, ...array_map($tinggi, $barisTahun)), 3);
  $m = count($barisTahun);
  $tabelOn = in_array('tahunsiptl', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-tahunsiptl" :kelas="$kelas" judul="Per tahun · SIPTL" :info="['Jumlah penugasan LHP yang sudah diunggah ke SIPTL, dikelompokkan menurut tahun surat laporan dan status penilaian BPK.',
    'Belum diunggah: sudah memadai, tapi unggahannya ke SIPTL belum dicatat Setba.']"
  :kosong="! $nTampil ? ($adaLhp ? 'Belum ada data SIPTL pada pilihan ini.' : 'Tidak ada LHP pada pilihan ini.') : null"
  kunci-tabel="tahunsiptl" :tabel="$tabelOn">
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="array_merge([['nama' => 'Tahun'], ['nama' => 'Jumlah', 'num' => true], ['nama' => 'SS', 'num' => true], ['nama' => 'BS', 'num' => true], ['nama' => 'BT', 'num' => true]],
        $belumTampil ? [['nama' => 'Belum diunggah', 'num' => true]] : [], [['nama' => 'Sisa SIPTL', 'num' => true]])"
      :baris="array_map(fn ($o) => array_merge([$o['k'], $o['siptl'], $o['SS'], $o['BS'], $o['BT']], $belumTampil ? [$o['belumUnggah']] : [], [$rpk($o['sisaBpk'])]),
        array_values(array_filter($barisTahun, fn ($o) => $tinggi($o) > 0)))"
      :kaki="array_merge(['Jumlah', $nB, $jmlTahun('SS'), $jmlTahun('BS'), $jmlTahun('BT')], $belumTampil ? [$jmlTahun('belumUnggah')] : [], [$rpk($jmlTahun('sisaBpk'))])" />
  </x-slot:isiTabel>

  <div class="dsb-subgrafik">
    <div class="dsb-kolom">
      <div class="plot" style="--n:{{ $m }}">
        @foreach($sk['tanda'] as $tt)
          <span class="garis{{ $tt === 0 ? ' nol' : '' }}" style="bottom:{{ $tt / $sk['atas'] * 100 }}%"><em>{{ $tt }}</em></span>
        @endforeach
        @foreach($barisTahun as $o)
          @php
            $n = $tinggi($o);
            $pilih = in_array((string) $o['k'], $f['tahun'], true);
            $ada = array_values(array_filter($seg, fn ($x) => $o[$x] > 0));
          @endphp
          <button type="submit" name="ubah" value="{{ 'tahun:'.$o['k'].'@dsb-p-tahunsiptl' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
            class="dsb-pita{{ $f['tahun'] && ! $pilih ? ' redup' : '' }}" style="--h:{{ $n / $sk['atas'] * 100 }}%"
            aria-label="SIPTL, tahun {{ $o['k'] }}: {{ implode(', ', array_map(fn ($x) => $o[$x].' '.$sebutSeg($x), $ada)) ?: 'tidak ada tindak lanjut' }}"
            data-petunjuk="{{ $petunjuk(['judul' => 'Tahun '.$o['k'].' · SIPTL',
              'baris' => array_map(fn ($x) => ['warna' => $W[$x], 'nilai' => $o[$x], 'nama' => $sebutSeg($x)], $ada),
              'ket' => ! $n ? 'Tidak ada tindak lanjut di SIPTL' : $o['siptl'].' tindak lanjut di SIPTL'
                .($belumTampil && $o['belumUnggah'] ? ' · '.$o['belumUnggah'].' belum diunggah' : '').' · sisa SIPTL '.$rpk($o['sisaBpk'])]) }}">
            <span class="tumpuk">
              @foreach($ada as $x)
                <i @if($f['bpk'] && ! in_array($x, $f['bpk'], true)) class="redup" @endif style="flex-grow:{{ $o[$x] }};background:{{ $W[$x] }}"></i>
              @endforeach
            </span>
            @if($n > 0 && $m <= 12)<b class="dsb-cap">{{ $n }}</b>@endif
          </button>
        @endforeach
      </div>
      <div class="sumbux" style="--n:{{ $m }}" aria-hidden="true">
        {{-- Tahun banyak: label dijarangkan dari tahun terbaru (kelas `jarang`). --}}
        @foreach($barisTahun as $i => $o)
          @php
            $langkah = $m <= 12 ? 1 : (int) ceil($m / 12);
            $jauh = $m - 1 - $i;
            $tampil = $jauh % $langkah === 0;
            $jarang = $m > 6 && $jauh % ($langkah * 2) !== 0;
          @endphp
          <span @if($jarang) class="jarang" @endif>{{ $tampil ? ($m > 12 ? '’'.substr((string) $o['k'], 2) : $o['k']) : '' }}</span>
        @endforeach
      </div>
    </div>
    <div class="dsb-legenda">
      @foreach($seg as $x)
        <span><i style="background:{{ $W[$x] }}"></i>{{ $x === 'belumUnggah' ? 'Belum diunggah' : $x }}</span>
      @endforeach
    </div>
  </div>
</x-dsb-panel>
