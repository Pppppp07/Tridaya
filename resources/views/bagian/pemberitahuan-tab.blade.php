{{-- Deret "Belum dibaca" · "Semua" — padanan `DeretTabPemberitahuan`. Tanpa skrip
     berupa link ke tab itu; dengan skrip berganti di tempat, dan isi "Belum
     dibaca" dibekukan saat tabnya dipilih (yang baru ditandai dibaca tetap di
     tempatnya sampai tabnya dipilih lagi). --}}
<div class="kb-tab" role="tablist" aria-label="Filter pemberitahuan">
  <a role="tab" href="{{ route('pemberitahuan', ['tab' => 'belum']) }}" aria-selected="{{ $tab === 'belum' ? 'true' : 'false' }}"
    aria-controls="{{ $idIsi }}" data-tab-pemberitahuan="belum">Belum dibaca <span class="n" data-jml-belum>{{ $belum }}</span></a>
  <a role="tab" href="{{ route('pemberitahuan', ['tab' => 'semua']) }}" aria-selected="{{ $tab === 'semua' ? 'true' : 'false' }}"
    aria-controls="{{ $idIsi }}" data-tab-pemberitahuan="semua">Semua <span class="n">{{ $semua }}</span></a>
</div>
