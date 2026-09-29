@php
  /* "sebelum → sesudah" dari rincian log, dalam kalimat pendek — padanan
     `BedaLog` prototipe. Hanya medan yang punya nama untuk dibaca orang. */
  $namaMedan = ['nama' => 'Nama', 'pendek' => 'Nama pendek', 'aktif' => 'Keadaan', 'peran' => 'Peran',
    'uang' => 'Menuntut penyetoran', 'pj' => 'Penanggung jawab', 'nip' => 'NIP', 'sumber' => 'Sumber']
    + \App\Support\Setelan::NAMA;
  $nilaiMedan = function (string $k, $v) {
      if ($v === null || $v === '') {
          return '—';
      }

      return match ($k) {
          'aktif' => $v ? 'aktif' : 'nonaktif',
          'uang' => $v ? 'ya' : 'tidak',
          'peran' => \App\Enums\PeranPengguna::tryFrom((string) $v)?->pendek() ?? (string) $v,
          'popup', 'email', 'animasi', 'beranda' => \App\Support\Setelan::sebut($k, $v),
          default => (string) $v,
      };
  };
  $s = (array) ($rincian['sebelum'] ?? []);
  $d = (array) ($rincian['sesudah'] ?? []);
  $kunci = array_values(array_filter(array_unique(array_merge(array_keys($s), array_keys($d))), fn ($k) => isset($namaMedan[$k])));
@endphp
@if($kunci)
  <span class="dm-beda">
    @foreach($kunci as $k)
      <span>{{ $namaMedan[$k] }}: @if(array_key_exists($k, $s))<s>{{ $nilaiMedan($k, $s[$k]) }}</s> <x-ikon n="ArrowRight" :s="11" aria-label="menjadi" /> @endif<b>{{ $nilaiMedan($k, $d[$k] ?? null) }}</b></span>
    @endforeach
  </span>
@endif
