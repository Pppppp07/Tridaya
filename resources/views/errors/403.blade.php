@extends('rangka')
@section('judul', 'Tidak bisa dibuka')
@section('isi')
{{-- Penolakan 403 dalam bingkai aplikasi; tercatat di log aktivitas. --}}
<div class="body">
  <div class="kartu" style="max-width:640px">
    <span class="ic-kotak besar kuning" style="margin-bottom:12px"><x-ikon n="Lock" :s="20" /></span>
    <h2 style="margin:0 0 6px;font-size:var(--t5)">Halaman atau tindakan ini tidak untuk akun Anda</h2>
    <p style="margin:0;color:var(--ink-2);line-height:1.6">{{ $exception->getMessage() ?: 'Anda tidak punya hak untuk membuka atau mengubah ini.' }}</p>
    <p style="margin:16px 0 0"><a class="btn" href="{{ url('/') }}">&larr; Kembali ke beranda</a></p>
  </div>
</div>
@endsection
