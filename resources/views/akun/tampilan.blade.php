@php
  use App\Support\Setelan;

  /* Tab Tampilan — padanan `TabTampilanAkun` prototipe. */
  $modeNav = [
    ['otomatis', 'Otomatis', 'Lebar di layar besar, ringkas di layar sedang.'],
    ['lebar', 'Selalu lebar', 'Nama menu selalu terlihat.'],
    ['ringkas', 'Selalu ringkas', 'Menu lebih ringkas dengan ikon dan label pendek.'],
  ];
  $animasi = [
    ['ikut', 'Ikuti perangkat', 'Animasi aktif, kecuali perangkat Anda diatur untuk mengurangi animasi.'],
    ['kurang', 'Kurangi animasi', 'Tanpa animasi dan efek transisi di semua halaman.'],
  ];
@endphp
<x-dm-kepala judul="Tampilan" ket="Atur tampilan aplikasi sesuai kebiasaan Anda." />

<form method="post" action="{{ route('akun.setelan') }}" data-form-tampilan>
  @csrf
  <input type="hidden" name="akun" value="{{ $u->id }}">
  <input type="hidden" name="bagian" value="tampilan">
  <div class="ak-blok">
    @if($errors->setelan->any())
      <div class="pesan bad" role="alert"><x-ikon n="AlertTriangle" :s="16" /><span>{{ $errors->setelan->first() }}</span></div>
    @endif
    <h3>Tema aplikasi</h3>
    <p class="ak-ket">Tersimpan untuk akun Anda dan berlaku di semua perangkat setelah masuk.</p>
    <div class="ak-pilih ak-tema" role="radiogroup" aria-label="Tema aplikasi">
      @foreach(['terang' => ['Terang', 'Default · latar cerah dan bersih.', 'Sun'], 'gelap' => ['Gelap', 'Latar gelap dengan kontras yang nyaman.', 'Moon']] as $k => [$nama, $ket, $ikon])
        <label class="dm-opsi tema-opsi">
          <input type="radio" name="tema" value="{{ $k }}" @checked(old('tema', $setelan['tema']) === $k)>
          <b><x-ikon :n="$ikon" :s="16" /> {{ $nama }}</b><span>{{ $ket }}</span>
          <span class="tema-pratinjau {{ $k }}" aria-hidden="true"><i></i><span><i></i><i></i><i></i></span></span>
        </label>
      @endforeach
    </div>
    <p class="ak-ket tema-petunjuk">Pilih tema lalu Simpan, atau gunakan tombol matahari/bulan di header untuk langsung beralih.</p>
  </div>
<div class="ak-blok">
  <h3>Menu samping</h3>
  <p class="ak-ket">Pilihan menu samping mengikuti akun Anda.</p>
  <div class="ak-pilih" role="radiogroup" aria-label="Menu samping">
    @foreach($modeNav as [$k, $nama, $ket])
      <label class="dm-opsi"><input type="radio" name="menu" value="{{ $k }}" @checked(old('menu', $setelan['menu']) === $k)><b>{{ $nama }}</b><span>{{ $ket }}</span></label>
    @endforeach
  </div>
</div>

  <div class="ak-blok">
    <h3>Animasi</h3>
    <p class="ak-ket">Untuk akun Anda, di perangkat mana pun.</p>
    <div class="ak-pilih" role="radiogroup" aria-label="Animasi">
      @foreach($animasi as [$k, $nama, $ket])
        <label class="dm-opsi">
          <input type="radio" name="animasi" value="{{ $k }}" @checked(old('animasi', $setelan['animasi']) === $k)><b>{{ $nama }}</b><span>{{ $ket }}</span>
        </label>
      @endforeach
    </div>
  </div>
  <div class="ak-blok">
    <h3>Halaman pertama sesudah masuk</h3>
    <p class="ak-ket">Halaman yang terbuka begitu Anda masuk ke aplikasi.</p>
    <label class="ak-beranda">
      <span class="sr-only">Halaman pertama sesudah masuk</span>
      <select name="beranda">
        @foreach(Setelan::berandaBoleh($u) as $k)
          <option value="{{ $k }}" @selected(old('beranda', $setelan['beranda']) === $k)>{{ Setelan::BERANDA[$k]['nama'] }}</option>
        @endforeach
      </select>
    </label>
  </div>
  <div class="ak-kaki">
    <button type="submit" class="btn btn-p"><x-ikon n="Check" :s="15" /> Simpan</button>
    @if(session('pesan_akun'))
      <span class="ak-tersimpan" role="status"><x-ikon n="CheckCircle2" :s="15" /> {{ session('pesan_akun') }}</span>
    @endif
  </div>
</form>
