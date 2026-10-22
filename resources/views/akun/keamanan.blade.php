@php
  use App\Support\Perangkat;
  use App\Support\Tampil;

  /* Perangkat yang sedang masuk dan sepuluh kejadian masuk terakhir (termasuk percobaan gagal
     dengan email akun ini). Tidak ada ganti password: akun dikelola eHRM PU. */
  $kejadian = fn ($x) => match ($x->aksi) {
      'masuk'         => match ($x->rincian['cara'] ?? null) {
          'sso'   => 'Masuk lewat SSO',
          'password' => 'Masuk dengan password',
          default => 'Masuk',
      },
      'keluar'        => ($x->rincian['cara'] ?? null) === 'otomatis'
          ? 'Keluar otomatis — '.($x->rincian['batas_menit'] ?? '').' menit tanpa aktivitas' : 'Keluar',
      'masuk.dikunci' => 'Dikunci sementara — terlalu banyak salah',
      default         => 'Gagal masuk — password salah',
  };
  $lain = $sesi->where('ini', false);
  $pastikanSemua = ['judul' => 'Keluarkan semua perangkat lain?',
    'ket' => 'Perangkat itu harus masuk ulang. Perangkat yang sedang Anda pakai tidak ikut keluar.',
    'tombol' => 'Ya, keluarkan', 'nada' => 'jingga', 'balik' => false];
@endphp
<x-dm-kepala judul="Keamanan" ket="Perangkat aktif dan riwayat masuk akun Anda."
  :info="['Daftar perangkat yang masih login dan riwayat login atau logout akun Anda.',
    'Mengeluarkan perangkat mengakhiri sesi pada perangkat tersebut. Sesi juga berakhir otomatis setelah '.\App\Support\Sesi::menitDiam().' menit tanpa aktivitas.']" />

@if($gagal > 0)
  <div class="ak-blok">
    <div class="pesan warn" role="status"><x-ikon n="AlertTriangle" :s="16" /><span>
      Ada {{ $gagal }} percobaan masuk gagal dengan email Anda sejak masuk sebelumnya.
      Bukan Anda? Segera hubungi Admin DTI.</span></div>
  </div>
@endif

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
