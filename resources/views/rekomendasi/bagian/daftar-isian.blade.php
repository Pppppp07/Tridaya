{{-- Daftar isian satu baris per butir — padanan DaftarIsian. Selalu ada
     setidaknya satu baris; baris kosong diabaikan saat disimpan. Sejak 25 Sep
     bentuknya sama dengan daftar dokumen di formulir Catat laporan baru: tombol
     × di ujung barisnya, "Tambah dokumen" sebagai link di bawahnya. Nomor
     pada nama isian dan tombol hapusnya ditulis ulang skrip tiap kali barisnya
     bertambah atau berkurang (`pasangDaftarIsian`). --}}
@php $sebut = $sebut ?? 'dokumen'; @endphp
<div class="fb-dok" data-daftar-isian data-nama="{{ $nama }}" data-sebut="{{ $sebut }}">
  @foreach(array_values($nilai) as $i => $v)
    <div class="fb-dok-brs" data-baris-isian>
      <input type="text" name="{{ $nama }}[]" value="{{ $v }}" placeholder="{{ $placeholder }}" aria-label="{{ ucfirst($sebut) }} {{ $i + 1 }}">
      <button type="button" class="fb-x" aria-label="Hapus {{ $sebut }} {{ $i + 1 }}" data-hapus-isian @if(count($nilai) < 2) hidden @endif><x-ikon n="X" :s="14" /></button>
    </div>
  @endforeach
  <button type="button" class="fb-link" data-tambah-isian><x-ikon n="Plus" :s="13" /> {{ $tambah }}</button>
</div>
