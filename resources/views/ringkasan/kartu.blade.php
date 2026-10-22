{{-- Kartu ringkasan, semuanya per tindak lanjut satuan kerja. Kartu = tombol pembuka
     rinciannya. LHA tidak diunggah ke SIPTL, jadi kartu SIPTL hanya untuk LHP. --}}
@php
  use App\Support\Dasbor;

  $propKartu = fn ($x) => [
    'value' => 'kartu:'.$x.'@dsb-rincian',
    'buka' => $k['kartu'] === $x,
  ];
  $daftarKartu = $adaSiptl ? ['M', 'tunggu', 'sisa', 'ss'] : ['M', 'tunggu', 'sisa'];
@endphp
{{-- data-tumbuh: kartu masuk bergiliran dan batang tumbuh hanya saat deret ini baru muncul
     (pasangTumbuh); data-wadah = pengenal saat isi diganti di tempat. --}}
<div class="dsb-kpi" data-tumbuh data-wadah="kpi">
  @foreach($daftarKartu as $kk)
    @php $p = $propKartu($kk); @endphp
    <button type="submit" name="ubah" value="{{ $p['value'] }}" class="dsb-ubin" aria-expanded="{{ $p['buka'] ? 'true' : 'false' }}"
      @if($p['buka']) aria-controls="dsb-rincian" @endif title="{{ $p['buka'] ? 'Tutup rincian' : 'Lihat rinciannya' }}">
      @switch($kk)
        @case('M')
          {{-- Hasil verifikasi Inspektorat saja; status SIPTL punya kartunya sendiri. Kalimatnya
               sekaligus legenda warna pita. --}}
          <span class="l">Status<x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $persen($t['M'], $t['n']) }}%</span>
          <span class="vis">
            @if($t['n'] > 0)
              <span class="dsb-pitamini" aria-hidden="true">
                @if($t['M'] > 0)<i style="flex-grow:{{ $t['M'] }};background:{{ $W['M'] }}"></i>@endif
                @if($t['BM'] > 0)<i style="flex-grow:{{ $t['BM'] }};background:{{ $W['BM'] }}"></i>@endif
              </span>
            @endif
          </span>
          <span class="k legenda"><span class="dsb-utuh"><i class="dsb-tanda" style="background:{{ $W['M'] }}"></i>{{ $t['M'] }} {{ $kecil($kataM) }}<span class="dsb-sr">,</span></span><span class="dsb-utuh"><i class="dsb-tanda" style="background:{{ $W['BM'] }}"></i>{{ $t['BM'] }} {{ $kecil($kataBM) }}</span></span>
          @break
        @case('tunggu')
          {{-- Yang belum lolos menurut posisi berkasnya, satu warna per tahap. Label versi pendek di
               kartu sempit; kalimatnya sekaligus legenda warna pita. --}}
          @php $tahapAda = array_values(array_filter(array_keys(Dasbor::NAMA_MEJA), fn ($m) => ($t['meja'][$m] ?? 0) > 0)); @endphp
          <span class="l"><span class="dsb-pjg">Proses tindak lanjut</span><span class="dsb-pdk">Proses</span><x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $t['BM'] }}</span>
          <span class="vis">
            @if($tahapAda)
              <span class="dsb-pitamini" aria-hidden="true">
                @foreach($tahapAda as $m)<i style="flex-grow:{{ $t['meja'][$m] }};background:{{ $warnaProses($m) }}"></i>@endforeach
              </span>
            @endif
          </span>
          <span class="k legenda">@forelse($tahapAda as $m)<span class="dsb-utuh"><i class="dsb-tanda" style="background:{{ $warnaProses($m) }}"></i>{{ $t['meja'][$m] }} {{ Dasbor::PENDEK_MEJA[$m] }}@unless($loop->last)<span class="dsb-sr">,</span>@endunless</span>@empty Tidak ada yang sedang diproses @endforelse</span>
          @break
        @case('sisa')
          <span class="l">Sisa nilai<x-ikon n="ChevronDown" :s="15" class="buka" /></span>
          <span class="n">{{ $rpk($t['sisa']) }}</span>
          {{-- Total, yang sudah diakui (hijau), dan sisanya (kuning). --}}
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
            @if($t['bpk']['n']){{ $t['bpk']['SS'] }} dari {{ $t['bpk']['n'] }} <span class="dsb-utuh">tindak lanjut di SIPTL</span>@if($t['belumUnggah']) · <span class="dsb-utuh">{{ $t['belumUnggah'] }} belum diunggah</span>@endif
            @elseif($t['belumUnggah']){{ $t['belumUnggah'] }} <span class="dsb-utuh">belum diunggah ke SIPTL</span>
            @else Belum ada data SIPTL @endif
          </span>
          @break
      @endswitch
    </button>
  @endforeach
</div>
