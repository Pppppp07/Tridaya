{{-- Nilai per tahun laporan — rincian kartu Sisa nilai, bentuknya sama
     dengan "Nilai per satuan kerja". Klik batangnya memfilter tahun itu. --}}
@php
  $kosongTahun = ['M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0, 'lhp' => 0, 'SS' => 0, 'BS' => 0, 'BT' => 0, 'TD' => 0, 'sisaBpk' => 0, 'tugas' => 0];
  $sisaTahun = array_map(fn ($th) => ($d['tahun'][$th] ?? $kosongTahun + ['k' => $th]), $tahunSemua);
  $sisaTahun = array_map(fn ($o) => $o + ['diakui' => max(0, $o['nilai'] - $o['sisa'])], $sisaTahun);
  $sisaTahun = array_values(array_filter($sisaTahun, fn ($o) => $o['nilai'] > 0));
  $maksSisaTahun = max(1, ...array_map(fn ($o) => $o['nilai'], $sisaTahun ?: [['nilai' => 0]]));
  $jumlahRp = fn (array $arr, string $kk) => array_sum(array_column($arr, $kk));
  $tabelOn = in_array('sisatahun', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-sisatahun" :kelas="$kelas" judul="Nilai per tahun laporan"
  info="Batang = nilai tindak lanjut tahun itu: hijau sudah diakui, kuning sisa. Angka di kanan = sisa."
  :kosong="! $sisaTahun ? 'Tidak ada nilai rupiah.' : null"
  kunci-tabel="sisatahun" :tabel="$tabelOn">
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Tahun'], ['nama' => 'Diakui', 'num' => true], ['nama' => 'Sisa', 'num' => true], ['nama' => 'Sisa SIPTL', 'num' => true]]"
      :baris="array_map(fn ($o) => [$o['k'], $rpk($o['diakui']), $rpk($o['sisa']), $rpk($o['sisaBpk'])], $sisaTahun)"
      :kaki="['Jumlah', $rpk($jumlahRp($sisaTahun, 'diakui')), $rpk($jumlahRp($sisaTahun, 'sisa')), $rpk($jumlahRp($sisaTahun, 'sisaBpk'))]" />
  </x-slot:isiTabel>

  <div class="dsb-hbatang">
    @foreach($sisaTahun as $o)
      @php $pilih = in_array((string) $o['k'], $f['tahun'], true); @endphp
      <button type="submit" name="ubah" value="{{ 'tahun:'.$o['k'].'@dsb-p-sisatahun' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
        class="dsb-hbaris tahun{{ $f['tahun'] && ! $pilih ? ' redup' : '' }}"
        aria-label="Tahun {{ $o['k'] }}: diakui {{ $rpk($o['diakui']) }}, sisa {{ $rpk($o['sisa']) }}"
        data-petunjuk="{{ $petunjuk(['judul' => 'Tahun '.$o['k'],
          'baris' => [['warna' => $W['M'], 'nilai' => $rpk($o['diakui']), 'nama' => 'diakui'], ['warna' => 'var(--warn)', 'nilai' => $rpk($o['sisa']), 'nama' => 'sisa']],
          'ket' => 'dari '.$rpk($o['nilai']).' · '.$o['BM'].' dari '.($o['M'] + $o['BM']).' tindak lanjut '.$kecil($kataBM)
            .($o['lhp'] ? ' · sisa SIPTL '.$rpk($o['sisaBpk']) : '')]) }}">
        <span class="nm">{{ $o['k'] }}</span>
        @include('ringkasan.panel.batang-nilai', ['o' => $o, 'maks' => $maksSisaTahun])
        <span class="n">@if($o['sisa'] > 0)<b>{{ $rpk($o['sisa']) }}</b>@else<span class="dsb-lunas">tanpa sisa</span>@endif</span>
      </button>
    @endforeach
  </div>
  <div class="dsb-kaki-panel">
    <div class="dsb-legenda">
      <span><i style="background:{{ $W['M'] }}"></i>Diakui</span>
      <span><i style="background:var(--warn)"></i>Sisa</span>
    </div>
  </div>
</x-dsb-panel>
