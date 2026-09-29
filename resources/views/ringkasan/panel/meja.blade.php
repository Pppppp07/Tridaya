{{-- Posisi berkas — padanan `panelMeja`: di meja siapa tiap tindak lanjut
     sedang menunggu. Klik batangnya memfilter meja itu. --}}
@php
  $maksMeja = max(1, ...array_column($d['meja'], 'n'));
  $puncakMeja = null;
  foreach ($d['meja'] as $m) {
    if ($m['k'] !== 'selesai' && $m['n'] > ($puncakMeja['n'] ?? 0)) {
      $puncakMeja = $m;
    }
  }
  $nMeja = array_sum(array_column($d['meja'], 'n'));
  $tabelOn = in_array('meja', $k['tabel'], true);
@endphp
<x-dsb-panel id="dsb-p-meja" :kelas="$kelas" judul="Posisi berkas" info="Di meja siapa tiap tindak lanjut sedang menunggu."
  :kosong="! $nMeja ? 'Tidak ada tindak lanjut pada pilihan ini.' : null"
  kunci-tabel="meja" :tabel="$tabelOn">
  <x-slot:isiTabel>
    <x-dsb-tabel :kepala="[['nama' => 'Meja'], ['nama' => 'Tindak lanjut', 'num' => true], ['nama' => 'Bagian', 'num' => true]]"
      :baris="array_map(fn ($m) => [$m['nama'], $m['n'], $persen($m['n'], $nMeja).'%'], $d['meja'])"
      :kaki="['Jumlah', $nMeja, '100%']" />
  </x-slot:isiTabel>

  <div class="dsb-pipa">
    <div class="plot" style="--n:{{ count($d['meja']) }}">
      @foreach($d['meja'] as $m)
        @php $pilih = in_array($m['k'], $f['meja'], true); @endphp
        {{-- 84%, bukan 100%: batang tertinggi masih menyisakan tempat untuk
             angka dan tanda "terbanyak" di atasnya. --}}
        <button type="submit" name="ubah" value="{{ 'meja:'.$m['k'].'@dsb-p-meja' }}" aria-pressed="{{ $pilih ? 'true' : 'false' }}"
          class="dsb-pita{{ $m['k'] === 'selesai' ? ' selesai' : '' }}{{ $f['meja'] && ! $pilih ? ' redup' : '' }}"
          style="--h:{{ $m['n'] / $maksMeja * 84 }}%" aria-label="{{ $m['nama'] }}: {{ $m['n'] }} tindak lanjut"
          data-petunjuk="{{ $petunjuk(['judul' => $m['nama'],
            'baris' => [['warna' => $m['k'] === 'selesai' ? $W['M'] : $W['tunggu'], 'nilai' => $m['n'], 'nama' => 'tindak lanjut']],
            'ket' => $persen($m['n'], $nMeja).'% dari seluruh tindak lanjut']) }}">
          <span class="batang"></span>
          @if($puncakMeja && $puncakMeja['k'] === $m['k'] && $m['n'] > 0)<span class="tag">terbanyak</span>@endif
          <b class="dsb-cap">{{ $m['n'] }}</b>
        </button>
      @endforeach
    </div>
    <div class="sumbux" style="--n:{{ count($d['meja']) }}">
      {{-- Nama lengkap, dan nama pendek untuk panel sempit (lihat CSS). --}}
      @foreach($d['meja'] as $m)
        <span><span class="pjg">{{ $m['nama'] }}</span><span class="pdk">{{ $m['pendek'] }}</span></span>
      @endforeach
    </div>
  </div>
  <div class="dsb-legenda">
    <span><i style="background:{{ $W['tunggu'] }}"></i>Menunggu</span>
    <span><i style="background:{{ $W['M'] }}"></i>Selesai</span>
  </div>
</x-dsb-panel>
