@props(['judul', 'ket' => null, 'info' => null])

{{-- Kepala tab Data master dan Log: judul, satu kalimat, Info, lalu alat di kanan (slot). --}}
<div class="dm-kepala">
  <div class="dm-judul">
    <h2>{{ $judul }} @if($info)<x-info :teks="$info" />@endif</h2>
    @if($ket)<p>{{ $ket }}</p>@endif
  </div>
  @if(trim($slot) !== '')<div class="dm-alat">{{ $slot }}</div>@endif
</div>
