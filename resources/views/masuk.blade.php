@extends('rangka')
@section('judul', $pengembang ? 'Login developer' : 'Masuk')
@use('App\Enums\PeranPengguna', 'P')
@use('App\Support\Rangka')
@section('isi')
{{-- Halaman masuk (ditata ulang 28 Sep). Dua kolom di layar lebar — kiri
     pengenal aplikasinya, kanan kartu masuk — dan satu kolom di layar sempit.
     Latarnya bergerak pelan (x-latar-masuk). Rupanya di simtlhp-tambahan.css
     ("halaman masuk"); kelakuannya — mata password, peringatan Caps Lock,
     tombol yang menunggu, akun contoh — di simtlhp.js (pasangMasuk). Semua
     tetap bekerja tanpa skrip, kecuali klik akun contoh.

     Satu tampilan untuk dua halaman (28 Sep, permintaan Hizkia):
     - /masuk — halaman masuk sungguhan, bersih seperti saat dipasang, tempat
       SSO. Selama pengembangan ada kartu "Login developer" di bawahnya.
     - /masuk/pengembang ($pengembang) — Login developer: SSO simulasi, email
       dan password, dan akun contoh. Tidak ditemukan di produksi. --}}
@php
  $gagal = $errors->any();
  /* Sesudah gagal, email-nya sudah terisi — password-nya yang diketik ulang. */
  $fokusPassword = $password && $gagal && old('email');
  $fokusEmail = $password && ! $fokusPassword && ! $sso;
@endphp
<x-latar-masuk />
<main class="msk">
  <section class="msk-sambut">
    <x-merek-masuk />
    <p class="msk-tajuk">Tindak lanjut hasil pemeriksaan, terpantau di satu tempat.</p>
    <p class="msk-ket">Sekretariat Badan, unit kerja, UKI, Inspektorat, dan pimpinan bekerja pada berkas yang sama.</p>
    <ul class="msk-butir">
      <li>
        <span class="ic" aria-hidden="true"><x-ikon n="FileCheck2" :s="17" /></span>
        <span><b>LHP BPK dan LHA Inspektorat</b>Rekomendasi, bukti, dan statusnya dalam satu daftar.</span>
      </li>
      <li>
        <span class="ic" aria-hidden="true"><x-ikon n="Building2" :s="17" /></span>
        <span><b>Setiap unit kerja</b>Mengunggah bukti dan memantau berkasnya sendiri.</span>
      </li>
      <li>
        <span class="ic" aria-hidden="true"><x-ikon n="ShieldCheck" :s="17" /></span>
        <span><b>Aman dan tercatat</b>Setiap perubahan tersimpan di riwayat dan log aktivitas.</span>
      </li>
    </ul>
  </section>

  <section class="msk-panel">
    <div class="msk-kartu">
      @if($pengembang)
        <p class="msk-mode"><x-ikon n="Code" :s="14" /> Pengembangan</p>
        <h1>Login developer</h1>
        <p class="sub">Masuk dengan akun contoh untuk mencoba setiap peran. Halaman ini tidak tersedia di versi resmi.</p>
      @else
        <h1>Masuk</h1>
        <p class="sub">Silakan masuk untuk melanjutkan.</p>
      @endif
      @if($gagal)
        <div class="pesan bad msk-error" role="alert"><x-ikon n="AlertTriangle" :s="16" /><span>{{ $errors->first() }}</span></div>
      @endif

      {{-- Masuk lewat SSO eHRM (27 Sep) — tombol utama begitu penyedianya
           disiapkan (SIMTLHP_SSO). Hanya pegawai yang terdaftar di Data master
           yang diterima; yang lain mendarat di halaman Akses dibatasi. --}}
      @if($sso)
        <a class="btn btn-p msk-tombol" href="{{ route('sso.arahkan') }}" data-tunggu="Menghubungkan…">
          <x-ikon n="ShieldCheck" :s="17" class="msk-ikon" />
          <x-ikon n="LoaderCircle" :s="17" class="msk-putar" />
          <span class="teks">Masuk dengan {{ $sso }}</span>
        </a>
        @if($password)<div class="msk-atau"><span>atau dengan email dan password</span></div>@endif
      @endif

      @if($password)
        <form method="post" action="{{ route('masuk') }}" data-form-masuk>
          @csrf
          <div class="msk-isian">
            <label for="email">Email</label>
            <span class="msk-kolom">
              <x-ikon n="Mail" :s="16" />
              <input type="text" name="email" id="email" value="{{ old('email') }}" required
                inputmode="email" autocapitalize="none" spellcheck="false" autocomplete="username"
                placeholder="nama@contoh.go.id" @if($fokusEmail) autofocus @endif @if($gagal) aria-invalid="true" @endif>
            </span>
          </div>
          <div class="msk-isian">
            <label for="password">Password</label>
            <span class="msk-kolom ada-mata">
              <x-ikon n="Lock" :s="16" />
              <input type="password" name="password" id="password" required autocomplete="current-password"
                aria-describedby="caps-password" @if($fokusPassword) autofocus @endif @if($gagal) aria-invalid="true" @endif>
              {{-- Dimunculkan skrip; tanpa skrip password-nya tetap tersamar. --}}
              <button type="button" class="msk-mata" data-mata-password aria-controls="password" aria-pressed="false"
                aria-label="Tampilkan password" title="Tampilkan password" hidden>
                <x-ikon n="Eye" :s="17" class="buka" /><x-ikon n="EyeOff" :s="17" class="tutup" />
              </button>
            </span>
            <p class="msk-caps" id="caps-password" role="status" hidden><x-ikon n="AlertTriangle" :s="14" /> Caps Lock aktif</p>
          </div>
          <button class="btn msk-tombol {{ $sso ? 'kedua' : 'btn-p' }}" type="submit" data-tunggu="Memeriksa…">
            <x-ikon n="LoaderCircle" :s="17" class="msk-putar" />
            <span class="teks">Masuk</span>
          </button>
        </form>
      @endif

      @if(! $sso && ! $password)
        {{-- Pemasangan tanpa SSO dan tanpa password: belum ada pintu. --}}
        <div class="pesan warn" role="status"><x-ikon n="AlertTriangle" :s="16" /><span>Masuk lewat SSO belum dikonfigurasi di server ini.</span></div>
      @endif

      @if($pengembang)
        <p class="msk-kembali-masuk"><a href="{{ route('masuk') }}"><x-ikon n="ArrowLeft" :s="14" /> Kembali ke halaman masuk</a></p>
      @else
        <p class="msk-catatan">
          <x-ikon n="Lock" :s="14" />
          <span>Hanya untuk petugas yang didaftarkan Sekretariat Badan. Belum punya akses? Hubungi Setba.</span>
        </p>
      @endif
    </div>

    {{-- Pintu ke Login developer — hanya selama pengembangan. Bingkai
         putus-putus: bukan bagian aplikasi yang sungguhan. --}}
    @if(! $pengembang && $adaPengembang)
      <a class="msk-pengembang" href="{{ route('masuk.pengembang') }}">
        <span class="ic" aria-hidden="true"><x-ikon n="Code" :s="17" /></span>
        <span class="teks">
          <b>Login developer <span class="pil">Pengembangan</span></b>
          <span>Masuk dengan akun contoh atau email dan password. Tidak tersedia di versi resmi.</span>
        </span>
        <x-ikon n="ArrowRight" :s="17" class="panah" />
      </a>
    @endif

    {{-- Padanan pemilih "Masuk sebagai" di prototipe. Hanya ada pada
         pemasangan contoh — padam sendiri di produksi
         (config simtlhp.akun_demo). Terbuka atau terlipatnya diingat
         tiap browser. --}}
    @if($akun->isNotEmpty())
      @php
        [$unit, $pusat] = $akun->partition(fn ($a) => $a->peran === P::SATKER);
        $urut = array_flip(array_map(fn ($p) => $p->value, P::pusat()));
        $pusat = $pusat->sortBy(fn ($a) => $urut[$a->peran->value] ?? 99)->values();
      @endphp
      <details class="msk-contoh" open data-ingat-buka="simtlhp.masuk.akunContoh">
        <summary>
          <span class="ic" aria-hidden="true"><x-ikon n="Users" :s="16" /></span>
          <span class="judul">Akun contoh <span class="n">{{ $akun->count() }}</span></span>
          <span class="pil">Demo</span>
          <x-ikon n="ChevronDown" :s="16" class="chev" />
        </summary>
        <div class="isi" data-akun-demo data-password="rahasia123">
          <p class="hint">Klik untuk langsung masuk. Semua akun memakai password <span class="mono">rahasia123</span>.</p>
          @if($pusat->isNotEmpty())
            <div class="msk-grup">
              <span class="lbl">Petugas pusat</span>
              <div class="msk-akun">
                @foreach($pusat as $a)
                  @php $p = Rangka::pengguna($a); @endphp
                  <button type="button" data-email="{{ $a->email }}" title="{{ $p['peran'] }} · {{ $a->email }}">
                    <span class="rupa" aria-hidden="true">{{ $p['rupa'] }}</span>
                    <span class="t"><b>{{ $p['nama'] }}</b><small>{{ $a->name }}</small></span>
                  </button>
                @endforeach
              </div>
            </div>
          @endif
          @if($unit->isNotEmpty())
            <div class="msk-grup">
              {{-- Akun satuan kerja milik penanggung jawabnya (Data master) —
                   unit kerjanya di baris pertama, orangnya di baris kedua. --}}
              <span class="lbl">Penanggung jawab unit kerja</span>
              <div class="msk-akun gulir">
                @foreach($unit as $a)
                  <button type="button" data-email="{{ $a->email }}" title="{{ $a->satker?->nama ?? 'Satuan kerja' }} · {{ $a->email }}">
                    <span class="rupa" aria-hidden="true">{{ Rangka::inisial($a->name) }}</span>
                    <span class="t"><b>{{ $a->satker?->namaPendek() ?? 'Satuan kerja' }}</b><small>{{ $a->name }}</small></span>
                  </button>
                @endforeach
              </div>
            </div>
          @endif
        </div>
      </details>
      {{-- Dilipat sebelum tergambar bila terakhir kali dilipat di browser
           ini — tanpa kedipan daftar yang terbuka lalu menutup. --}}
      <script nonce="{{ $nonce ?? '' }}">try{if(localStorage.getItem('simtlhp.masuk.akunContoh')==='0')document.currentScript.previousElementSibling.open=false}catch(e){}</script>
    @endif
  </section>

  <footer class="msk-kaki">
    <span>Sekretariat Badan &middot; BPSDM</span>
    <span>Pemantauan tindak lanjut LHP BPK dan LHA Inspektorat</span>
  </footer>
</main>
@endsection
