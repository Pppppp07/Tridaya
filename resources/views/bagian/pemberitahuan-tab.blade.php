{{-- Deret "Belum dibaca" · "Semua": tanpa skrip berupa link, dengan skrip berganti di tempat.
     `$saring`: angka mengikuti jenis yang dipilih, jadi tidak ikut ditimpa angka belum dibaca global. --}}
@php $saring ??= false; @endphp
<div class="kb-tab" role="tablist" aria-label="Filter pemberitahuan">
  <a role="tab" href="{{ route('pemberitahuan', array_filter(['tab' => 'belum', 'jenis' => $jenis ?? null])) }}" aria-selected="{{ $tab === 'belum' ? 'true' : 'false' }}"
    aria-controls="{{ $idIsi }}" data-tab-pemberitahuan="belum">Belum dibaca <span class="n" @if($saring) data-jml-saring @else data-jml-belum @endif>{{ $belum }}</span></a>
  <a role="tab" href="{{ route('pemberitahuan', array_filter(['tab' => 'semua', 'jenis' => $jenis ?? null])) }}" aria-selected="{{ $tab === 'semua' ? 'true' : 'false' }}"
    aria-controls="{{ $idIsi }}" data-tab-pemberitahuan="semua">Semua <span class="n">{{ $semua }}</span></a>
</div>
