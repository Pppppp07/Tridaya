@php
  use App\Support\Perangkat;
  use App\Support\Tampil;

  /* Tab Keamanan — padanan `TabKeamananAkun` prototipe: password, perangkat
     yang sedang masuk (tabel sesi), dan sepuluh kejadian masuk terakhir —
     termasuk percobaan yang gagal dengan email akun ini. */
  $kejadian = fn ($x) => match ($x->aksi) {
      'masuk'         => match ($x->rincian['cara'] ?? null) {
          'sso'   => 'Masuk lewat SSO',
          'password' => 'Masuk dengan password',
          default => 'Masuk',
      },
      'keluar'        => 'Keluar',
      'masuk.dikunci' => 'Dikunci sementara — terlalu banyak salah',
      default         => 'Gagal masuk — password salah',
  };
  $lain = $sesi->where('ini', false);
  $pastikanSemua = ['judul' => 'Keluarkan semua perangkat lain?',
    'ket' => 'Perangkat itu harus masuk ulang. Perangkat yang sedang Anda pakai tidak ikut keluar.',
    'tombol' => 'Ya, keluarkan', 'nada' => 'jingga', 'balik' => false];
@endphp
<x-dm-kepala judul="Keamanan" ket="Password, perangkat aktif, dan riwayat masuk akun Anda."
  :info="['Jika ada perangkat atau percobaan masuk yang tidak Anda kenali, keluarkan perangkat tersebut, ganti password, lalu hubungi DTI.',
    'Sesi berakhir otomatis jika lama tidak digunakan. Aplikasi ini tidak memakai cookie “Remember me”.']" />

@if($gagal > 0)
  <div class="ak-blok">
    <div class="pesan warn" role="status"><x-ikon n="AlertTriangle" :s="16" /><span>
      Ada {{ $gagal }} percobaan masuk gagal dengan email Anda sejak masuk sebelumnya.
      Bukan Anda? Segera ganti password dan hubungi DTI.</span></div>
  </div>
@endif

<div class="ak-blok">
  <h3>Password</h3>
  @if($password)
    <p class="ak-ket">Digunakan saat masuk dengan email dan password. Minimal 10 karakter, berisi huruf dan angka.
      @if($sso)Jika Anda selalu masuk lewat SSO, password ini tidak digunakan.@endif
      <x-info :teks="['Setelah diganti, perangkat lain yang sedang memakai akun ini akan dikeluarkan.']" /></p>
    {{-- Diperiksa server, bukan browser (novalidate): kalimat salahnya sama
         dengan prototipe, bukan kalimat bawaan browser. --}}
    <form class="ak-password" method="post" action="{{ route('akun.password') }}" novalidate data-form-password>
      @csrf
      @if($errors->password->any())
        <div class="pesan bad" role="alert"><x-ikon n="AlertTriangle" :s="16" /><span>{{ $errors->password->first() }}</span></div>
      @endif
      <label class="fld"><span>Password saat ini <span class="wajib">*</span></span>
        <input type="password" name="current_password" autocomplete="current-password" required></label>
      <label class="fld"><span>Password baru <span class="wajib">*</span></span>
        <input type="password" name="password" autocomplete="new-password" required></label>
      <label class="fld"><span>Konfirmasi password baru <span class="wajib">*</span></span>
        <input type="password" name="password_confirmation" autocomplete="new-password" required></label>
      <p class="dm-wajib"><span class="wajib">*</span> wajib diisi</p>
      <div class="aksi">
        <button type="submit" class="btn btn-p"><x-ikon n="KeyRound" :s="15" /> Ganti password</button>
        {{-- Dimunculkan skrip; tanpa skrip password-nya tetap tersamar. --}}
        <label class="ak-tampak" hidden data-tampak-password><input type="checkbox"> Tampilkan password</label>
      </div>
      @if(session('pesan_password'))
        <span class="ak-tersimpan" role="status"><x-ikon n="CheckCircle2" :s="15" /> {{ session('pesan_password') }}</span>
      @endif
    </form>
  @else
    <p class="ak-ket" style="margin-bottom:0">Anda masuk lewat {{ $sso ?? 'SSO PU' }}. Password diatur di eHRM, bukan di aplikasi ini.</p>
  @endif
</div>

<div class="ak-blok">
  <div class="ak-blok-kepala">
    <div>
      <h3>Perangkat aktif</h3>
      <p class="ak-ket">Keluarkan perangkat yang tidak Anda kenali. Perangkat itu harus masuk ulang.</p>
    </div>
    @if($lain->count() > 1)
      <form method="post" action="{{ route('akun.sesi') }}">
        @csrf
        <input type="hidden" name="sesi" value="semua">
        <button type="submit" class="btn btn-s" data-pastikan='@json($pastikanSemua)'>Keluarkan semua perangkat lain</button>
      </form>
    @endif
  </div>
  @if(session('pesan_sesi'))
    <p class="ak-ket" style="margin:0 0 10px"><span class="ak-tersimpan" role="status"><x-ikon n="CheckCircle2" :s="15" /> {{ session('pesan_sesi') }}</span></p>
  @endif
  <ul class="ak-sesi">
    @foreach($sesi as $s)
      <li @class(['ini' => $s->ini])>
        <span class="ic"><x-ikon :n="$s->perangkat['ponsel'] ? 'Smartphone' : 'Monitor'" :s="18" /></span>
        <span class="teks">
          <b>{{ $s->perangkat['nama'] }}@if($s->ini) <span class="ak-ini">Perangkat ini</span>@endif</b>
          <span>{{ $s->ini ? 'Aktif sekarang' : 'Terakhir aktif '.Tampil::lalu($s->detik).($s->ip ? ' · '.$s->ip : '') }}</span>
        </span>
        @unless($s->ini)
          <form method="post" action="{{ route('akun.sesi') }}">
            @csrf
            <input type="hidden" name="sesi" value="{{ $s->kunci }}">
            <button type="submit" class="btn btn-s" aria-label="Keluarkan {{ $s->perangkat['nama'] }}">Keluarkan</button>
          </form>
        @endunless
      </li>
    @endforeach
  </ul>
  @if(! $sesiTersedia)
    <p class="ak-ket" style="margin:10px 0 0">Daftar perangkat lain tidak tersedia di server ini.</p>
  @elseif($lain->isEmpty())
    <p class="ak-ket" style="margin:10px 0 0">Tidak ada perangkat lain yang sedang aktif.</p>
  @endif
</div>

<div class="ak-blok">
  <h3>Riwayat masuk</h3>
  <p class="ak-ket">@if($sebelumnya)Masuk sebelumnya: {{ Tampil::waktuLog($sebelumnya->waktu) }}. @endif Sepuluh kejadian terakhir, terbaru di atas.</p>
  @if($riwayat->isNotEmpty())
    <div class="dm-tabel">
      <table>
        <thead><tr><th>Waktu</th><th>Kejadian</th><th>Perangkat</th><th>Alamat IP</th></tr></thead>
        <tbody>
          @foreach($riwayat as $x)
            @php $gagalIni = ! in_array($x->aksi, ['masuk', 'keluar'], true); @endphp
            <tr @class(['lg-tolak' => $gagalIni])>
              <td class="dm-waktu" data-label="Waktu">{{ Tampil::waktuLog($x->waktu) }}</td>
              <td data-label="Kejadian">
                <span @class(['ak-kejadian', 'gagal' => $gagalIni, 'masuk' => $x->aksi === 'masuk'])>
                  <x-ikon :n="$gagalIni ? 'AlertTriangle' : ($x->aksi === 'masuk' ? 'CheckCircle2' : 'ArrowRight')" :s="14" />
                  {{ $kejadian($x) }}
                </span>
              </td>
              <td data-label="Perangkat">{{ $x->agen ? Perangkat::sebut($x->agen)['nama'] : '—' }}</td>
              <td data-label="Alamat IP"><span class="mono">{{ $x->ip ?: '—' }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="ak-kosong">Belum ada catatan masuk.</p>
  @endif
</div>
