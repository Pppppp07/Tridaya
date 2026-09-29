{{-- Satu judul kolom tabel keseluruhan = tombol pengurut. --}}
<th @if($h['kelas']) class="{{ $h['kelas'] }}" @endif @if($h['rentang']) rowspan="{{ $h['rentang'] }}" @endif
  @if($h['aktif']) aria-sort="{{ $k['urut']['arah'] < 0 ? 'descending' : 'ascending' }}" @endif>
  <button type="submit" name="ubah" value="{{ 'urut:'.$h['k'] }}" class="urut{{ $h['aktif'] ? ' aktif' : '' }}"
    title="Urutkan menurut {{ mb_strtolower($h['isi']) }}">
    <span>{{ $h['isi'] }}</span>
    @if($h['aktif'])@if($k['urut']['arah'] < 0)<x-ikon n="ArrowDown" :s="12" />@else<x-ikon n="ArrowUp" :s="12" />@endif @endif
  </button>
</th>
