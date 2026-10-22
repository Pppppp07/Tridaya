@extends('sso-rangka')
@section('judul', 'Ganti akun')
@section('isi')
<x-latar-masuk />
<main class="msk tunggal">
  <section class="msk-sambut"><x-merek-masuk /></section>
  <section class="msk-panel">
    <div class="msk-kartu">
      <h1>Ganti akun?</h1>
      <p class="sub">Dwaradaya mengirim akun yang berbeda dari akun {{ config('app.display_name') }} yang sedang terbuka.</p>
      <p class="msk-teks">Lanjutkan sebagai <strong>{{ $akunTujuan->name }}</strong> ({{ $akunTujuan->email }})? Sesi {{ config('app.display_name') }} saat ini akan diganti.</p>
      <form method="post" action="{{ route('sso.dwaradaya.lanjutkan') }}">
        @csrf
        <button type="submit" class="btn btn-p msk-tombol" name="pilihan" value="lanjut">Lanjutkan dengan akun ini</button>
        <p class="msk-kembali"><button class="btn" type="submit" name="pilihan" value="batal">Tetap gunakan akun sekarang</button></p>
      </form>
    </div>
  </section>
</main>
@endsection
