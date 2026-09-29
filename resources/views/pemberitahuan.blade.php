@extends('rangka')
@section('judul', 'Pemberitahuan')
@section('isi')
{{-- Halaman Pemberitahuan: riwayat lengkapnya — padanan `Pemberitahuan`
     prototipe (26 Sep). Dulu dua blok (yang belum dibaca di atas, yang sudah
     dibaca terlipat di bawah); sekarang satu daftar per hari dengan filter
     Belum dibaca · Semua dan kartu yang sama dengan panel lonceng. --}}
<div class="body kb-halaman" data-halaman-pemberitahuan>
  <div class="kb-ringkas">
    <span class="angka"><b data-jml-belum>{{ $belum }}</b> belum dibaca <span>· {{ $semua }} pemberitahuan</span></span>
    <x-info :teks="\App\Support\Pemberitahuan::KET" />
    <form method="post" action="{{ route('pemberitahuan.semua') }}" data-pemberitahuan-semua-f @if(! $belum) hidden @endif>
      @csrf
      <button type="submit" class="btn btn-s"><x-ikon n="CheckCheck" :s="14" /> Tandai semua dibaca</button>
    </form>
  </div>
  @include('bagian.pemberitahuan-tab', ['tab' => $tab, 'belum' => $belum, 'semua' => $semua, 'idIsi' => 'kb-lembar'])
  <div class="kb-lembar" id="kb-lembar" role="tabpanel" data-isi-pemberitahuan data-tab="{{ $tab }}">
    @include('bagian.pemberitahuan-daftar', ['daftar' => $daftar, 'lengkap' => true, 'tab' => $tab])
    @include('bagian.pemberitahuan-kosong', ['tab' => $kosong ? $tab : ''])
  </div>
</div>
@endsection
