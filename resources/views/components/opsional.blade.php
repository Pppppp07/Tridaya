@props(['judul', 'jumlah', 'terisi' => false])

{{-- Isian yang boleh dikosongkan tidak ikut memenuhi panel sejak awal —
     padanan `Opsional` prototipe (25 Sep). Pembukanya satu link di lajur
     isian, dan isinya baris-baris biasa yang berbagi kolom label dengan baris
     di atasnya. Dulu kotak bergaris putus berbunyi "2 isian · boleh
     dikosongkan" — isian tanpa bintang memang sudah berarti boleh dikosongkan.

     Terbuka sendiri kalau isinya sudah ada, supaya yang sudah diketik tidak
     terlihat hilang. Yang tertutup menyebut isinya: berapa isian, atau bahwa
     ada yang sudah terisi (`pasangOpsional`). --}}
<div class="fb-brs kerja-opsional" data-opsional>
  <span class="fb-lbl" aria-hidden="true"></span>
  <div class="fb-isian">
    <button type="button" class="fb-link" aria-expanded="{{ $terisi ? 'true' : 'false' }}" data-buka-opsional><x-ikon n="Plus" :s="14" data-ikon-tutup :hidden="(bool) $terisi" /><x-ikon n="ChevronUp" :s="14" data-ikon-buka :hidden="! $terisi" />{{ $judul }}</button>
    <span class="fb-catatan" data-opsional-ket data-jumlah="{{ $jumlah }}" @if($terisi) hidden @endif>{{ $jumlah }} isian</span>
  </div>
</div>
<div class="kerja-susul" data-opsional-isi @if(! $terisi) hidden @endif>{{ $slot }}</div>
