@use('App\Models\Laporan')
{{-- Jarak surat diterima → dicatat bila lewat sepekan, atau mundur (salah satu tanggalnya
     salah). --}}
@if($j !== null && $j < 0)
  <div class="jedacatat salah">dicatat {{ -$j }} hari sebelum diterima</div>
@elseif($j !== null && $j > Laporan::BATAS_JEDA_CATAT)
  <div class="jedacatat">dicatat {{ $j }} hari kemudian</div>
@endif
