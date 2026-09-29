{{-- Per kategori internal — padanan `panelKategori`. Namanya dari data
     master. Sejak 27 Sep batangnya memakai warna keadaan, sama dengan Per
     satuan kerja — bukan warna kategori, yang dulu terbaca sebagai keadaan.
     Klik batangnya memfilter kategori itu (baris "Kategori internal" ikut
     terpasang). --}}
@php
  $lengkap = in_array('kategori', $k['semua'], true);
  $dipilih = $f['lain']['intern'] ?? [];
  $barisKat = $lengkap ? $d['kategori']
    : array_values(array_filter($d['kategori'], fn ($o, $i) => $i < $TERATAS || in_array($o['k'], $dipilih, true), ARRAY_FILTER_USE_BOTH));
  $maksKat = max(1, ...array_map(fn ($o) => $o['n'], $d['kategori'] ?: [['n' => 0]]));
  $tabelOn = in_array('kategori', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-kategori" :kelas="$kelas" judul="Per kategori internal"
  :info="['Kelompok buatan BPSDM, diatur di Data master.', 'Warnanya sama dengan Per satuan kerja: '.$kecil($kataBM).' dan '.$kecil($kataM).'.']"
  :kosong="! $d['kategori'] ? 'Tidak ada tindak lanjut pada pilihan ini.' : null"
  kunci-tabel="kategori" :tabel="$tabelOn">
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Kategori internal'], ['nama' => $kataBM, 'num' => true], ['nama' => 'Jumlah', 'num' => true], ['nama' => 'Sisa nilai', 'num' => true]]"
      :baris="array_map(fn ($o) => [$o['nama'], $o['BM'], $o['n'], $rpk($o['sisa'])], $d['kategori'])"
      :kaki="['Jumlah', array_sum(array_column($d['kategori'], 'BM')), array_sum(array_column($d['kategori'], 'n')), $rpk(array_sum(array_column($d['kategori'], 'sisa')))]" />
  </x-slot:isiTabel>

  <div class="dsb-hbatang{{ $lengkap && count($barisKat) > 14 ? ' gulir' : '' }}">
    @foreach($barisKat as $o)
      @php
        $pilih = in_array($o['k'], $dipilih, true);
      @endphp
      <button type="submit" name="ubah" value="{{ 'lain:intern:'.$o['k'].'@dsb-p-kategori' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
        class="dsb-hbaris kat{{ $dipilih && ! $pilih ? ' redup' : '' }}"
        aria-label="{{ $o['nama'] }}: {{ $o['BM'] }} {{ $kecil($kataBM) }} dari {{ $o['n'] }} tindak lanjut"
        data-petunjuk="{{ $petunjuk(['judul' => $o['nama'],
          'baris' => [['warna' => $W['BM'], 'nilai' => $o['BM'], 'nama' => $kecil($kataBM)], ['warna' => $W['M'], 'nilai' => $o['M'], 'nama' => $kecil($kataM)]],
          'ket' => $o['n'].' tindak lanjut · sisa nilai '.$rpk($o['sisa'])]) }}">
        <span class="nm"><span class="t">{{ $o['nama'] }}</span></span>
        <span class="jalur">
          <span class="isi" style="width:{{ $o['n'] / $maksKat * 100 }}%">
            @if($o['BM'] > 0)<i @if($f['hasil'] === 'M') class="redup" @endif style="flex-grow:{{ $o['BM'] }};background:{{ $W['BM'] }}"></i>@endif
            @if($o['M'] > 0)<i @if($f['hasil'] === 'BM') class="redup" @endif style="flex-grow:{{ $o['M'] }};background:{{ $W['M'] }}"></i>@endif
          </span>
        </span>
        <span class="n"><b>{{ $o['BM'] }}</b> dari {{ $o['n'] }}</span>
      </button>
    @endforeach
  </div>
  <div class="dsb-kaki-panel">
    <div class="dsb-legenda">
      <span><i style="background:{{ $W['BM'] }}"></i>{{ $kataBM }}</span>
      <span><i style="background:{{ $W['M'] }}"></i>{{ $kataM }}</span>
    </div>
    @if(count($d['kategori']) > $TERATAS)
      <button type="submit" name="ubah" value="{{ 'semua:kategori@dsb-p-kategori' }}" class="dsb-lagi">
        @if($lengkap){{ $TERATAS }} teratas <x-ikon n="ChevronUp" :s="14" />@else Semua ({{ count($d['kategori']) }}) <x-ikon n="ChevronDown" :s="14" />@endif
      </button>
    @endif
  </div>
</x-dsb-panel>
