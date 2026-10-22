@php
  use App\Enums\PeranPengguna as P;

  /* Saklar yang mati tidak terkirim; AkunController membacanya sebagai "mati". */
  $satker = $u->peranAktif() === P::SATKER;
  $tanpaEmail = ! $u->email;
  $ketEmail = match (true) {
      $tanpaEmail => 'Akun ini belum memiliki email.',
      $satker     => 'Pemberitahuan untuk '.($u->satker?->namaPendek() ?? 'unit Anda').' dikirim juga ke '.$u->email.'.',
      default     => 'Pemberitahuan untuk '.$u->peranAktif()->pendek().' dikirim juga ke '.$u->email.'.',
  };
@endphp
<form method="post" action="{{ route('akun.setelan') }}">
  @csrf
  <input type="hidden" name="bagian" value="pemberitahuan">
  <x-dm-kepala judul="Pemberitahuan" ket="Atur cara pemberitahuan dikirim ke Anda."
    :info="['Pengaturan pop-up, sorotan pembaruan di daftar Rekomendasi, dan pengiriman email untuk pemberitahuan akun Anda.',
      'Pemberitahuan di ikon lonceng tetap tersedia meskipun pop-up atau email dimatikan.']" />
  <div class="ak-blok">
    <ul class="ak-atur">
      <li>
        <div class="ak-baris">
          <span class="teks"><b>Pemberitahuan di aplikasi</b><span>Semua pemberitahuan untuk Anda selalu masuk ke ikon lonceng di kanan atas.</span></span>
          <span class="dm-keadaan aktif">Selalu aktif</span>
        </div>
      </li>
      <li>
        <label class="ak-baris">
          <span class="teks"><b>Pop-up pemberitahuan baru</b><span>Pemberitahuan baru tampil sebentar di pojok kanan atas, sekali untuk setiap pemberitahuan.</span></span>
          <input type="checkbox" role="switch" class="ak-saklar" name="popup" value="1" @checked($setelan['popup'])>
        </label>
      </li>
      <li>
        <label class="ak-baris">
          <span class="teks"><b>Sorot pembaruan di daftar Rekomendasi</b><span>Rekomendasi yang baru diperbarui tampil paling atas dan diberi titik merah sampai dibuka. Menekan barisnya langsung menunjukkan bagian yang berubah.</span></span>
          <input type="checkbox" role="switch" class="ak-saklar" name="pembaruan" value="1" @checked($setelan['pembaruan'])>
        </label>
      </li>
      <li>
        <label class="ak-baris">
          <span class="teks">
            <b>Kirim juga ke email</b>
            <span>{{ $ketEmail }}</span>
            @if($satker && ! $tanpaEmail)
              <span class="awas" data-awas-email @if($setelan['email']) hidden @endif>Jika dimatikan, pemberitahuan hanya tersedia di aplikasi.</span>
            @endif
          </span>
          <input type="checkbox" role="switch" class="ak-saklar" name="email" value="1"
            @checked($setelan['email'] && ! $tanpaEmail) @disabled($tanpaEmail)>
        </label>
      </li>
    </ul>
  </div>
  <div class="ak-kaki">
    <button type="submit" class="btn btn-p"><x-ikon n="Check" :s="15" /> Simpan</button>
    @if(session('pesan_akun'))
      <span class="ak-tersimpan" role="status"><x-ikon n="CheckCircle2" :s="15" /> {{ session('pesan_akun') }}</span>
    @endif
  </div>
</form>
