@props(['isi', 'kelas' => null])

{{-- Daftar keterangan. Butir: `l` nama, `v` isi (HTML aman), `k` keterangan di belakang isi,
     `ket` Info, `mono`, `c` warna. Butir bernilai palsu dilewati. --}}
<dl class="fakta{{ $kelas ? ' '.$kelas : '' }}">
  @foreach(array_filter($isi) as $m)
    <div>
      <dt>{{ $m['l'] }}@if(! empty($m['ket']))<x-info :teks="$m['ket']" />@endif</dt>
      <dd>
        <span @class(['mono' => ! empty($m['mono'])]) @if(! empty($m['c'])) style="color:{{ $m['c'] }}" @endif>{!! $m['v'] !!}</span>
        @if(! empty($m['k']))<span class="ket"> &middot; {{ $m['k'] }}</span>@endif
      </dd>
    </div>
  @endforeach
</dl>
