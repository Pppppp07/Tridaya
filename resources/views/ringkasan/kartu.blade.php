{{-- Lima kartu ringkasan utama — semuanya per TINDAK LANJUT SATUAN KERJA
     (Hizkia, 24 Sep: "seharusnya fokus utama ke tindak lanjut satuan kerja"),
     seperti kepala dasbor Excel Setba ("Selesai Satker 303, Belum Selesai
     Satker 9"). Kartu = tombol pembuka rinciannya; satu terbuka, klik lagi
     menutup. Kartu TIDAK memfilter. --}}
@php
  use App\Support\Dasbor;

  $propKartu = fn ($x) => [
    'value' => 'kartu:'.$x.'@dsb-rincian',
    'buka' => $k['kartu'] === $x,
  ];
  /* Garis kecil di kartu "Belum memadai": tumpukan pada akhir tiap bulan. */
  $maksTp = max(1, ...$tp);
  $yTp = fn ($v) => 25 - ($v / $maksTp) * 21;
  $titikTp = implode(' ', array_map(fn ($v, $i) => round($i / (count($tp) - 1) * 100, 3).','.round($yTp($v), 3),
    $tp, array_keys($tp)));
  $akhirTp = $tp[count($tp) - 1];
@endphp
{{-- data-tumbuh: kartunya masuk bergiliran dan batangnya tumbuh — hanya saat
     deret ini baru muncul. Dilepas simtlhp.js (pasangTumbuh); data-wadah
     pengenalnya waktu isi dasbor diganti di tempat. --}}
<div class="dsb-kpi" data-tumbuh data-wadah="kpi">
  @foreach(['M', 'BM', 'tunggu', 'sisa', 'ss'] as $kk)
    @php $p = $propKartu($kk); @endphp
    <button type="submit" name="ubah" value="{{ $p['value'] }}" class="dsb-ubin" aria-expanded="{{ $p['buka'] ? 'true' : 'false' }}"
      @if($p['buka']) aria-controls="dsb-rincian" @endif title="{{ $p['buka'] ? 'Tutup rincian' : 'Lihat rinciannya' }}">
      @switch($kk)
        @case('M')
          <span class="l"><i style="background:{{ $W['M'] }}"></i>{{ $kataM }}<x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $persen($t['M'], $t['n']) }}%</span>
          <span class="vis">
            <span class="dsb-meter"><i style="width:{{ $persen($t['M'], $t['n']) }}%;background:{{ $W['M'] }}"></i></span>
          </span>
          <span class="k">{{ $t['M'] }} dari {{ $t['n'] }} tindak lanjut</span>
          @break
        @case('BM')
          <span class="l"><i style="background:{{ $W['BM'] }}"></i>{{ $kataBM }}<x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $t['BM'] }}</span>
          <span class="vis">
            <span class="dsb-sirip" aria-hidden="true">
              <svg viewBox="0 0 100 28" preserveAspectRatio="none"><polyline points="{{ $titikTp }}" /></svg>
              <i style="left:100%;top:{{ round($yTp($akhirTp) / 28 * 100, 3) }}%"></i>
            </span>
          </span>
          <span class="k">
            @if($gerak === 0)
              sama dengan {{ $bulanLalu }}
            @else
              <span class="dsb-delta {{ $gerak < 0 ? 'baik' : 'buruk' }}">
                @if($gerak < 0)<x-ikon n="TrendingDown" :s="13" />@else<x-ikon n="TrendingUp" :s="13" />@endif{{ $gerak < 0 ? 'turun ' : 'naik ' }}{{ abs($gerak) }} sejak {{ $bulanLalu }}
              </span>
            @endif
          </span>
          @break
        @case('tunggu')
          {{-- Bola di tangan siapa: dari yang belum memadai, berapa yang
               menunggu tanggapan satuan kerja. Pitanya membagi yang belum
               memadai — kuning di satuan kerja, abu sedang diproses. Di kartu
               sempit labelnya versi pendek, supaya tetap satu baris. --}}
          <span class="l"><i style="background:{{ $W['tunggu'] }}"></i><span class="dsb-pjg">Menunggu satuan kerja</span><span class="dsb-pdk">Menunggu satker</span><x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $nTunggu }}</span>
          <span class="vis">
            @if($t['BM'] > 0)
              <span class="dsb-pitamini" aria-hidden="true">
                @if($nTunggu > 0)<i style="flex-grow:{{ $nTunggu }};background:{{ $W['tunggu'] }}"></i>@endif
                @if($t['BM'] - $nTunggu > 0)<i style="flex-grow:{{ $t['BM'] - $nTunggu }};background:{{ $W['proses'] }}"></i>@endif
              </span>
            @endif
          </span>
          <span class="k">dari {{ $t['BM'] }} {{ $kecil($kataBM) }}</span>
          @break
        @case('sisa')
          <span class="l">Sisa nilai<x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $rpk($t['sisa']) }}</span>
          {{-- Mbak Puspi: "kadang suka ditanya semuanya berapa, yang udah
               berapa" — dan sisanya. Hijau yang sudah diakui, kuning sisanya. --}}
          <span class="vis">
            <span class="dsb-pitamini" aria-hidden="true">
              @if($t['nilai'] - $t['sisa'] > 0)<i style="flex-grow:{{ $t['nilai'] - $t['sisa'] }};background:{{ $W['M'] }}"></i>@endif
              @if($t['sisa'] > 0)<i style="flex-grow:{{ $t['sisa'] }};background:var(--warn)"></i>@endif
            </span>
          </span>
          <span class="k"><span class="dsb-utuh">{{ $rpk($t['nilai'] - $t['sisa']) }}</span> diakui dari <span class="dsb-utuh">{{ $rpk($t['nilai']) }}</span></span>
          @break
        @case('ss')
          <span class="l">SS di SIPTL<x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $t['bpk']['n'] ? $persen($t['bpk']['SS'], $t['bpk']['n']).'%' : '—' }}</span>
          <span class="vis">
            @if($t['bpk']['n'] > 0)
              <span class="dsb-pitamini">
                @foreach(Dasbor::URUT_BPK as $s)
                  @if($t['bpk'][$s] > 0)<i style="flex-grow:{{ $t['bpk'][$s] }};background:{{ $W[$s] }}"></i>@endif
                @endforeach
              </span>
            @endif
          </span>
          <span class="k">
            @if($t['bpk']['n']){{ $t['bpk']['SS'] }} dari {{ $t['bpk']['n'] }} <span class="dsb-utuh">tindak lanjut LHP</span>@else LHA tidak masuk SIPTL @endif
          </span>
          @break
      @endswitch
    </button>
  @endforeach
</div>
