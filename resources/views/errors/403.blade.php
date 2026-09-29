@extends('rangka')
@section('judul', 'Tidak bisa dibuka')
@section('isi')
{{-- Penolakan 403 dengan bingkai aplikasi (27 Sep), bukan halaman polos
     bawaan. Setiap penolakan bagi akun yang sudah masuk tercatat di log
     aktivitas (CatatAktivitas). --}}
<div class="body">
  <div class="kartu" style="max-width:640px">
    <span class="ic-kotak besar kuning" style="margin-bottom:12px"><x-ikon n="Lock" :s="20" /></span>
    <h2 style="margin:0 0 6px;font-size:var(--t5)">Halaman atau tindakan ini tidak untuk akun Anda</h2>
    <p style="margin:0;color:var(--ink-2);line-height:1.6">{{ $exception->getMessage() ?: 'Anda tidak punya hak untuk membuka atau mengubah ini.' }}</p>
    <p style="margin:16px 0 0"><a class="btn" href="{{ url('/') }}">&larr; Kembali ke beranda</a></p>
  </div>
</div>
@endsection
