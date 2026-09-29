{{-- Batang nilai bertumpuk: hijau diakui, kuning sisa. Panjangnya = nilai
     baris itu dibanding yang terbesar. --}}
<span class="jalur">
  <span class="isi" style="width:{{ $o['nilai'] / $maks * 100 }}%">
    @if($o['diakui'] > 0)<i style="flex-grow:{{ $o['diakui'] }};background:{{ $W['M'] }}"></i>@endif
    @if($o['sisa'] > 0)<i style="flex-grow:{{ $o['sisa'] }};background:var(--warn)"></i>@endif
  </span>
</span>
