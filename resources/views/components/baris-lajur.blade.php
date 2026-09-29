{{-- Baris tanpa label — padanan `BarisLajur` prototipe: peringatan atau
     keterangan yang duduk di lajur isian, segaris dengan isian di atasnya.
     Atribut diteruskan ke barisnya (misalnya `hidden` yang dibuka skrip). --}}
<div {{ $attributes->class(['fb-brs']) }}><span class="fb-lbl" aria-hidden="true"></span><div class="fb-isian">{{ $slot }}</div></div>
