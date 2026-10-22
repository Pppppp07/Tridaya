{{-- Baris tanpa label (peringatan atau keterangan di lajur isian). Atribut diteruskan ke
     barisnya. --}}
<div {{ $attributes->class(['fb-brs']) }}><span class="fb-lbl" aria-hidden="true"></span><div class="fb-isian">{{ $slot }}</div></div>
