{{-- Nilai per satuan kerja — rincian kartu Sisa nilai. Batangnya bertumpuk
     seperti pita di kartunya: hijau yang sudah DIAKUI, kuning SISA-nya
     (Hizkia, 24 Sep: "nilai yang diakui tidak ada visualisasinya"). Panjang
     batang = nilai tindak lanjut baris itu; urut sisa terbesar dulu, dan
     angka di kanan sisanya. --}}
@php
  $lengkap = in_array('sisasatker', $k['semua'], true);
  $sisaSatker = array_map(fn ($o) => $o + ['diakui' => max(0, $o['nilai'] - $o['sisa'])], $d['satker']);
  $sisaSatker = array_values(array_filter($sisaSatker, fn ($o) => $o['nilai'] > 0));
  usort($sisaSatker, fn ($a, $b) => [$b['sisa'], $b['diakui']] <=> [$a['sisa'], $a['diakui']]);
  $barisSisa = $lengkap ? $sisaSatker
    : array_values(array_filter($sisaSatker, fn ($o, $i) => $i < $TERATAS || in_array($o['k'], $f['satker'], true), ARRAY_FILTER_USE_BOTH));
  $maksSisa = max(1, ...array_map(fn ($o) => $o['nilai'], $sisaSatker ?: [['nilai' => 0]]));
  $jumlahRp = fn (array $arr, string $kk) => array_sum(array_column($arr, $kk));
  $tabelOn = in_array('sisasatker', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-sisasatker" :kelas="$kelas" judul="Nilai per satuan kerja"
  :info="['Batang = nilai tindak lanjut satuan kerja itu: hijau sudah diakui, kuning sisa.', 'Angka di kanan = sisa. Urut dari sisa terbesar.']"
  :kosong="! $sisaSatker ? 'Tidak ada nilai rupiah.' : null"
  kunci-tabel="sisasatker" :tabel="$tabelOn">
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Satuan kerja'], ['nama' => 'Diakui', 'num' => true], ['nama' => 'Sisa', 'num' => true], ['nama' => 'Sisa SIPTL', 'num' => true]]"
      :baris="array_map(fn ($o) => [$o['pendek'], $rpk($o['diakui']), $rpk($o['sisa']), $rpk($o['sisaBpk'])], $sisaSatker)"
      :kaki="['Jumlah', $rpk($jumlahRp($sisaSatker, 'diakui')), $rpk($jumlahRp($sisaSatker, 'sisa')), $rpk($jumlahRp($sisaSatker, 'sisaBpk'))]" />
  </x-slot:isiTabel>

  <div class="dsb-hbatang{{ $lengkap && count($barisSisa) > 14 ? ' gulir' : '' }}">
    @foreach($barisSisa as $o)
      @php $pilih = in_array($o['k'], $f['satker'], true); @endphp
      <button type="submit" name="ubah" value="{{ 'satker:'.$o['k'].'@dsb-p-sisasatker' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
        class="dsb-hbaris{{ $f['satker'] && ! $pilih ? ' redup' : '' }}"
        aria-label="{{ $o['pendek'] }}: diakui {{ $rpk($o['diakui']) }}, sisa {{ $rpk($o['sisa']) }}"
        data-petunjuk="{{ $petunjuk(['judul' => $o['pendek'],
          'baris' => [['warna' => $W['M'], 'nilai' => $rpk($o['diakui']), 'nama' => 'diakui'], ['warna' => 'var(--warn)', 'nilai' => $rpk($o['sisa']), 'nama' => 'sisa']],
          'ket' => 'dari '.$rpk($o['nilai']).' · '.$o['BM'].' dari '.($o['M'] + $o['BM']).' tindak lanjut '.$kecil($kataBM)
            .($o['lhp'] ? ' · sisa SIPTL '.$rpk($o['sisaBpk']) : '')]) }}">
        <span class="nm" title="{{ $o['pendek'] }}">{{ $namaSatker($o['k']) }}</span>
        @include('ringkasan.panel.batang-nilai', ['o' => $o, 'maks' => $maksSisa])
        <span class="n">@if($o['sisa'] > 0)<b>{{ $rpk($o['sisa']) }}</b>@else<span class="dsb-lunas">tanpa sisa</span>@endif</span>
      </button>
    @endforeach
  </div>
  <div class="dsb-kaki-panel">
    <div class="dsb-legenda">
      <span><i style="background:{{ $W['M'] }}"></i>Diakui</span>
      <span><i style="background:var(--warn)"></i>Sisa</span>
    </div>
    @if(count($sisaSatker) > $TERATAS)
      <button type="submit" name="ubah" value="{{ 'semua:sisasatker@dsb-p-sisasatker' }}" class="dsb-lagi">
        @if($lengkap){{ $TERATAS }} teratas <x-ikon n="ChevronUp" :s="14" />@else Semua ({{ count($sisaSatker) }}) <x-ikon n="ChevronDown" :s="14" />@endif
      </button>
    @endif
  </div>
</x-dsb-panel>
