@extends('rangka')
@section('judul', 'Log aktivitas')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;
  use App\Http\Controllers\LogController;
  use App\Support\Aktivitas;
  use App\Support\Tampil;
  use App\Support\TampilanLog;

  $urut = ['setba', 'uki', 'inspektorat', 'pimpinan', 'dti', 'admin', 'satker'];
  $filter = array_filter(['rentang' => $f['rentang'] !== 'semua' ? $f['rentang'] : null, 'kelompok' => $f['kelompok'] ?: null,
    'peran' => $f['peran'] ?: null, 'q' => $f['q'] ?: null, 'akun' => $f['akun'], 'kejadian' => $f['kejadian']]);
  $filterAlert = ['tab' => 'alert', 'status' => $f['status']] + array_filter([
    'rentang' => $f['rentang'] !== 'semua' ? $f['rentang'] : null, 'tingkat' => $f['tingkat'] ?: null, 'q' => $f['q'] ?: null,
  ]);
  $statusAlert = ['baru' => 'Belum ditinjau', 'perlu_tindak_lanjut' => 'Perlu tindak lanjut', 'wajar' => 'Dinilai wajar', 'selesai' => 'Selesai ditinjau'];
  $rinci = (int) request('rinci');
  $faktaLog = static function ($x) {
    $p = TampilanLog::permintaan($x);
    return array_values(array_filter([
      ['l' => 'ID log', 'v' => '#'.$x->id, 'mono' => true],
      ['l' => 'Waktu', 'v' => e(Tampil::waktuLog($x->waktu))],
      ['l' => $x->user_id ? 'Nama saat kejadian' : 'Identitas yang dicoba / sistem', 'v' => e(TampilanLog::nama($x) ?: 'Identitas tidak tercatat')],
      ['l' => 'Peran pada saat kejadian', 'v' => e($x->sebutanPeran())],
      ['l' => 'Sumber aktivitas', 'v' => e(TampilanLog::asal($x))],
      ['l' => 'Kategori aktivitas', 'v' => e(Aktivitas::KELOMPOK[$x->kelompok] ?? $x->kelompok)],
      ['l' => 'Kode aktivitas', 'v' => e($x->aksi), 'mono' => true],
      TampilanLog::ringkasan($x) !== $x->ringkasan ? ['l' => 'Catatan asli', 'v' => e($x->ringkasan)] : null,
      TampilanLog::nama($x) !== $x->nama ? ['l' => 'Nama asli dalam log', 'v' => e($x->nama)] : null,
      ['l' => 'Alamat IP', 'v' => e($x->ip ?: 'Tidak tercatat'), 'mono' => true],
      ['l' => 'Perangkat', 'v' => e($x->agen ?: 'Tidak tercatat')],
      $p['metode'] ? ['l' => 'Metode permintaan', 'v' => e($p['metode']), 'mono' => true] : null,
      $p['alamat'] ? ['l' => 'Alamat permintaan', 'v' => e($p['alamat']), 'mono' => true] : null,
      $p['rute'] ? ['l' => 'Rute aplikasi', 'v' => e($p['rute']), 'mono' => true] : null,
      ['l' => 'Status HTTP', 'v' => e($p['status'] ?? 'Tidak tercatat'), 'mono' => $p['status'] !== null],
      ! empty($x->rincian['atas_nama']) ? ['l' => 'Atas nama', 'v' => e($x->rincian['atas_nama'])] : null,
    ]));
  };
@endphp

<div class="body dm lg">
  <div class="tl-tabs dm-tabs" role="tablist" aria-label="Log aktivitas">
    <a role="tab" href="{{ route('log', $filter) }}" aria-selected="{{ $f['tab'] === 'aktivitas' ? 'true' : 'false' }}"
      @if($f['tab'] === 'aktivitas') aria-current="page" @endif><x-ikon n="History" :s="14" />Aktivitas</a>
    <a role="tab" href="{{ route('log', ['tab' => 'alert']) }}" aria-selected="{{ $f['tab'] === 'alert' ? 'true' : 'false' }}"
      @if($f['tab'] === 'alert') aria-current="page" @endif><x-ikon n="ShieldCheck" :s="14" />Alert keamanan @if($jumlahAlert)<span class="dm-jumlah">{{ $jumlahAlert }}</span>@endif</a>
    <a role="tab" href="{{ route('log', ['tab' => 'keaktifan']) }}" aria-selected="{{ $f['tab'] === 'keaktifan' ? 'true' : 'false' }}"
      @if($f['tab'] === 'keaktifan') aria-current="page" @endif><x-ikon n="Users" :s="14" />Keaktifan akun</a>
  </div>

  <section class="kartu dm-panel" role="tabpanel" aria-label="{{ ['aktivitas' => 'Aktivitas', 'alert' => 'Alert keamanan', 'keaktifan' => 'Keaktifan akun'][$f['tab']] }}">
    @if($f['tab'] === 'alert')
      <x-dm-kepala judul="Alert keamanan" ket="Aktivitas yang perlu diperiksa. Alert adalah indikasi, bukan bukti pelanggaran." :info="[
        'Indikasi aktivitas yang memenuhi aturan pemantauan keamanan, disertai log sumber dan status tinjauannya.',
        'Alert perlu ditinjau sebelum dianggap sebagai ancaman. Gagal login atau penolakan akses juga bisa terjadi karena kesalahan biasa.',
      ]" />
      <div class="dm-ringkas lg-alert-meta"><span>@if($mulaiPantau)Pemantauan sejak {{ Tampil::waktuLog($mulaiPantau) }}@else Pemantauan belum aktif @endif</span><span>Log sebelum tanggal ini tidak diperiksa ulang secara otomatis.</span></div>
      <form method="get" action="{{ route('log') }}" class="lg-filter" data-log-filter>
        <input type="hidden" name="tab" value="alert">
        <label class="dm-cari"><x-ikon n="Search" :s="14" /><input type="search" name="q" value="{{ $f['q'] }}" placeholder="Cari indikasi, nama, atau IP" aria-label="Cari indikasi, nama, atau IP"></label>
        <label class="lg-pilih"><span>Tinjauan</span><select name="status" data-kirim-otomatis><option value="terbuka" @selected($f['status'] === 'terbuka')>Belum selesai</option><option value="" @selected($f['status'] === '')>Semua status</option>@foreach($statusAlert as $k => $v)<option value="{{ $k }}" @selected($f['status'] === $k)>{{ $v }}</option>@endforeach</select></label>
        <label class="lg-pilih"><span>Prioritas</span><select name="tingkat" data-kirim-otomatis><option value="">Semua</option><option value="tinggi" @selected($f['tingkat'] === 'tinggi')>Prioritas tinggi</option><option value="perhatian" @selected($f['tingkat'] === 'perhatian')>Perhatian</option></select></label>
        <label class="lg-pilih"><span>Waktu</span><select name="rentang" data-kirim-otomatis>@foreach($rentang as $k => $v)<option value="{{ $k }}" @selected($f['rentang'] === (string) $k)>{{ $v }}</option>@endforeach</select></label>
        <button type="submit" class="btn" data-tanpa-js>Terapkan</button>
      </form>
      <div class="dm-ringkas"><span><b>{{ $alerts->total() }}</b> alert sesuai filter</span></div>
      @if($alerts->count())
        <div class="dm-tabel"><table>
          <thead><tr><th>Terakhir terdeteksi</th><th>Indikasi</th><th>Oleh</th><th>Prioritas</th><th>Tinjauan</th><th><span class="sr-only">Bukti</span></th></tr></thead>
          <tbody>@foreach($alerts as $a)<tr @class(['lg-alert-aktif' => $alert?->id === $a->id])>
            <td class="dm-waktu" data-label="Terakhir terdeteksi">{{ Tampil::waktuLog($a->terakhir_pada) }}<span class="dm-sub">#{{ $a->id }}</span></td>
            <td data-label="Indikasi"><span class="dm-ubah">{{ TampilanLog::teksUi($a->judul) }}</span><span class="dm-sub">{{ $a->bukti['jumlah'] ?? count($a->bukti['log_ids'] ?? []) }} kejadian tercatat</span></td>
            <td data-label="Oleh"><span class="dm-orang"><b>{{ ! $a->user_id && ! $a->peran && $a->nama === 'Perintah peladen' ? 'Perintah server' : ($a->nama ?: 'Identitas tidak tercatat') }}</b><span>{{ P::tryFrom((string) $a->peran)?->pendek() ?? ($a->sumber === 'perintah_server' ? 'Sistem' : 'Belum login · identitas yang dicoba') }} · {{ $a->sumberLabel() }}</span></span></td>
            <td data-label="Prioritas"><span class="lg-resultat {{ $a->tingkat === 'tinggi' ? 'ditolak' : 'dibatasi' }}">{{ $a->tingkatLabel() }}</span></td>
            <td data-label="Tinjauan"><span class="lg-indikasi">{{ $a->statusLabel() }}</span></td>
            <td class="dm-aksi"><a class="btn btn-s" href="{{ route('log', $filterAlert + ['alert' => $a->id, 'page' => $alerts->currentPage()]) }}#lg-alert-detail">Lihat bukti</a></td>
          </tr>@endforeach</tbody>
        </table></div>
      @else
        <div class="kosong dm-kosong-kotak">Tidak ada alert yang cocok dengan filter ini. Aktivitas tetap perlu diperiksa bila ada dugaan masalah.</div>
      @endif

      @if($alert)
        <section class="lg-alert-detail" id="lg-alert-detail" aria-labelledby="lg-alert-judul">
          <div class="lg-alert-head"><div><span class="dm-sub">Alert #{{ $alert->id }} · {{ $alert->aturanLabel() }}</span><h3 id="lg-alert-judul">{{ TampilanLog::teksUi($alert->judul) }}</h3></div><a class="btn btn-s" href="{{ route('log', $filterAlert + ['page' => $alerts->currentPage()]) }}">Tutup bukti</a></div>
          <p>{{ TampilanLog::teksUi($alert->alasan) }}</p>
          <p class="lg-review-note">Periksa aktivitas dan hak akses saat kejadian, lalu konfirmasi ke pemilik akun. Identitas pada percobaan login belum terbukti milik pengirim. Satu alamat IP dapat dipakai beberapa orang.</p>
          <x-fakta :isi="[
            ['l' => 'Prioritas', 'v' => e($alert->tingkatLabel())],
            ['l' => 'Status tinjauan', 'v' => e($alert->statusLabel())],
            ['l' => 'Pertama terdeteksi', 'v' => e(Tampil::waktuLog($alert->pertama_pada))],
            ['l' => 'Terakhir terdeteksi', 'v' => e(Tampil::waktuLog($alert->terakhir_pada))],
            ['l' => 'Jumlah kejadian', 'v' => e($alert->bukti['jumlah'] ?? count($alert->bukti['log_ids'] ?? []))],
            ['l' => 'Alamat IP', 'v' => e($alert->ip ?: 'Tidak tercatat'), 'mono' => true],
          ]" />
          <h4>Bukti kejadian</h4>
          @if($alert->bukti['bukti_terpotong'] ?? false)<p class="lg-review-note">Ditampilkan {{ $bukti->count() }} log terbaru dari {{ $alert->bukti['jumlah'] }} kejadian agar panel tetap ringan. Buka Log aktivitas untuk menelusuri lebih lanjut.</p>@endif
          @if($bukti->isEmpty())<p class="lg-review-note">Log sumber tidak tersedia lagi. Periksa masa simpan log dan bukti ringkas alert sebelum mengambil kesimpulan.</p>@endif
          @foreach($bukti as $x)
            @php $h = TampilanLog::hasil($x); $obj = $x->subjek_tipe ? ($subjek[$x->subjek_tipe.':'.$x->subjek_id] ?? null) : null; @endphp
            <details class="lg-bukti-log">
              <summary><span class="lg-resultat {{ $h['kelas'] }}">{{ $h['nama'] }}</span><span>{{ Tampil::waktuLog($x->waktu) }} · {{ TampilanLog::ringkasan($x) }}</span><span class="dm-sub">Log #{{ $x->id }}</span></summary>
              <div class="lg-bukti"><x-fakta :isi="$faktaLog($x)" />
                @if($obj)<p><b>Target:</b> {{ $obj['teks'] }}</p>@endif
                @include('bagian.beda-log', ['rincian' => $x->rincian])
                <a class="btn btn-s" href="{{ route('log', ['kejadian' => $x->id, 'rinci' => $x->id]) }}">Buka log sumber</a>
              </div>
            </details>
          @endforeach
          <details class="lg-bukti-log"><summary>Detail aturan dan bukti asli</summary><pre class="lg-raw">{{ json_encode($alert->bukti, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>

          <h4>Hasil tinjauan</h4>
          @forelse($alert->tinjauan as $t)
            <article class="lg-review-note"><b>{{ $statusAlert[$t->keputusan] ?? $t->keputusan }}</b><p>{{ $t->catatan }}</p><span class="dm-sub">{{ $t->nama }} · {{ P::tryFrom((string) $t->peran)?->pendek() ?? 'Admin DTI' }} · {{ Tampil::waktuLog($t->waktu) }}</span></article>
          @empty<p class="dm-sub">Belum ditinjau oleh Admin DTI.</p>@endforelse
          @if(auth()->user()->peranAktif() === P::ADMIN)
            <form method="post" action="{{ route('log.alert.tinjau', $alert) }}" class="lg-review-form">
              @csrf
              <input type="hidden" name="bukti_sampai" value="{{ $alert->bukti['sampai_log_id'] ?? '' }}">
              <label class="lg-pilih"><span>Keputusan tinjauan</span><select name="keputusan" required><option value="">Pilih hasil pemeriksaan</option>
                @foreach($statusAlert as $k => $v)
                  @if($k !== 'baru')<option value="{{ $k }}" @selected(old('keputusan') === $k)>{{ $v }}</option>@endif
                @endforeach
              </select></label>
              <label><span>Alasan dan tindak lanjut</span><textarea name="catatan" rows="3" maxlength="2000" required placeholder="Tulis bukti yang diperiksa, alasan penilaian, dan tindakan yang diperlukan.">{{ old('catatan') }}</textarea></label>
              <p class="dm-sub">Hasil tinjauan disimpan sebagai catatan baru. Keputusan ini tidak otomatis membatasi atau menonaktifkan akun.</p>
              @foreach(['bukti_sampai', 'keputusan', 'catatan'] as $medan)
                @error($medan)<p class="form-error" role="alert">{{ $message }}</p>@enderror
              @endforeach
              <button type="submit" class="btn btn-p"><x-ikon n="Save" :s="14" /> Simpan tinjauan</button>
            </form>
          @else<p class="dm-sub">Tinjauan disimpan oleh Admin DTI. Akun DTI membaca bukti dan hasil penilaiannya.</p>@endif
        </section>
      @endif
    @elseif($f['tab'] === 'aktivitas')
      <x-dm-kepala judul="Aktivitas" ket="Lihat siapa melakukan apa, targetnya, dan hasilnya. Buka Rincian untuk memeriksa detail." :info="[
        'Catatan kegiatan di aplikasi: waktu, akun atau sumber yang menjalankan, tindakan, target, dan hasilnya.',
        'Nama dan peran mengikuti catatan saat kejadian. Nama target pada tautan mengikuti data terkini. Buka Rincian untuk melihat bukti yang tersimpan.',
      ]">
        @if($log->total())<a class="btn" href="{{ route('log.unduh', $filter) }}"><x-ikon n="Download" :s="14" /> Ekspor CSV</a>@else<span class="btn" aria-disabled="true"><x-ikon n="Download" :s="14" /> Ekspor CSV</span>@endif
      </x-dm-kepala>
      <form method="get" action="{{ route('log') }}" class="lg-filter" data-log-filter>
        <label class="dm-cari"><x-ikon n="Search" :s="14" /><input type="search" name="q" value="{{ $f['q'] }}" placeholder="Cari nama, aktivitas, atau IP" aria-label="Cari nama atau aktivitas"></label>
        <label class="lg-pilih"><span>Waktu</span><select name="rentang" data-kirim-otomatis>@foreach($rentang as $k => $v)<option value="{{ $k }}" @selected($f['rentang'] === (string) $k)>{{ $v }}</option>@endforeach</select></label>
        <label class="lg-pilih"><span>Kelompok</span><select name="kelompok" data-kirim-otomatis><option value="">Semua</option>@foreach(Aktivitas::KELOMPOK as $k => $v)<option value="{{ $k }}" @selected($f['kelompok'] === $k)>{{ $v }}</option>@endforeach</select></label>
        <label class="lg-pilih"><span>Peran</span><select name="peran" data-kirim-otomatis><option value="">Semua</option>@foreach($urut as $k)<option value="{{ $k }}" @selected($f['peran'] === $k)>{{ P::from($k)->pendek() }}</option>@endforeach</select></label>
        @if($f['akun'])<input type="hidden" name="akun" value="{{ $f['akun'] }}">@endif
        @if($f['kejadian'])<input type="hidden" name="kejadian" value="{{ $f['kejadian'] }}">@endif
        <button type="submit" class="btn" data-tanpa-js>Terapkan</button>
      </form>
      <div class="dm-ringkas"><span><b>{{ $log->total() }}</b> aktivitas @if($f['kejadian'])· Log #{{ $f['kejadian'] }}@endif</span>@if($filter)<a href="{{ route('log') }}">Semua aktivitas</a>@endif</div>
      @if($log->count())
        <div class="dm-tabel"><table>
          <thead><tr><th>Waktu</th><th>Oleh</th><th>Aktivitas</th><th>Target</th><th>Hasil <x-info :teks="TampilanLog::KET_HASIL" /></th><th><span class="sr-only">Rincian</span></th></tr></thead>
          <tbody>@foreach($log as $x)
            @php $h = TampilanLog::hasil($x); $asal = TampilanLog::asal($x); $obj = $x->subjek_tipe ? ($subjek[$x->subjek_tipe.':'.$x->subjek_id] ?? null) : null; $buka = $rinci === $x->id; @endphp
            <tr @class(['lg-tolak' => $h['kelas'] !== 'tercatat'])>
              <td class="dm-waktu" data-label="Waktu">{{ Tampil::waktuLog($x->waktu) }}<span class="dm-sub">#{{ $x->id }}</span></td>
              <td data-label="Oleh"><span class="dm-orang"><b>{{ TampilanLog::nama($x) ?: 'Identitas tidak tercatat' }}</b><span>{{ $x->peran ? $x->sebutanPeran() : (TampilanLog::asal($x) === 'Perintah server' ? 'Sistem' : 'Belum login · identitas yang dicoba') }}</span></span></td>
              <td data-label="Aktivitas"><span class="dm-ubah">{{ TampilanLog::ringkasan($x) }}</span><span class="dm-sub lg-asal">{{ Aktivitas::KELOMPOK[$x->kelompok] ?? $x->kelompok }} · {{ $asal === 'Log lama — asal tidak tercatat' ? 'Asal belum tercatat' : $asal }}</span>
                @foreach($alertLog[$x->id] ?? [] as $a)<a class="lg-indikasi" href="{{ route('log', ['tab' => 'alert', 'status' => '', 'alert' => $a->id]) }}#lg-alert-detail">Alert #{{ $a->id }} · {{ $a->statusLabel() }}</a>@endforeach
              </td>
              <td data-label="Target">@if($obj && $obj['url'])<a class="lg-objek" href="{{ $x->subjek_tipe === 'rekomendasi' ? $obj['url'].'?dari=log' : $obj['url'] }}">{{ $obj['teks'] }}</a>@elseif($obj){{ $obj['teks'] }}@elseif($x->subjek_tipe)<span>{{ $x->subjek_tipe }} #{{ $x->subjek_id }}</span>@else<span class="dm-sub">—</span>@endif</td>
              <td data-label="Hasil"><span class="lg-resultat {{ $h['kelas'] }}">{{ $h['nama'] }}</span></td>
              <td class="dm-aksi"><a class="btn btn-s" href="{{ route('log', $filter + ['page' => $log->currentPage(), 'rinci' => $buka ? null : $x->id]) }}" aria-expanded="{{ $buka ? 'true' : 'false' }}" aria-controls="lg-r-{{ $x->id }}" data-rinci-log>Rincian</a></td>
            </tr>
            <tr class="lg-rinci" id="lg-r-{{ $x->id }}" @unless($buka) hidden @endunless><td colspan="6">
              <div class="lg-bukti"><x-fakta :isi="$faktaLog($x)" />
                @if($asal === 'Perintah server' && empty($x->rincian['konteks']['sumber']))<p class="lg-review-note">Log lama ini dapat memuat alamat atau perangkat bawaan perintah server; catatan tersebut tidak mengidentifikasi operator sistem operasi.</p>@endif
                @if($x->satker)<p class="dm-sub">Satuan kerja tertaut: {{ $x->satker->namaPendek() }} (nama terkini).</p>@endif
                @if(\App\Support\BedaLog::butir($x->rincian))<h4>Perubahan yang tersimpan</h4>@include('bagian.beda-log', ['rincian' => $x->rincian])@endif
                @if($x->rincian)<details class="lg-bukti-log"><summary>Detail teknis (data asli)</summary><pre class="lg-raw">{{ json_encode($x->rincian, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
              </div>
            </td></tr>
          @endforeach</tbody>
        </table></div>
      @else<div class="kosong dm-kosong-kotak">Tidak ada aktivitas pada pilihan ini.</div>@endif
    @else
      <x-dm-kepala judul="Keaktifan akun" ket="Waktu login dan aktivitas terakhir. Akun yang paling lama tidak digunakan tampil lebih dulu." :info="[
        'Penggunaan setiap akun aktif berdasarkan login dan aktivitas yang tercatat. Jumlah aktivitas 30 hari tidak menghitung login dan logout.',
        'Diam menunjukkan jumlah hari sejak login atau aktivitas terakhir, mana yang lebih baru. Akun ditandai jika melewati '.LogController::HARI_DIAM.' hari; tanda ini tidak otomatis menonaktifkan akun.',
      ]" />
      <div class="dm-tabel"><table>
        <thead><tr><th>Akun</th><th>Terakhir masuk</th><th>Terakhir beraktivitas</th><th class="num">Aktivitas 30 hari</th><th>Penggunaan tercatat</th></tr></thead>
        <tbody>@foreach($keaktifan as $k)
          @php $a = $k->akun; @endphp
          <tr>
            <td data-label="Akun"><span class="dm-orang"><b><a class="lg-objek" href="{{ route('log', ['akun' => $a->id]) }}" title="Lihat aktivitas akun ini">{{ $a->name }}</a></b><span>{{ $a->peran->pendek() }}{{ $a->peran === P::SATKER && $a->satker ? ' · '.$a->satker->namaPendek() : '' }}</span></span></td>
            <td data-label="Terakhir masuk">@if($a->terakhir_masuk_pada){{ Tampil::waktuLog($a->terakhir_masuk_pada) }}@else<span class="dm-sub">Belum pernah</span>@endif</td>
            <td data-label="Terakhir beraktivitas">@if($k->terakhir_berbuat){{ Tampil::waktuLog($k->terakhir_berbuat) }}@else<span class="dm-sub">Belum ada</span>@endif</td>
            <td class="num mono" data-label="Aktivitas 30 hari">{{ $k->n30 }}</td>
            <td data-label="Penggunaan tercatat">@if($k->diam === null)<span class="dm-keadaan diam">Belum pernah dipakai</span>@elseif($k->diam > LogController::HARI_DIAM)<span class="dm-keadaan diam">Diam {{ $k->diam }} hari</span>@else<span class="dm-keadaan aktif">Dipakai</span>@endif</td>
          </tr>
        @endforeach</tbody>
      </table></div>
    @endif

    @php $halaman = $log ?? $alerts ?? null; @endphp
    @if($halaman && $halaman->lastPage() > 1)
      <nav class="lg-halaman" aria-label="Halaman log">
        @if($halaman->onFirstPage())<span class="btn btn-s" aria-disabled="true"><x-ikon n="ChevronLeft" :s="13" /> Sebelumnya</span>@else<a class="btn btn-s" href="{{ $halaman->previousPageUrl() }}"><x-ikon n="ChevronLeft" :s="13" /> Sebelumnya</a>@endif
        <span>Halaman {{ $halaman->currentPage() }} dari {{ $halaman->lastPage() }}</span>
        @if($halaman->hasMorePages())<a class="btn btn-s" href="{{ $halaman->nextPageUrl() }}">Berikutnya <x-ikon n="ChevronRight" :s="13" /></a>@else<span class="btn btn-s" aria-disabled="true">Berikutnya <x-ikon n="ChevronRight" :s="13" /></span>@endif
      </nav>
    @endif
  </section>
</div>
@endsection
