@php
  use App\Enums\PeranPengguna as P;
  use App\Enums\SumberLaporan;
  use App\Support\DirektoriIrm;
  use App\Support\PusatData;
  use App\Support\Rangka;
  use App\Support\Tampil;

  /* Jendela Data master, digambar server dari `?jendela=`. `$tutup` = alamat daftar tanpa
     jendela; `$kembali` = alamat yang sama untuk formulir. */
  $j = $jendela;
@endphp
@switch($j['jenis'])
  @case('unit')
    @php $x = $j['x']; $terkunci = $j['kunci'] ?? false; @endphp
    <x-dm-jendela :judul="$x ? 'Ubah '.$x->namaPendek() : 'Tambah satuan kerja'" ikon="Building2" :tutup="$tutup" wajib
      :sub="$x ? 'Nama hanya bisa diubah jika belum digunakan dalam berkas.' : 'Setelah satuan kerja ditambahkan, pilih penanggung jawab dari IRM.'"
      :aksi="$x ? route('master.unit.simpan', $x) : route('master.unit.tambah')">
      <input type="hidden" name="kembali" value="{{ $kembali }}">
      <label class="fld">
        <span>Nama lengkap<span class="wajib">*</span></span>
        <input type="text" name="nama" value="{{ $terkunci ? $x->nama : old('nama', $x?->nama) }}" required
          @if($terkunci) readonly @else autofocus @endif
          placeholder="mis. Balai Pengembangan Kompetensi PUPR Wilayah X Kupang">
      </label>
      <label class="fld">
        <span>Nama pendek</span>
        <input type="text" name="pendek" value="{{ $terkunci ? $x->nama_pendek : old('pendek', $x?->nama_pendek) }}"
          @if($terkunci) readonly @endif placeholder="mis. Balai Wil. X Kupang">
        <span class="hint">{{ $terkunci ? 'Sudah dipakai berkas, jadi namanya terkunci.' : 'Digunakan di tabel. Jika kosong, sistem memakai nama lengkap.' }}</span>
      </label>
      <label class="fld" style="margin-bottom:0">
        <span>Kode satker</span>
        <input type="text" name="kode_satker" value="{{ old('kode_satker', $x?->kode_satker) }}" class="mono"
          inputmode="numeric" maxlength="6" pattern="[0-9]{6}" title="6 angka" placeholder="mis. 694123"
          @if($terkunci) autofocus @endif>
        <span class="hint">Kode resmi 6 angka, untuk mencocokkan data lama dan akun SSO.</span>
      </label>
    </x-dm-jendela>
    @break

  @case('riwayat-unit')
    @php $u = $j['x']; @endphp
    <x-dm-jendela :judul="'Riwayat '.$u->namaPendek()" :sub="$u->nama" ikon="History" lebar :tutup="$tutup">
      @if($j['log']->count())
        <ol class="dm-jejak">
          @foreach($j['log'] as $l)
            <li>
              <span class="dm-waktu">{{ Tampil::waktuLog($l->waktu) }}</span>
              <span><span class="dm-ubah">{{ $l->ringkasan }}</span>@include('bagian.beda-log', ['rincian' => $l->rincian])<span class="dm-sub">oleh {{ $l->nama ?: 'Sistem (otomatis)' }}</span></span>
            </li>
          @endforeach
        </ol>
      @else
        <p class="dm-sub">Belum ada perubahan yang tercatat untuk satuan kerja ini.</p>
      @endif
      <x-slot:kaki><a class="btn btn-p" href="{{ $tutup }}">Tutup</a></x-slot:kaki>
    </x-dm-jendela>
    @break

  @case('pengguna')
    @php $p = $j['pilih']; $c = $j['cari']; @endphp
    @if(! $p)
      <x-dm-jendela judul="Tambah user" ikon="UserPlus" :tutup="$tutup" lebar sub="Cari pegawai berdasarkan nama atau NIP.">
        <form method="get" action="{{ route('master') }}" class="cari-irm" data-cari-pegawai data-sumber="{{ route('master.pengguna.cari') }}">
          <x-ikon n="Search" :s="15" />
          <input type="hidden" name="tab" value="pengguna">
          <input type="hidden" name="jendela" value="pengguna">
          <input type="text" name="q" value="{{ $c['q'] }}" placeholder="Ketik nama atau NIP" aria-label="Cari pegawai di direktori" autocomplete="off" autofocus>
        </form>
        <div data-hasil-pegawai>@include('data-master.hasil-pegawai', $c)</div>
      </x-dm-jendela>
    @else
      @php $ada = $akun->firstWhere('nip', $p['nip']); @endphp
      <x-dm-jendela judul="Tambah user" ikon="UserPlus" :tutup="$tutup" lebar wajib sub="Pilih satu atau beberapa peran user."
        :aksi="route('master.pengguna.tambah')" tombol="Tambahkan">
        <input type="hidden" name="nip" value="{{ $p['nip'] }}">
        <div class="dm-terpilih">
          <span class="rupa" aria-hidden="true">{{ Rangka::inisial($p['nama']) }}</span>
          <span class="dm-orang">
            <b>{{ $p['nama'] }}</b>
            <span class="mono">NIP {{ DirektoriIrm::nipTampil($p['nip']) }}</span>
            <span>{{ $p['jabatan'] }} · {{ $p['unitNama'] }}</span>
            <span>{{ $p['email'] }}</span>
          </span>
          <a class="btn btn-s" href="{{ $ke(['jendela' => 'pengguna', 'q' => $c['q'] ?: $p['nama']]) }}">Ganti pegawai</a>
        </div>
        @if($ada && $ada->aktif)
          <div class="pesan warn" style="margin-top:0"><x-ikon n="AlertTriangle" :s="16" /><span>{{ $p['nama'] }} sudah punya akun sebagai {{ $ada->peran->pendek() }}.</span></div>
        @endif
        <div class="fld" style="margin-bottom:0">
          <span>Peran<span class="wajib">*</span></span>
          @include('data-master.pilihan-peran', ['nilai' => old('perans', []), 'bolehBeri' => $bolehBeri, 'banyak' => true])
        </div>
        @include('data-master.cakupan-pimpinan', ['cakupanPimpinan' => $ada?->cakupanPimpinan()])
      </x-dm-jendela>
    @endif
    @break

  @case('impor')
    @php
      $peranImpor = $j['peran'] !== '' ? P::from($j['peran']) : null;
      $nipImpor = PusatData::uraiNip($j['teks']);
      $statusImpor = [
        'siap' => ['ok', 'Siap ditambahkan'],
        'kembali' => ['ok', 'Akun nonaktif — diaktifkan kembali dengan peran ini'],
        'sudah' => ['warn', 'Sudah memiliki akun — dilewati'],
        'email' => ['warn', 'Emailnya dipakai akun lain — dilewati'],
        'tidak-ada' => ['bad', 'Tidak ada di direktori pegawai — dilewati'],
      ];
    @endphp
    @if(! $j['hasil'])
      {{-- Langkah 1: tempel NIP dan pilih peran. Diperiksa lewat GET — belum ada yang berubah. --}}
      <x-dm-jendela judul="Tambah beberapa user" ikon="ListPlus" :tutup="$tutup" lebar wajib metode="get" :aksi="route('master')"
        sub="Tempel daftar NIP, lalu pilih peran untuk semua user." tombol="Periksa daftar">
        <input type="hidden" name="tab" value="pengguna">
        <input type="hidden" name="jendela" value="impor">
        <input type="hidden" name="periksa" value="1">
        <label class="fld">
          <span>NIP pegawai<span class="wajib">*</span></span>
          <textarea name="nip" rows="6" autofocus required data-hitung-nip placeholder="197201011996012001&#10;198005152005011002">{{ $j['teks'] }}</textarea>
          <span class="hint">
            Satu NIP satu baris. Boleh dipisah koma, dan boleh berkelompok seperti di kartu pegawai.
            <span data-nip-terbaca>@if($j['jumlah'] > 0) <b>{{ $j['jumlah'] }}</b> NIP terbaca.@endif</span>
            @if($j['kelebihan']) <b>Maksimal {{ \App\Http\Controllers\PenggunaController::BATAS_IMPOR }} NIP dalam sekali tambah.</b>@endif
          </span>
        </label>
        <div class="fld" style="margin-bottom:0">
          <span>Peran untuk semua user<span class="wajib">*</span></span>
          @include('data-master.pilihan-peran', ['nilai' => $j['peran'], 'bolehBeri' => $bolehBeri, 'namaIsian' => 'peran_baru'])
        </div>
        @include('data-master.cakupan-pimpinan', ['cakupanPimpinan' => $j['pimpinan']])
      </x-dm-jendela>
    @else
      {{-- Langkah 2: hasil pemeriksaan. Yang dikirim hanya NIP dan peran, dihitung ulang di
           server. --}}
      @php
        $siapImpor = collect($j['hasil'])->filter(fn ($b) => in_array($b['status'], ['siap', 'kembali'], true))->count();
        $lewatImpor = count($j['hasil']) - $siapImpor;
      @endphp
      <x-dm-jendela judul="Tambah beberapa user" ikon="ListPlus" :tutup="$tutup" lebar :bisa="$siapImpor > 0"
        sub="Periksa daftar sebelum menambahkan user." :aksi="route('master.pengguna.banyak')" :tombol="'Tambahkan '.$siapImpor.' user'">
        <input type="hidden" name="nip" value="{{ implode(',', $nipImpor) }}">
        <input type="hidden" name="peran" value="{{ $peranImpor->value }}">
        @if($peranImpor === P::PIMPINAN)
          @if($j['pimpinan']['semua'])
            <input type="hidden" name="pimpinan_semua" value="1">
          @endif
          @foreach($j['pimpinan']['ids'] as $idPimpinan)
            <input type="hidden" name="pimpinan_satker[]" value="{{ $idPimpinan }}">
          @endforeach
          <p class="dm-sub">Satker yang dipimpin: <b>{{ $j['pimpinan']['semua'] ? 'Semua Satker' : $unit->whereIn('id', $j['pimpinan']['ids'])->map->namaPendek()->join(', ') }}</b></p>
        @endif
        <div class="dm-ringkas dm-ringkas-jendela">
          <span><b>{{ $siapImpor }}</b> siap ditambahkan sebagai {{ $peranImpor->pendek() }}</span>
          @if($lewatImpor > 0)<span class="awas"><x-ikon n="AlertTriangle" :s="13" /> {{ $lewatImpor }} dilewati</span>@endif
          <a class="btn btn-s" href="{{ $ke(['jendela' => 'impor', 'nip' => $j['teks'], 'peran_baru' => $peranImpor->value, 'pimpinan_satker' => $j['pimpinan']['ids'], 'pimpinan_semua' => $j['pimpinan']['semua'] ? 1 : null]) }}"><x-ikon n="ArrowLeft" :s="13" /> Ubah daftar</a>
        </div>
        <ul class="dm-impor">
          @foreach($j['hasil'] as $b)
            <li class="{{ $statusImpor[$b['status']][0] }}">
              <span class="dm-orang">
                <b>{{ $b['pegawai']['nama'] ?? 'NIP '.DirektoriIrm::nipTampil($b['nip']) }}</b>
                <span class="mono">NIP {{ DirektoriIrm::nipTampil($b['nip']) }}</span>
                @if($b['pegawai'])<span>{{ $b['pegawai']['jabatan'] }} · {{ $b['pegawai']['unitNama'] }}</span>@endif
              </span>
              <span class="dm-impor-status">
                {{ $statusImpor[$b['status']][1] }}
                @if($b['status'] === 'sudah')<span class="dm-sub">{{ $b['lama']->peran->pendek() }}{{ $b['lama']->peran === P::SATKER ? ' · '.$b['lama']->satker?->namaPendek() : '' }}</span>@endif
                @if($b['status'] === 'email')<span class="dm-sub">{{ $b['lama']->name }}</span>@endif
              </span>
            </li>
          @endforeach
        </ul>
      </x-dm-jendela>
    @endif
    @break

  @case('akun')
    @php $a = $j['x']; @endphp
    @if(! $alasanTolak($a))
      <x-dm-jendela :judul="'Ubah peran '.$a->name" ikon="Users" :tutup="$tutup" lebar
        :sub="'NIP '.DirektoriIrm::nipTampil($a->nip).' · '.$a->email" :aksi="route('master.pengguna.simpan', $a)" tombol="Simpan peran"
        :data-pastikan="json_encode(['judul' => 'Ubah peran '.$a->name.'?', 'ket' => 'Semua sesi akan diakhiri. User perlu masuk ulang dengan hak akses baru.', 'tombol' => 'Ya, ubah', 'nada' => 'jingga', 'balik' => false])">
        <input type="hidden" name="kembali" value="{{ $kembali }}">
        <input type="hidden" name="perans_dikirim" value="1">
        <p class="dm-sub">Pilih peran yang boleh digunakan. User bekerja dengan satu peran aktif setiap saat.</p>
        @if($a->peran === P::SATKER)<p class="dm-sub">Peran Satuan kerja untuk {{ $a->satker?->namaPendek() }} tetap mengikuti penetapan penanggung jawab.</p>@endif
        @include('data-master.pilihan-peran', ['nilai' => old('perans', array_map(fn ($p) => $p->value, $a->semuaPeran())), 'bolehBeri' => $bolehBeri, 'banyak' => true])
        @include('data-master.cakupan-pimpinan', ['cakupanPimpinan' => $a->cakupanPimpinan()])
      </x-dm-jendela>
    @endif
    @break

  @case('detail')
    @php
      $a = $j['x'];
      $tolak = $alasanTolak($a);
      $terakhir = $alasanTerakhir($a);
      $sesi = $j['sesi'];
      $pastikanSesi = ['judul' => 'Akhiri sesi '.$a->name.'?',
        'ket' => 'Sesi pada '.$sesi->count().' perangkat akan diakhiri. User perlu masuk ulang lewat SSO; akunnya tetap aktif.',
        'tombol' => 'Ya, akhiri sesi', 'nada' => 'jingga', 'balik' => false];
      $pastikanNonaktif = ['judul' => 'Nonaktifkan akun '.$a->name.'?',
        'ket' => 'User tidak bisa masuk dan semua sesinya langsung diakhiri. Riwayat aktivitas tetap tersimpan. Akun bisa diaktifkan kembali kapan saja.',
        'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga', 'balik' => false];
    @endphp
    <x-dm-jendela :judul="$a->name" ikon="UserRound" lebar :tutup="$tutup"
      :sub="'NIP '.DirektoriIrm::nipTampil($a->nip).' · '.$a->email">
      <div class="dm-detail-kep">
        <span class="rupa" aria-hidden="true">{{ Rangka::inisial($a->name) }}</span>
        <span class="dm-orang">
          <b>{{ $a->name }}@if($a->is($saya))<span class="dm-anda">Anda</span>@endif</b>
          <span>{{ $a->jabatan ?: '—' }}</span>
        </span>
        {!! $keadaan($a->aktif, true) !!}
      </div>
      <dl class="dm-fakta">
        <div><dt>Peran</dt><dd>{{ implode(' · ', array_map(fn ($p) => $p->pendek(), $a->semuaPeran())) }}@if($a->peran === P::SATKER)<span class="dm-sub">{{ $a->satker?->namaPendek() }}</span>@endif</dd></div>
        <div><dt>Terakhir masuk</dt><dd>{{ $a->terakhir_masuk_pada ? Tampil::waktuLog($a->terakhir_masuk_pada) : 'Belum pernah' }}</dd></div>
        <div><dt>Aktivitas {{ PusatData::HARI_SEPI }} hari terakhir</dt><dd class="mono">{{ $j['perbuatan30'] }}</dd></div>
        <div><dt>Sesi aktif</dt><dd class="mono">{{ $sesi->count() }}</dd></div>
      </dl>
      @if($tolak)<p class="dm-sub dm-catatan"><x-ikon n="Lock" :s="12" /> {{ $tolak }}</p>@endif

      <section class="dm-detail-bag" aria-label="Hak dan cakupan akses">
        <h4>Hak dan cakupan akses</h4>
        <p class="dm-sub">Hak akses mengikuti satu peran aktif yang dipilih user. Satuan kerja dan Pimpinan hanya membuka bagian unit yang ditetapkan.</p>
        @if($a->punyaPeran(P::PIMPINAN))
          <p class="dm-sub">Satker yang dipimpin: <b>{{ $a->daftarSatkerDipimpin() ?: 'Belum ditentukan — akses data Pimpinan belum tersedia' }}</b></p>
        @endif
        @if($a->peran === P::SATKER)
          <p class="dm-sub">Membatasi izin masuk tidak menonaktifkan satuan kerja dan tidak menghapus pekerjaan atau riwayatnya. Pergantian penanggung jawab dilakukan di Satuan kerja.</p>
        @endif
        <details class="dm-hak-detail">
          <summary>Lihat hak tiap peran</summary>
          @foreach($a->semuaPeran() as $p)<b>{{ $p->pendek() }}</b><ul class="dm-hak">@foreach($p->hak() as $hak)<li>{{ $hak }}</li>@endforeach</ul>@endforeach
        </details>
      </section>

      <section class="dm-detail-bag" aria-label="Perangkat yang sedang masuk">
        <h4>Perangkat yang sedang masuk <span class="dm-n">{{ $sesi->count() }}</span></h4>
        @if($sesi->count())
          <ul class="dm-sesi">
            @foreach($sesi as $s)
              <li>
                <x-ikon :n="$s->perangkat['ponsel'] ? 'Smartphone' : 'Monitor'" :s="16" />
                <span><b>{{ $s->perangkat['nama'] }}</b><span>{{ $s->ip }} · aktif {{ Tampil::lalu($s->detik, $s->waktu) }}</span></span>
              </li>
            @endforeach
          </ul>
        @else
          <p class="dm-sub">Tidak ada perangkat yang sedang masuk.</p>
        @endif
        @if($sesi->count() && ! $a->is($saya))
          <form method="post" action="{{ route('master.pengguna.sesi', $a) }}">
            @csrf
            <button type="submit" class="btn btn-s" data-pastikan='@json($pastikanSesi)'><x-ikon n="LogOut" :s="13" /> Akhiri semua sesi</button>
          </form>
        @endif
        @if($a->is($saya))<p class="dm-sub">Sesi Anda sendiri diatur di Profil → Keamanan.</p>@endif
      </section>

      <section class="dm-detail-bag" aria-label="Aktivitas terakhir">
        <h4>Aktivitas terakhir</h4>
        @if($j['aktivitas']->count())
          <ol class="dm-jejak">
            @foreach($j['aktivitas'] as $l)
              <li><span class="dm-waktu">{{ Tampil::waktuLog($l->waktu) }}</span><span class="dm-ubah">{{ $l->ringkasan }}</span></li>
            @endforeach
          </ol>
        @else
          <p class="dm-sub">Belum ada aktivitas selain masuk dan keluar.</p>
        @endif
      </section>

      <section class="dm-detail-bag" aria-label="Riwayat hak akses">
        <h4>Riwayat hak akses</h4>
        @if($j['hak']->count())
          <ol class="dm-jejak">
            @foreach($j['hak'] as $l)
              <li>
                <span class="dm-waktu">{{ Tampil::waktuLog($l->waktu) }}</span>
                <span><span class="dm-ubah">{{ $l->ringkasan }}</span>@include('bagian.beda-log', ['rincian' => $l->rincian])<span class="dm-sub">oleh {{ $l->nama ?: 'Sistem (otomatis)' }}</span></span>
              </li>
            @endforeach
          </ol>
        @else
          <p class="dm-sub">Belum ada perubahan hak akses yang tercatat untuk akun ini.</p>
        @endif
      </section>

      <x-slot:kaki>
        @if($a->peran === P::SATKER && $a->satker)
          <a class="btn" href="{{ $keTab('unit', ['kartu' => 'unit-'.$a->satker_id]) }}"><x-ikon n="Building2" :s="14" /> Satuan kerja</a>
        @endif
        @if(! $tolak)
          <a class="btn" href="{{ $ke(['jendela' => 'akun-'.$a->id]) }}"><x-ikon n="Pencil" :s="14" /> Ubah peran</a>
        @endif
        @if(! $tolak)
          <form method="post" action="{{ route('master.pengguna.saklar', $a) }}" style="display:contents">
            @csrf
            <input type="hidden" name="kembali" value="{{ $kembali }}">
            <button type="submit" class="btn" @disabled($a->aktif && $terakhir)
              @if($a->aktif && $terakhir) title="{{ $terakhir }}" @endif
              @if($a->aktif && ! $terakhir) data-pastikan='@json($pastikanNonaktif)' @endif>{{ $a->aktif ? 'Batasi izin masuk' : 'Izinkan masuk' }}</button>
          </form>
        @endif
        <a class="btn btn-p" href="{{ $tutup }}">Tutup</a>
      </x-slot:kaki>
    </x-dm-jendela>
    @break

  @case('temuan')
    @php $k = $j['x']; $sumber = $k ? $k->sumber->value : $j['sumber']; @endphp
    <x-dm-jendela :judul="$k ? 'Ubah kategori temuan' : 'Tambah kategori temuan'" ikon="FileCheck2" :tutup="$tutup" wajib
      :aksi="$k ? route('master.temuan.simpan', $k) : route('master.temuan.tambah')">
      @if(! $k)
        <div class="fld">
          <span>Sumber laporan<span class="wajib">*</span></span>
          <div class="dm-pilih">
            @foreach(['LHP', 'LHA'] as $s)
              <label class="dm-opsi{{ old('sumber', $sumber) === $s ? ' pilih' : '' }}">
                <input type="radio" name="sumber" value="{{ $s }}" @checked(old('sumber', $sumber) === $s)>
                <b>{{ $s }}</b><span>{{ SumberLaporan::from($s)->nama() }}</span>
              </label>
            @endforeach
          </div>
        </div>
      @else
        <p class="dm-sub" style="margin-top:0">Sumber laporan: <b>{{ $sumber }}</b> · {{ SumberLaporan::from($sumber)->nama() }}</p>
      @endif
      <label class="fld" style="margin-bottom:0">
        <span>Nama kategori<span class="wajib">*</span></span>
        <input type="text" name="nama" value="{{ old('nama', $k?->nama) }}" required autofocus>
      </label>
    </x-dm-jendela>
    @break

  @case('intern')
    @php $k = $j['x']; @endphp
    <x-dm-jendela :judul="$k ? 'Ubah kategori internal' : 'Tambah kategori internal'" ikon="ListChecks" :tutup="$tutup" wajib
      :aksi="$k ? route('master.simpan', $k) : route('master.tambah')">
      <label class="fld" style="margin-bottom:0">
        <span>Nama kategori<span class="wajib">*</span></span>
        <input type="text" name="nama" value="{{ old('nama', $k?->nama) }}" required autofocus>
      </label>
    </x-dm-jendela>
    @break

  @case('sifat')
    @php $x = $j['x']; @endphp
    <x-dm-jendela :judul="$x ? 'Ubah sifat rekomendasi' : 'Tambah sifat rekomendasi'" ikon="Wallet" :tutup="$tutup" wajib
      :aksi="$x ? route('master.sifat.simpan', $x) : route('master.sifat.tambah')">
      <label class="fld">
        <span>Nama sifat<span class="wajib">*</span></span>
        <input type="text" name="nama" value="{{ old('nama', $x?->nama) }}" required autofocus>
      </label>
      <input type="hidden" name="uang" value="0">
      <label class="centang">
        <input type="checkbox" name="uang" value="1" @checked(old('uang', $x?->perlu_nilai))>
        Menuntut penyetoran uang
      </label>
    </x-dm-jendela>
    @break

  @case('bentuk')
    @php $x = $j['x']; $terkunci = $j['n'] > 0; @endphp
    <x-dm-jendela :judul="$x ? 'Ubah bentuk tindak lanjut' : 'Tambah bentuk tindak lanjut'" ikon="ClipboardCheck" :tutup="$tutup" wajib
      :aksi="$x ? route('master.bentuk.simpan', $x) : route('master.bentuk.tambah')">
      <label class="fld">
        <span>Nama bentuk<span class="wajib">*</span></span>
        <input type="text" name="nama" value="{{ old('nama', $x?->nama) }}" required @if($terkunci) readonly @else autofocus @endif>
        @if($terkunci)<span class="hint">Sudah dipakai {{ $j['n'] }} tindak lanjut, jadi namanya terkunci.</span>@endif
      </label>
      <label class="fld" style="margin-bottom:0">
        <span>Dokumen usulan</span>
        <textarea name="dokumen" rows="6" @if($terkunci) autofocus @endif placeholder="Satu dokumen satu baris">{{ old('dokumen', implode("\n", (array) ($x?->dokumen ?? []))) }}</textarea>
        <span class="hint">Mengisi otomatis "Dokumen yang diminta" saat bentuk ini dipilih.</span>
      </label>
    </x-dm-jendela>
    @break

  @case('banyak')
    @php
      $d = $j['daftar'];
      $judulBanyak = ['temuan' => 'Tambah beberapa kategori temuan', 'intern' => 'Tambah beberapa kategori internal', 'sifat' => 'Tambah beberapa sifat rekomendasi',
        'bentuk' => 'Tambah beberapa bentuk tindak lanjut'][$d];
      $ikonBanyak = ['temuan' => 'FileCheck2', 'intern' => 'ListChecks', 'sifat' => 'Wallet', 'bentuk' => 'ClipboardCheck'][$d];
    @endphp
    <x-dm-jendela :judul="$judulBanyak" :ikon="$ikonBanyak" :tutup="$tutup" wajib tombol="Tambahkan" :aksi="route('master.banyak', $d)"
      :sub="$d === 'temuan' ? 'Untuk sumber laporan '.$j['sumber'].'.' : ($d === 'bentuk'
        ? 'Satu nama satu baris. Dokumen usulannya diisi lewat Ubah.' : 'Satu nama satu baris. Yang namanya sudah ada dilewati.')">
      @if($d === 'temuan')<input type="hidden" name="sumber" value="{{ $j['sumber'] }}">@endif
      <label class="fld"@if($d !== 'sifat') style="margin-bottom:0"@endif>
        <span>Nama-nama<span class="wajib">*</span></span>
        <textarea name="nama" rows="7" required autofocus data-hitung-baris placeholder="Satu nama satu baris">{{ old('nama') }}</textarea>
        <span class="hint"><span data-baris-terbaca>Nama yang sudah terdaftar akan dilewati.</span></span>
      </label>
      @if($d === 'sifat')
        <label class="centang">
          <input type="checkbox" name="uang" value="1" @checked(old('uang'))>
          Semuanya menuntut penyetoran uang
        </label>
      @endif
    </x-dm-jendela>
    @break

  @case('gabung')
    @php
      $d = $j['daftar'];
      $dari = $j['x'];
      $n = $j['n'];
      $satuan = ['sifat' => 'rekomendasi', 'bentuk' => 'tindak lanjut'][$d] ?? 'temuan';
      $sebut = ['temuan' => 'kategori temuan', 'intern' => 'kategori internal', 'sifat' => 'sifat rekomendasi', 'bentuk' => 'bentuk tindak lanjut'][$d];
      $ikonGabung = ['temuan' => 'FileCheck2', 'intern' => 'ListChecks', 'sifat' => 'Wallet', 'bentuk' => 'ClipboardCheck'][$d];
    @endphp
    <x-dm-jendela :judul="'Gabungkan “'.$dari->nama.'”'" :ikon="$ikonGabung" lebar wajib :tutup="$tutup" tombol="Gabungkan"
      :bisa="$j['pilihan']->isNotEmpty()" :aksi="route('master.gabung', [$d, $dari->id])"
      :sub="$n ? $n.' '.$satuan.' yang memakainya dipindahkan ke '.$sebut.' lain.' : 'Belum dipakai '.$satuan.' mana pun.'"
      :data-pastikan="json_encode([
        'judul' => 'Gabungkan “'.$dari->nama.'” ke “{tujuan}”?',
        'ket' => ($n ? $n.' '.$satuan.' yang memakai “'.$dari->nama.'” langsung pindah ke “{tujuan}”. ' : '')
          .'“'.$dari->nama.'” lalu dinonaktifkan. Yang sudah pindah tidak bisa dikembalikan dari sini.',
        'tombol' => 'Ya, gabungkan', 'nada' => 'jingga', 'balik' => false], JSON_UNESCAPED_UNICODE)">
      <div class="fld" style="margin-bottom:0">
        <span>Gabungkan ke<span class="wajib">*</span></span>
        <div class="dm-peran-pilih" role="radiogroup" aria-label="Kategori tujuan">
          @foreach($j['pilihan'] as $y)
            <label class="dm-opsi">
              <input type="radio" name="ke" value="{{ $y->id }}" required>
              <b data-nama-tujuan>{{ $y->nama }}</b>
            </label>
          @endforeach
        </div>
        @if($d === 'sifat')<span class="hint">Hanya sifat dengan keterangan menuntut penyetoran yang sama yang ditawarkan.</span>@endif
      </div>
    </x-dm-jendela>
    @break
@endswitch
