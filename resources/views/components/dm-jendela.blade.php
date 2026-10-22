@props(['judul', 'sub' => null, 'ikon' => 'ListChecks', 'tutup', 'aksi' => null, 'tombol' => 'Simpan',
  'lebar' => false, 'wajib' => false, 'bisa' => true, 'metode' => 'post'])

{{-- Jendela Tambah/Ubah Data master, digambar server (`?jendela=`), jadi jalan tanpa skrip.
     Escape atau klik latar menutupnya. --}}
<div class="tirai" data-tirai-master data-tutup="{{ $tutup }}">
  <{{ $aksi ? 'form' : 'div' }} class="lembar dm-jendela{{ $lebar ? ' lebar' : '' }}" role="dialog" aria-modal="true" aria-label="{{ $judul }}"
    @if($aksi) method="{{ $metode }}" action="{{ $aksi }}" @endif>
    @if($aksi && $metode === 'post') @csrf @endif
    <div class="kep">
      <span class="ic-kotak biru"><x-ikon :n="$ikon" :s="17" /></span>
      <span class="judul"><b>{{ $judul }}</b>@if($sub)<span>{{ $sub }}</span>@endif</span>
      <a class="tutup" href="{{ $tutup }}" aria-label="Tutup"><x-ikon n="X" :s="16" /></a>
    </div>
    <div class="bdn">
      @if(session('gagal'))
        <div class="pesan bad" style="margin-top:0"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') }}</span></div>
      @endif
      @if($errors->any())
        <div class="pesan bad" style="margin-top:0"><x-ikon n="AlertTriangle" :s="16" /><span>{{ $errors->first() }}</span></div>
      @endif
      {{ $slot }}
      @if($wajib)<p class="dm-wajib"><span class="wajib">*</span> wajib diisi</p>@endif
    </div>
    @if($aksi)
      <div class="kak">
        <a class="btn" href="{{ $tutup }}">Batal</a>
        <button type="submit" class="btn btn-p" @disabled(! $bisa) {{ $attributes->only('data-pastikan') }}><x-ikon n="Check" :s="15" /> {{ $tombol }}</button>
      </div>
    @elseif(isset($kaki) && trim((string) $kaki) !== '')
      <div class="kak">{{ $kaki }}</div>
    @endif
  </{{ $aksi ? 'form' : 'div' }}>
</div>
