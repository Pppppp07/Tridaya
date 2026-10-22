@props(['teks', 'nada' => null])

@php
  $poin = is_array($teks) ? array_values(array_filter($teks)) : [$teks];
@endphp

{{-- `title` adalah satu-satunya cara membaca isi Info tanpa JavaScript. Arah buka dihitung
     skrip (kelas `atas`, `kiri`). --}}
<span {{ $attributes->class(['info', $nada => (bool) $nada]) }}><button type="button" title="{{ implode(' ', $poin) }}" aria-label="Keterangan" aria-expanded="false">!</button><span class="isi" role="note" hidden>@if(is_array($teks))<span class="poin">@foreach($poin as $p)<span>{{ $p }}</span>@endforeach</span>@else{{ $teks }}@endif</span></span>
