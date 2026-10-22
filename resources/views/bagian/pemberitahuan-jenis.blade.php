@php
  use App\Support\Pemberitahuan;

  /* Filter jenis di halaman Pemberitahuan. Hanya jenis yang memang ada untuk akun ini; angkanya
     mengikuti tab (belum dibaca / semua) dan diperbarui skrip saat tab berganti. */
  $pilihan = ['' => ['nama' => 'Semua jenis', 'belum' => $belumSemua, 'semua' => $semua]];
  foreach (Pemberitahuan::JENIS as $k => $j) {
      if (($hitung['semua'][$k] ?? 0) > 0) {
          $pilihan[$k] = ['nama' => $j['nama'], 'belum' => $hitung['belum'][$k] ?? 0, 'semua' => $hitung['semua'][$k]];
      }
  }
@endphp
@if(count($pilihan) > 2 || $jenis)
  <nav class="kb-jenis" aria-label="Filter jenis pemberitahuan">
    <span class="kb-jenis-lbl">Jenis</span>
    @foreach($pilihan as $k => $p)
      @php $aktif = (string) $jenis === (string) $k; @endphp
      <a href="{{ route('pemberitahuan', array_filter(['tab' => $tab, 'jenis' => $k])) }}" data-jenis-pilih="{{ $k }}"
        @class(['redup' => $tab === 'belum' && ! $p['belum'] && ! $aktif]) @if($aktif) aria-current="true" @endif>
        {{ $p['nama'] }} <span class="n" data-n-belum="{{ $p['belum'] }}" data-n-semua="{{ $p['semua'] }}">{{ $tab === 'belum' ? $p['belum'] : $p['semua'] }}</span>
      </a>
    @endforeach
  </nav>
@endif
