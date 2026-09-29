@props(['teks', 'nada' => null])

@php
  /* Keterangan yang perlu tersedia tapi tidak perlu dibaca berulang. Boleh
     satu kalimat atau beberapa poin; beberapa poin digelar satu per baris. */
  $poin = is_array($teks) ? array_values(array_filter($teks)) : [$teks];
@endphp

{{-- `title` bukan hiasan: kalau JavaScript mati, itulah satu-satunya cara isi
     keterangan ini tetap bisa dibaca. Arah membukanya dihitung skrip saat
     ditekan (kelas `atas` dan `kiri`). Atribut lain diteruskan ke wadahnya —
     panel kerja memakainya untuk keterangan yang cuma berlaku pada satu
     putusan (`data-info-bila`). --}}
<span {{ $attributes->class(['info', $nada => (bool) $nada]) }}><button type="button" title="{{ implode(' ', $poin) }}" aria-label="Keterangan" aria-expanded="false">!</button><span class="isi" role="note" hidden>@if(is_array($teks))<span class="poin">@foreach($poin as $p)<span>{{ $p }}</span>@endforeach</span>@else{{ $teks }}@endif</span></span>
