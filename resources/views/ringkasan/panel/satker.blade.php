{{-- Per satuan kerja — padanan `panelSatker`. Enam teratas, sisanya lewat
     "Semua (N)". Jumlah tindak lanjut di panel ini penyebut yang sama dengan
     kartu; lembar mereka menaruhnya di kepala dasbor ("Selesai Satker 303,
     Belum Selesai Satker 9"), di sinilah ia terurai per satuan kerja. --}}
@php
  $lengkap = in_array('satker', $k['semua'], true);
  $barisSatker = $lengkap ? $d['satker']
    : array_values(array_filter($d['satker'], fn ($o, $i) => $i < $TERATAS || in_array($o['k'], $f['satker'], true), ARRAY_FILTER_USE_BOTH));
  $maksSatker = max(1, ...array_map(fn ($o) => $o['M'] + $o['BM'], $d['satker'] ?: [['M' => 0, 'BM' => 0]]));
  $tugasT = array_sum(array_map(fn ($o) => $o['M'] + $o['BM'], $d['satker']));
  $tugasBM = array_sum(array_column($d['satker'], 'BM'));
  $tabelOn = in_array('satker', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-satker" :kelas="$kelas" judul="Per satuan kerja"
  :info="['Satu tindak lanjut = satu satuan kerja pada satu bentuk tindak lanjut.', 'Urut dari yang '.$kecil($kataBM).' terbanyak.']"
  :kosong="! $d['satker'] ? 'Tidak ada tindak lanjut pada pilihan ini.' : null"
  kunci-tabel="satker" :tabel="$tabelOn">
  @if($tugasT)
    <x-slot:wawasan>
      @if(! $tugasBM)Seluruh <b>{{ $tugasT }}</b> tindak lanjut sudah {{ $kecil($kataM) }}.@else<b>{{ $tugasBM }}</b> dari {{ $tugasT }} tindak lanjut {{ $kecil($kataBM) }}.@endif
    </x-slot:wawasan>
  @endif
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Satuan kerja'], ['nama' => $kataBM, 'num' => true], ['nama' => 'Tindak lanjut', 'num' => true], ['nama' => 'Sisa nilai', 'num' => true]]"
      :baris="array_map(fn ($o) => [$o['pendek'], $o['BM'], $o['M'] + $o['BM'], $rpk($o['sisa'])], $d['satker'])"
      :kaki="['Jumlah', $tugasBM, $tugasT, $rpk(array_sum(array_column($d['satker'], 'sisa')))]" />
  </x-slot:isiTabel>

  {{-- Daftar lengkap yang panjang digulir di dalam panelnya sendiri. --}}
  <div class="dsb-hbatang{{ $lengkap && count($barisSatker) > 14 ? ' gulir' : '' }}">
    @foreach($barisSatker as $o)
      @php
        $tot = $o['M'] + $o['BM'];
        $pilih = in_array($o['k'], $f['satker'], true);
      @endphp
      <button type="submit" name="ubah" value="{{ 'satker:'.$o['k'].'@dsb-p-satker' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
        class="dsb-hbaris{{ $f['satker'] && ! $pilih ? ' redup' : '' }}"
        aria-label="{{ $o['pendek'] }}: {{ $o['BM'] }} {{ $kecil($kataBM) }} dari {{ $tot }} tindak lanjut"
        data-petunjuk="{{ $petunjuk(['judul' => $o['pendek'],
          'baris' => [['warna' => $W['BM'], 'nilai' => $o['BM'], 'nama' => $kecil($kataBM)], ['warna' => $W['M'], 'nilai' => $o['M'], 'nama' => $kecil($kataM)]],
          'ket' => $tot.' tindak lanjut · sisa nilai '.$rpk($o['sisa']).($o['lhp'] ? ' · sisa SIPTL '.$rpk($o['sisaBpk']) : '')]) }}">
        <span class="nm" title="{{ $o['pendek'] }}">{{ $namaSatker($o['k']) }}</span>
        <span class="jalur">
          <span class="isi" style="width:{{ $tot / $maksSatker * 100 }}%">
            @if($o['BM'] > 0)<i @if($f['hasil'] === 'M') class="redup" @endif style="flex-grow:{{ $o['BM'] }};background:{{ $W['BM'] }}"></i>@endif
            @if($o['M'] > 0)<i @if($f['hasil'] === 'BM') class="redup" @endif style="flex-grow:{{ $o['M'] }};background:{{ $W['M'] }}"></i>@endif
          </span>
        </span>
        <span class="n"><b>{{ $o['BM'] }}</b> dari {{ $tot }}</span>
      </button>
    @endforeach
  </div>
  <div class="dsb-kaki-panel">
    <div class="dsb-legenda">
      <span><i style="background:{{ $W['BM'] }}"></i>{{ $kataBM }}</span>
      <span><i style="background:{{ $W['M'] }}"></i>{{ $kataM }}</span>
    </div>
    @if(count($d['satker']) > $TERATAS)
      <button type="submit" name="ubah" value="{{ 'semua:satker@dsb-p-satker' }}" class="dsb-lagi">
        @if($lengkap){{ $TERATAS }} teratas <x-ikon n="ChevronUp" :s="14" />@else Semua ({{ count($d['satker']) }}) <x-ikon n="ChevronDown" :s="14" />@endif
      </button>
    @endif
  </div>
</x-dsb-panel>
