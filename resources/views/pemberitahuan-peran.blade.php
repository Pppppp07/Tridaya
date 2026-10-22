@extends('rangka')
@section('judul', 'Pemberitahuan')
@section('isi')
{{-- Link pemberitahuan (biasanya dari email) yang ditujukan ke peran lain milik akun ini. --}}
<div class="body">
  <div class="pesan" style="margin-top:0">
    <x-ikon n="Bell" :s="16" />
    <span>Pemberitahuan ini untuk peran <b>{{ $peran->pendek() }}</b>. Saat ini Anda bekerja sebagai {{ $sekarang->pendek() }}.</span>
  </div>
  <form method="post" action="{{ route('akun.peran-aktif') }}" style="margin-top:12px">
    @csrf
    <input type="hidden" name="peran" value="{{ $peran->value }}">
    <input type="hidden" name="kembali" value="{{ route('pemberitahuan.buka', $n, false) }}">
    <button type="submit" class="btn btn-p"><x-ikon n="ArrowRight" :s="14" /> Beralih ke {{ $peran->pendek() }} dan buka</button>
  </form>
</div>
@endsection
