@extends('rangka')
@section('judul', 'Pemberitahuan')
@section('isi')
{{-- Halaman Pemberitahuan: satu daftar per hari dengan filter Belum dibaca · Semua dan jenisnya.
     Ringkasan atas selalu menghitung semua jenis; tab dan daftar mengikuti jenis yang dipilih. --}}
<div class="body kb-halaman" data-halaman-pemberitahuan @if($jenis) data-jenis-aktif="{{ $jenis }}" @endif>
  <div class="kb-ringkas">
    <span class="angka"><b data-jml-belum>{{ $belumSemua }}</b> belum dibaca <span>· {{ $semua }} pemberitahuan</span></span>
    <x-info :teks="\App\Support\Pemberitahuan::KET" />
    <form method="post" action="{{ route('pemberitahuan.semua', array_filter(['jenis' => $jenis])) }}" data-pemberitahuan-semua-f @if(! $belum) hidden @endif>
      @csrf
      <button type="submit" class="btn btn-s"><x-ikon n="CheckCheck" :s="14" /> {{ $jenis ? 'Tandai yang tampil dibaca' : 'Tandai semua dibaca' }}</button>
    </form>
  </div>
  @include('bagian.pemberitahuan-tab', ['tab' => $tab, 'belum' => $belum, 'semua' => $semuaTampil, 'idIsi' => 'kb-lembar', 'saring' => (bool) $jenis])
  @include('bagian.pemberitahuan-jenis', ['tab' => $tab, 'jenis' => $jenis, 'hitung' => $hitungJenis, 'belumSemua' => $belumSemua, 'semua' => $semua])
  <div class="kb-lembar" id="kb-lembar" role="tabpanel" data-isi-pemberitahuan data-tab="{{ $tab }}">
    @include('bagian.pemberitahuan-daftar', ['daftar' => $daftar, 'lengkap' => true, 'semuaId' => $semuaId, 'tab' => $tab])
    @include('bagian.pemberitahuan-kosong', ['tab' => $kosong ? $tab : ''])
  </div>
  @if($semuaTampil > count($semuaId))
    <p class="kb-batas">Tampil {{ count($semuaId) }} pemberitahuan terbaru. Yang lebih lama tetap tercatat di tab Progres tiap rekomendasi.</p>
  @endif
</div>
@endsection
