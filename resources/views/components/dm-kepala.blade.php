@props(['judul', 'ket' => null, 'info' => null])

{{-- Kepala tiap tab Data master dan Log aktivitas — padanan `KepalaTab`
     prototipe: judul, satu kalimat, Info, lalu alat di kanan (slot). --}}
<div class="dm-kepala">
  <div class="dm-judul">
    <h2>{{ $judul }} @if($info)<x-info :teks="$info" />@endif</h2>
    @if($ket)<p>{{ $ket }}</p>@endif
  </div>
  @if(trim($slot) !== '')<div class="dm-alat">{{ $slot }}</div>@endif
</div>
