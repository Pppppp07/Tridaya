@php
  /* Satker yang dipimpin: satu atau beberapa, atau Semua Satker (termasuk yang ditambah kelak).
     Ditampilkan skrip hanya bila peran Pimpinan dipilih. */
  $cakupan = $cakupanPimpinan ?? ['ids' => [], 'semua' => false];
  $pilihId = array_map('intval', (array) old('pimpinan_satker', $cakupan['ids'] ?? []));
  $semuaDipilih = (bool) old('pimpinan_semua', $cakupan['semua'] ?? false);
@endphp
<div class="fld dm-cakupan" data-cakupan-pimpinan role="group" aria-labelledby="dm-cakupan-judul" style="margin-top:16px;margin-bottom:0">
  <span id="dm-cakupan-judul">Satker yang dipimpin<span class="wajib">*</span></span>
  <label class="centang dm-cakupan-semua">
    <input type="checkbox" name="pimpinan_semua" value="1" data-cakupan-semua @checked($semuaDipilih)>
    Semua Satker
  </label>
  <div class="dm-cakupan-daftar" data-cakupan-daftar>
    @foreach($unit as $s)
      <label class="centang">
        <input type="checkbox" name="pimpinan_satker[]" value="{{ $s->id }}" @checked(in_array((int) $s->id, $pilihId, true))>
        {{ $s->namaPendek() }}{{ $s->aktif ? '' : ' · nonaktif' }}
      </label>
    @endforeach
  </div>
  <span class="hint">Pilih satu atau beberapa Satker. Dashboard, laporan, dan rekomendasi hanya menampilkan Satker ini.</span>
</div>
