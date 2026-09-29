{{-- Menunggu per satuan kerja — rincian kartu "Menunggu satuan kerja".
     Batangnya = tindak lanjut yang belum memadai: kuning yang berkasnya di
     satuan kerja, abu yang sedang diproses Setba, UKI, atau Inspektorat. Urut
     dari yang terbanyak menunggu. --}}
@php
  $lengkap = in_array('tunggu', $k['semua'], true);
  $barisTunggu = $lengkap ? $d['tunggu']
    : array_values(array_filter($d['tunggu'], fn ($o, $i) => $i < $TERATAS || in_array($o['k'], $f['satker'], true), ARRAY_FILTER_USE_BOTH));
  $maksTunggu = max(1, ...array_map(fn ($o) => $o['BM'], $d['tunggu'] ?: [['BM' => 0]]));
  $jmlTunggu = array_sum(array_column($d['tunggu'], 'tunggu'));
  $tabelOn = in_array('tunggu', $k['tabel'], true);
  $pendek = fn ($id) => collect($kelompok['semua'])->firstWhere('k', $id)['pendek'] ?? 'Tidak diisi';
@endphp
<x-dsb-panel id="dsb-p-tunggu" :kelas="$kelas" judul="Menunggu per satuan kerja"
  :info="['Kuning: tindak lanjut '.$kecil($kataBM).' yang berkasnya ada di satuan kerja, menunggu tanggapannya.', 'Abu: sedang diproses Setba, UKI, atau Inspektorat.']"
  :kosong="! $d['tunggu'] ? 'Tidak ada yang menunggu tanggapan satuan kerja.' : null"
  kunci-tabel="tunggu" :tabel="$tabelOn">
  @if($jmlTunggu)
    <x-slot:wawasan><b>{{ $jmlTunggu }}</b> tindak lanjut di <b>{{ count($d['tunggu']) }}</b> satuan kerja.</x-slot:wawasan>
  @endif
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Satuan kerja'], ['nama' => 'Menunggu', 'num' => true], ['nama' => $kataBM, 'num' => true]]"
      :baris="array_map(fn ($o) => [$pendek($o['k']), $o['tunggu'], $o['BM']], $d['tunggu'])"
      :kaki="['Jumlah', $jmlTunggu, array_sum(array_column($d['tunggu'], 'BM'))]" />
  </x-slot:isiTabel>

  <div class="dsb-hbatang{{ $lengkap && count($barisTunggu) > 14 ? ' gulir' : '' }}">
    @foreach($barisTunggu as $o)
      @php
        $pilih = in_array($o['k'], $f['satker'], true);
        $proses = $o['BM'] - $o['tunggu'];
      @endphp
      <button type="submit" name="ubah" value="{{ 'satker:'.$o['k'].'@dsb-p-tunggu' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
        class="dsb-hbaris{{ $f['satker'] && ! $pilih ? ' redup' : '' }}"
        aria-label="{{ $pendek($o['k']) }}: {{ $o['tunggu'] }} menunggu tanggapan, dari {{ $o['BM'] }} tindak lanjut {{ $kecil($kataBM) }}"
        data-petunjuk="{{ $petunjuk(['judul' => $pendek($o['k']),
          'baris' => [['warna' => $W['tunggu'], 'nilai' => $o['tunggu'], 'nama' => 'menunggu tanggapan'], ['warna' => $W['proses'], 'nilai' => $proses, 'nama' => 'sedang diproses']],
          'ket' => $o['BM'].' tindak lanjut '.$kecil($kataBM)]) }}">
        <span class="nm" title="{{ $pendek($o['k']) }}">{{ $namaSatker($o['k']) }}</span>
        <span class="jalur">
          <span class="isi" style="width:{{ $o['BM'] / $maksTunggu * 100 }}%">
            <i style="flex-grow:{{ $o['tunggu'] }};background:{{ $W['tunggu'] }}"></i>
            @if($proses > 0)<i style="flex-grow:{{ $proses }};background:{{ $W['proses'] }}"></i>@endif
          </span>
        </span>
        <span class="n"><b>{{ $o['tunggu'] }}</b> dari {{ $o['BM'] }}</span>
      </button>
    @endforeach
  </div>
  <div class="dsb-kaki-panel">
    <div class="dsb-legenda">
      <span><i style="background:{{ $W['tunggu'] }}"></i>Menunggu satuan kerja</span>
      <span><i style="background:{{ $W['proses'] }}"></i>Sedang diproses</span>
    </div>
    @if(count($d['tunggu']) > $TERATAS)
      <button type="submit" name="ubah" value="{{ 'semua:tunggu@dsb-p-tunggu' }}" class="dsb-lagi">
        @if($lengkap){{ $TERATAS }} teratas <x-ikon n="ChevronUp" :s="14" />@else Semua ({{ count($d['tunggu']) }}) <x-ikon n="ChevronDown" :s="14" />@endif
      </button>
    @endif
  </div>
</x-dsb-panel>
