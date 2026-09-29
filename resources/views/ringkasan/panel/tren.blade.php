{{-- Per bulan — padanan `panelTren`. Pilihan rentang: 12 bulan terakhir atau
     satu tahun kalender yang ada di data (Hizkia, 24 Sep: "tidak hanya
     berdasarkan 12 bulan terakhir"). Garis silangnya mencari bulannya sendiri
     (skrip): pembaca cukup menunjuk ke arah bulan. --}}
@php
  use App\Support\Dasbor;

  $namaPeriode = $periode === '12' ? '12 bulan terakhir' : 'tahun '.$periode;
  $jmlMasuk = array_sum($d['tren']['masuk']);
  $jmlSelesai = array_sum($d['tren']['selesai']);
  $seri = [
    ['k' => 'masuk', 'nama' => 'masuk', 'warna' => $W['masuk'], 'nilai' => $d['tren']['masuk']],
    ['k' => 'selesai', 'nama' => $kecil($kataM), 'warna' => $W['M'], 'nilai' => $d['tren']['selesai']],
  ];
  $n = count($bulanTren);
  $sk = Dasbor::skala(max(1, ...array_merge(...array_column($seri, 'nilai'))), 3);
  $X = fn ($i) => $n > 1 ? $i / ($n - 1) * 100 : 50;
  $Y = fn ($v) => (1 - $v / $sk['atas']) * 100;
  $tabelOn = in_array('tren', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-tren" :kelas="$kelas" judul="Per bulan"
  :info="['Masuk: tindak lanjut dari laporan yang diterima bulan itu.', $kataM.': dinyatakan '.$kecil($kataM).' oleh Inspektorat bulan itu.']"
  kunci-tabel="tren" :tabel="$tabelOn">
  <x-slot:alat>
    <label class="dsb-periode">
      <span class="dsb-sr">Rentang waktu</span>
      <select name="periode" data-kirim-otomatis>
        <option value="12" @selected($periode === '12')>12 bulan terakhir</option>
        @foreach($tahunTren as $th)
          <option value="{{ $th }}" @selected($periode === $th)>Tahun {{ $th }}</option>
        @endforeach
      </select>
    </label>
    <button type="submit" class="btn btn-s" data-tanpa-js>Terapkan</button>
  </x-slot:alat>
  <x-slot:wawasan>
    @if($jmlMasuk || $jmlSelesai)<b>{{ $jmlMasuk }}</b> masuk, <b>{{ $jmlSelesai }}</b> {{ $kecil($kataM) }}.@else Tidak ada yang masuk atau {{ $kecil($kataM) }} pada {{ $namaPeriode }}.@endif
  </x-slot:wawasan>
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Bulan'], ['nama' => 'Masuk', 'num' => true], ['nama' => $kataM, 'num' => true]]"
      :baris="array_map(fn ($b, $i) => [Dasbor::NAMA_BULAN[$b['bl']].' '.$b['th'], $d['tren']['masuk'][$i], $d['tren']['selesai'][$i]], $bulanTren, array_keys($bulanTren))"
      :kaki="['Jumlah', $jmlMasuk, $jmlSelesai]" />
  </x-slot:isiTabel>

  <div class="dsb-garis" tabindex="0" data-garis-tren
    aria-label="Grafik garis per bulan, {{ $namaPeriode }}: {{ $jmlMasuk }} tindak lanjut masuk, {{ $jmlSelesai }} {{ $kecil($kataM) }}. Tekan panah kiri dan kanan untuk membaca tiap bulan."
    data-bulan="{{ json_encode(array_map(fn ($b) => Dasbor::NAMA_BULAN[$b['bl']].' '.$b['th'], $bulanTren), JSON_UNESCAPED_UNICODE) }}"
    data-seri="{{ json_encode(array_map(fn ($s) => ['nama' => $s['nama'], 'warna' => $s['warna'], 'nilai' => $s['nilai']], $seri), JSON_UNESCAPED_UNICODE) }}">
    <div class="plot">
      @foreach($sk['tanda'] as $tt)
        <span class="garis{{ $tt === 0 ? ' nol' : '' }}" style="top:{{ $Y($tt) }}%"><em>{{ $tt }}</em></span>
      @endforeach
      <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        @foreach($seri as $s)
          <polyline style="stroke:{{ $s['warna'] }}" points="{{ implode(' ', array_map(fn ($v, $i) => $X($i).','.$Y($v), $s['nilai'], array_keys($s['nilai']))) }}" />
        @endforeach
      </svg>
      <span class="silang" hidden></span>
      @foreach($seri as $s)
        @foreach($s['nilai'] as $i => $v)
          <span class="titik" data-i="{{ $i }}" style="left:{{ $X($i) }}%;top:{{ $Y($v) }}%;background:{{ $s['warna'] }}"></span>
        @endforeach
      @endforeach
    </div>
    <div class="sumbux" aria-hidden="true">
      @foreach($bulanTren as $i => $b)
        <span data-i="{{ $i }}" style="left:{{ $X($i) }}%">{{ mb_substr(Dasbor::NAMA_BULAN[$b['bl']], 0, 3) }}{{ $i === 0 || $b['bl'] === 0 ? ' ’'.substr((string) $b['th'], 2) : '' }}</span>
      @endforeach
    </div>
  </div>
  <div class="dsb-legenda">
    <span><i class="garis" style="background:{{ $W['masuk'] }}"></i>Masuk</span>
    <span><i class="garis" style="background:{{ $W['M'] }}"></i>{{ $kataM }}</span>
  </div>
</x-dsb-panel>
