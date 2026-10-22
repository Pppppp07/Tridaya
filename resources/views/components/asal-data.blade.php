{{-- Keterangan asal rekomendasi: hasil impor data historis atau dicatat di sistem. --}}
@props(['rek'])
@php
  $historis = $rek->historis();
  $acuan = $historis ? $rek->semuaBaris()->first(fn ($s) => $s->historis())?->tanggal_acuan_historis : null;
@endphp
<span {{ $attributes->class(['asal-data', $historis ? 'historis' : 'sistem']) }}
  title="{{ $historis ? 'Diimpor dari file pemantauan lama'.($acuan ? ', keadaan per '.\App\Support\Tampil::tgl($acuan) : '') : 'Dicatat lewat Catat laporan baru' }}">{{ $rek->asalData() }}</span>
