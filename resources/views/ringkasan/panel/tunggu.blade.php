{{-- Proses per satuan kerja: yang belum memadai menurut posisi berkasnya, satu warna per tahap
     (sama dengan kartu Proses tindak lanjut); urut terbanyak. --}}
@php
  use App\Support\Dasbor;

  $lengkap = in_array('tunggu', $k['semua'], true);
  $barisProses = $lengkap ? $d['tunggu']
    : array_values(array_filter($d['tunggu'], fn ($o, $i) => $i < $TERATAS || in_array($o['k'], $f['satker'], true), ARRAY_FILTER_USE_BOTH));
  $maksProses = max(1, ...array_map(fn ($o) => $o['BM'], $d['tunggu'] ?: [['BM' => 0]]));
  $jmlTahap = fn ($m) => array_sum(array_map(fn ($o) => $o['meja'][$m], $d['tunggu']));
  $jmlProses = array_sum(array_column($d['tunggu'], 'BM'));
  /* Tahap yang ada isinya, urut rantai posisi: dipakai batang, legenda, dan tabel. */
  $tahap = array_values(array_filter(array_keys(Dasbor::NAMA_MEJA), fn ($m) => $jmlTahap($m) > 0));
  $sebutTahap = fn ($m) => $m === 'selesai' ? 'selesai diperiksa' : 'di '.($m === 'satker' ? 'satuan kerja' : Dasbor::NAMA_MEJA[$m]);
  $tabelOn = in_array('tunggu', $k['tabel'], true);
  $pendek = fn ($id) => collect($kelompok['semua'])->firstWhere('k', $id)['pendek'] ?? 'Tidak diisi';
@endphp
<x-dsb-panel id="dsb-p-tunggu" :kelas="$kelas" judul="Proses per satuan kerja"
  :info="['Tindak lanjut '.$kecil($kataBM).' menurut posisi berkasnya: satuan kerja, Setba, UKI, atau Inspektorat.', 'Klik satuan kerja untuk memfilter data.']"
  :kosong="! $d['tunggu'] ? 'Tidak ada tindak lanjut yang sedang diproses.' : null"
  kunci-tabel="tunggu" :tabel="$tabelOn">
  @if($jmlProses)
    @php $nTunggu = $jmlTahap('satker'); @endphp
    <x-slot:wawasan>
      <b>{{ $jmlProses }}</b> tindak lanjut di <b>{{ count($d['tunggu']) }}</b> satuan kerja{{ $nTunggu ? ',' : '.' }}
      @if($nTunggu)<b>{{ $nTunggu }}</b> menunggu tanggapan satuan kerja.@endif
    </x-slot:wawasan>
  @endif
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="array_merge([['nama' => 'Satuan kerja']], array_map(fn ($m) => ['nama' => Dasbor::PENDEK_MEJA[$m], 'num' => true], $tahap), [['nama' => 'Jumlah', 'num' => true]])"
      :baris="array_map(fn ($o) => array_merge([$pendek($o['k'])], array_map(fn ($m) => $o['meja'][$m], $tahap), [$o['BM']]), $d['tunggu'])"
      :kaki="array_merge(['Jumlah'], array_map($jmlTahap, $tahap), [$jmlProses])" />
  </x-slot:isiTabel>

  <div class="dsb-hbatang{{ $lengkap && count($barisProses) > 14 ? ' gulir' : '' }}">
    @foreach($barisProses as $o)
      @php
        $pilih = in_array($o['k'], $f['satker'], true);
        $isiTahap = array_values(array_filter($tahap, fn ($m) => $o['meja'][$m] > 0));
      @endphp
      <button type="submit" name="ubah" value="{{ 'satker:'.$o['k'].'@dsb-p-tunggu' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
        class="dsb-hbaris{{ $f['satker'] && ! $pilih ? ' redup' : '' }}"
        aria-label="{{ $pendek($o['k']) }}: {{ $o['BM'] }} tindak lanjut {{ $kecil($kataBM) }} — {{ implode(', ', array_map(fn ($m) => $o['meja'][$m].' '.$sebutTahap($m), $isiTahap)) }}"
        data-petunjuk="{{ $petunjuk(['judul' => $pendek($o['k']),
          'baris' => array_map(fn ($m) => ['warna' => $warnaProses($m), 'nilai' => $o['meja'][$m], 'nama' => $sebutTahap($m)], $isiTahap),
          'ket' => $o['BM'].' tindak lanjut '.$kecil($kataBM)]) }}">
        <span class="nm" title="{{ $pendek($o['k']) }}">{{ $namaSatker($o['k']) }}</span>
        <span class="jalur">
          <span class="isi" style="width:{{ $o['BM'] / $maksProses * 100 }}%">
            @foreach($isiTahap as $m)<i style="flex-grow:{{ $o['meja'][$m] }};background:{{ $warnaProses($m) }}"></i>@endforeach
          </span>
        </span>
        <span class="n"><b>{{ $o['BM'] }}</b></span>
      </button>
    @endforeach
  </div>
  <div class="dsb-kaki-panel">
    <div class="dsb-legenda">
      @foreach($tahap as $m)
        <span><i style="background:{{ $warnaProses($m) }}"></i>{{ Dasbor::NAMA_MEJA[$m] }}</span>
      @endforeach
    </div>
    @if(count($d['tunggu']) > $TERATAS)
      <button type="submit" name="ubah" value="{{ 'semua:tunggu@dsb-p-tunggu' }}" class="dsb-lagi">
        @if($lengkap){{ $TERATAS }} teratas <x-ikon n="ChevronUp" :s="14" />@else Semua ({{ count($d['tunggu']) }}) <x-ikon n="ChevronDown" :s="14" />@endif
      </button>
    @endif
  </div>
</x-dsb-panel>
