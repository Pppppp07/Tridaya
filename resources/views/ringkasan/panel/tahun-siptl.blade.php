{{-- Per tahun · SIPTL — rincian kartu SS di SIPTL: satu grafik kolom per
     tahun surat pemeriksaan, hanya LHP. Yang belum selesai di dasar batang,
     supaya tumpukannya sejajar di garis nol dan bisa dibandingkan antartahun.
     Klik batangnya memfilter tahun itu. --}}
@php
  use App\Support\Dasbor;

  $kosongTahun = ['M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0, 'lhp' => 0, 'SS' => 0, 'BS' => 0, 'BT' => 0, 'TD' => 0, 'sisaBpk' => 0, 'tugas' => 0];
  $barisTahun = array_map(fn ($th) => ($d['tahun'][$th] ?? $kosongTahun + ['k' => $th]), $tahunSemua);
  $nB = array_sum(array_column($barisTahun, 'lhp'));
  $jmlTahun = fn ($kk) => array_sum(array_column($barisTahun, $kk));
  $adaTD = collect($barisTahun)->contains(fn ($o) => $o['TD'] > 0);
  $seg = ['BT', 'BS', 'SS', 'TD'];
  $sebutSeg = fn ($x) => $x.' '.$kecil($STATUS($x));
  $sk = Dasbor::skala(max(1, ...array_map(fn ($o) => $o['lhp'], $barisTahun)), 3);
  $m = count($barisTahun);
  $tabelOn = in_array('tahunsiptl', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-tahunsiptl" :kelas="$kelas" judul="Per tahun · SIPTL" info="Tahun surat pemeriksaannya. Hanya LHP."
  :kosong="! $nB ? 'Tidak ada LHP pada pilihan ini.' : null"
  kunci-tabel="tahunsiptl" :tabel="$tabelOn">
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="array_merge([['nama' => 'Tahun'], ['nama' => 'LHP', 'num' => true], ['nama' => 'SS', 'num' => true], ['nama' => 'BS', 'num' => true], ['nama' => 'BT', 'num' => true]],
        $adaTD ? [['nama' => 'TD', 'num' => true]] : [], [['nama' => 'Sisa SIPTL', 'num' => true]])"
      :baris="array_map(fn ($o) => array_merge([$o['k'], $o['lhp'], $o['SS'], $o['BS'], $o['BT']], $adaTD ? [$o['TD']] : [], [$rpk($o['sisaBpk'])]),
        array_values(array_filter($barisTahun, fn ($o) => $o['lhp'] > 0)))"
      :kaki="array_merge(['Jumlah', $nB, $jmlTahun('SS'), $jmlTahun('BS'), $jmlTahun('BT')], $adaTD ? [$jmlTahun('TD')] : [], [$rpk($jmlTahun('sisaBpk'))])" />
  </x-slot:isiTabel>

  <div class="dsb-subgrafik">
    <div class="dsb-kolom">
      <div class="plot" style="--n:{{ $m }}">
        @foreach($sk['tanda'] as $tt)
          <span class="garis{{ $tt === 0 ? ' nol' : '' }}" style="bottom:{{ $tt / $sk['atas'] * 100 }}%"><em>{{ $tt }}</em></span>
        @endforeach
        @foreach($barisTahun as $o)
          @php
            $n = $o['lhp'];
            $pilih = in_array((string) $o['k'], $f['tahun'], true);
            $ada = array_values(array_filter($seg, fn ($x) => $o[$x] > 0));
          @endphp
          <button type="submit" name="ubah" value="{{ 'tahun:'.$o['k'].'@dsb-p-tahunsiptl' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
            class="dsb-pita{{ $f['tahun'] && ! $pilih ? ' redup' : '' }}" style="--h:{{ $n / $sk['atas'] * 100 }}%"
            aria-label="SIPTL, tahun {{ $o['k'] }}: {{ implode(', ', array_map(fn ($x) => $o[$x].' '.$sebutSeg($x), $ada)) ?: 'tidak ada tindak lanjut' }}"
            data-petunjuk="{{ $petunjuk(['judul' => 'Tahun '.$o['k'].' · SIPTL',
              'baris' => array_map(fn ($x) => ['warna' => $W[$x], 'nilai' => $o[$x], 'nama' => $sebutSeg($x)], $ada),
              'ket' => ! $n ? 'Tidak ada tindak lanjut LHP' : $o['lhp'].' tindak lanjut LHP · sisa SIPTL '.$rpk($o['sisaBpk'])]) }}">
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
        {{-- Tahun yang banyak: label dijarangkan dari tahun terbaru, dan di
             grafik sempit dijarangkan sekali lagi (kelas `jarang`). --}}
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
      @foreach(array_filter($seg, fn ($x) => $x !== 'TD' || $adaTD) as $x)
        <span><i style="background:{{ $W[$x] }}"></i>{{ $x }}</span>
      @endforeach
    </div>
  </div>
</x-dsb-panel>
