@php
  use App\Enums\PeranPengguna as P;

  /* Tab Pemberitahuan — padanan `TabPemberitahuanAkun` prototipe. Saklar yang
     mati tidak ikut terkirim; AkunController membacanya sebagai "mati". */
  $satker = $u->peran === P::SATKER;
  $tanpaEmail = ! $u->email;
  $ketEmail = match (true) {
      $tanpaEmail => 'Akun ini belum memiliki email.',
      $satker     => 'Pemberitahuan untuk '.($u->satker?->namaPendek() ?? 'unit Anda').' dikirim juga ke '.$u->email.'.',
      default     => 'Pemberitahuan untuk '.$u->peran->pendek().' dikirim juga ke '.$u->email.'.',
  };
@endphp
<form method="post" action="{{ route('akun.setelan') }}">
  @csrf
  <input type="hidden" name="bagian" value="pemberitahuan">
  <x-dm-kepala judul="Pemberitahuan" ket="Atur cara pemberitahuan dikirim ke Anda."
    :info="['Pemberitahuan dibuat setiap kali berkas yang terkait dengan Anda dikirim, diteruskan, dinilai, atau dikembalikan.',
      'Membuka pemberitahuan langsung menampilkan bagian yang berubah.']" />
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
          <span class="teks">
            <b>Kirim juga ke email</b>
            <span>{{ $ketEmail }}</span>
            {{-- Muncul begitu saklarnya dimatikan (skrip); tanpa skrip, bila
                 memang tersimpan mati. --}}
            @if($satker && ! $tanpaEmail)
              <span class="awas" data-awas-email @if($setelan['email']) hidden @endif>Sebaiknya tetap aktif — Setba mengandalkan email ini untuk menghubungi unit Anda.</span>
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
