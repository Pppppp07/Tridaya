@use('App\Models\Laporan')
{{-- Jarak surat diterima ke pencatatannya, kalau lewat sepekan — atau mundur,
     yang berarti salah satu tanggalnya salah. Dipakai di kolom Diterima dan,
     di layar sempit, di bawah nomor laporan (22 Sep). --}}
@if($j !== null && $j < 0)
  <div class="jedacatat salah">dicatat {{ -$j }} hari sebelum diterima</div>
@elseif($j !== null && $j > Laporan::BATAS_JEDA_CATAT)
  <div class="jedacatat">dicatat {{ $j }} hari kemudian</div>
@endif
