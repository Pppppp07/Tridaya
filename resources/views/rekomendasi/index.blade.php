@extends(\App\Support\Halaman::bagian() ? 'rangka-bagian' : 'rangka')
@section('judul', 'Rekomendasi')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;
  use App\Enums\SumberLaporan;
  use App\Support\Tampil;
  use App\Support\TindakLanjutRingkas;

  $u = auth()->user();
  $peran = $u->peranAktif();

  /* Tekan sekali urut, lagi dibalik, lagi kembali ke bawaan. Nilai tombolnya keadaan berikutnya. */
  $berikut = fn ($k) => $urut !== $k ? "{$k}:naik" : ($arah === 'naik' ? "{$k}:turun" : $urutBawaan);
  $urutTanpaArah = in_array($urut, ['mendesak', 'terbaru'], true);
  $judulUrut = fn ($k, $nama) => $urut !== $k ? 'Urutkan menurut '.mb_strtolower($nama)
    : ($arah === 'naik' ? 'Tekan lagi untuk membalik urutannya' : 'Tekan lagi untuk kembali ke urutan bawaan');
@endphp

<div class="body">
  {{-- Seluruh filter satu formulir GET (jalan tanpa skrip). Isian tersembunyi di depan supaya
       tombol yang ditekan menimpanya. `data-ganti-di-tempat`: dengan skrip hasilnya ditukar di
       tempat. --}}
  <form id="filter" method="get" action="{{ route('rekomendasi.index') }}" class="kepalatabel" data-ganti-di-tempat>
    {{-- Tombol bawaan untuk Enter di kotak cari, tanpa nama — supaya pilihan LHP/LHA yang
         menyala tidak terlepas. --}}
    <button type="submit" hidden tabindex="-1" aria-hidden="true">Cari</button>
    <input type="hidden" name="keadaan" value="{{ $keadaanKini ?? 'semua' }}">
    <input type="hidden" name="urut" value="{{ $urutTanpaArah ? $urut : $urut.':'.$arah }}">

    <div class="pilihgrup" role="group" aria-label="Jenis laporan">
      @foreach(['semua' => 'Semua', 'LHP' => 'LHP', 'LHA' => 'LHA'] as $k => $n)
        <button type="submit" name="lingkup" value="{{ $k }}" aria-pressed="{{ $lingkup === $k ? 'true' : 'false' }}"
          title="{{ $k === 'semua' ? 'Rekomendasi dari LHP dan LHA' : 'Rekomendasi dari '.SumberLaporan::from($k)->nama().' yang diterbitkan '.SumberLaporan::from($k)->penerbit() }}">
          {{ $n }}<span class="n">{{ $jumlahJenis[$k] }}</span>
        </button>
      @endforeach
    </div>

    <div class="pilihgrup" role="group" aria-label="Keadaan pekerjaan">
      @foreach($keadaanAda as $k => $v)
        @php $nyala = $keadaanKini === $k; @endphp
        @if(isset($v['khusus']))<span class="pemisah" aria-hidden="true"></span>@endif
        {{-- Saklar, bukan pilihan mati: menekan yang menyala melepasnya. --}}
        <button type="submit" name="keadaan" value="{{ $nyala ? 'semua' : $k }}" aria-pressed="{{ $nyala ? 'true' : 'false' }}"
          @if($nyala) title="Tekan lagi untuk melihat semuanya" @endif>
          @isset($v['ikon'])<x-ikon :n="$v['ikon']" :s="13" />@endisset
          {{ $v['nama'] }}<span class="n">{{ $jumlah[$k] }}</span>
          @if($nyala)<x-ikon n="X" :s="12" class="lepas" />@endif
        </button>
      @endforeach
    </div>

    <div class="barisfilter">
      <span class="cari" style="flex:1;min-width:220px">
        <x-ikon n="Search" :s="14" />
        <input type="text" name="q" value="{{ $q }}" data-filter-langsung
          placeholder="Cari nomor LHP, kode, temuan, rekomendasi, Ref IDT">
      </span>
      {{-- Filter satuan kerja tidak untuk satuan kerja (isinya membocorkan daftar satuan kerja
           lain). --}}
      @if($daftarSatker->count() > 1)
        <select name="sk" data-kirim>
          <option value="semua">{{ $peran->terbatasSatker() ? 'Semua Satker yang dipimpin' : 'Semua satuan kerja' }}</option>
          @foreach($daftarSatker as $s)
            <option value="{{ $s->id }}" @selected((string) $sk === (string) $s->id)>{{ $s->namaPendek() }}</option>
          @endforeach
        </select>
      @endif
      @if($lingkup !== 'LHA')
        <select name="st" data-kirim title="Rangkuman status BPK dari tindak lanjut tiap satuan kerja. Hanya LHP.">
          <option value="semua">Semua rangkuman SIPTL</option>
          @foreach($STATUS as $s)
            <option value="{{ $s->value }}" @selected($st === $s->value)>{{ $s->value }} · {{ $s->pendek() }}</option>
          @endforeach
        </select>
      @endif
      <div style="flex:1"></div>
    </div>

    <div class="hint" data-hitung-tampil>
      <span data-n-tampil>{{ $hasil->count() }}</span> rekomendasi tampil
      <b style="color:var(--bad)" @if(! $perlu) hidden @endif data-perlu> · <span data-n-perlu>{{ $perlu }}</span> perlu perhatian</b>
      @if($hasil->count() !== $daftar->count())<span> · difilter dari {{ $daftar->count() }}</span>@endif
      {{-- Pindah cepat antara dua urutan bawaan; bawaannya diatur di Profil → Pemberitahuan. --}}
      @if($urutTanpaArah)
        <span class="urut-cepat"> · {{ $urut === 'terbaru' ? 'Pembaruan terbaru di atas' : 'Yang mendesak di atas' }} ·
          <button type="submit" name="urut" value="{{ $urut === 'terbaru' ? 'mendesak' : 'terbaru' }}" class="fb-link">{{ $urut === 'terbaru' ? 'Urutkan yang mendesak dulu' : 'Urutkan pembaruan terbaru dulu' }}</button>
          @if($urut === 'terbaru')<x-info :teks="[
            'Paling atas: rekomendasi yang paling baru diperbarui untuk Anda.',
            'Titik merah menandai pembaruan yang belum Anda lihat. Menekan barisnya membuka bagian yang berubah, lalu titiknya hilang.',
            'Urutan bawaan dan titik merah dapat dimatikan di Profil & pengaturan → Pemberitahuan.',
          ]" />@endif
        </span>
      @endif
    </div>

    @if($keadaanKini === 'siptl')
      <div class="hint">
        {{ $hasil->filter->perluUnggahSiptl()->count() }} perlu diunggah
        · {{ $hasil->filter->perluCekBpk()->count() }} menunggu penilaian BPK
        · {{ $hasil->filter->perluKirimUlangBpk()->count() }} ditolak BPK
        <x-info :teks="[
          'Ringkasan pekerjaan SIPTL untuk penugasan LHP pada halaman ini.',
          'Perlu diunggah: verifikasi internal selesai, tanggal unggah belum dicatat. Menunggu penilaian: sudah diunggah, hasil BPK belum dicatat. Ditolak: BPK menyatakan belum sesuai dan berkas perlu diperbaiki.',
          'Setba mencatat tanggal unggah dan hasil BPK dari aplikasi SIPTL; data tidak disinkronkan otomatis.',
        ]" />
      </div>
    @endif
  </form>

  {{-- Kelas k-*: di layar sempit tiap baris jadi kartu dan kepala kolom jadi tombol urut. --}}
  <div class="tw daftar-rek">
    <table>
      <thead>
        <tr>
          @foreach([['keterangan', 'Keterangan'], ['uraian', 'Uraian'], ['tenggat', 'Tenggat jawab'], ['satker', 'Satuan kerja'], ['kemajuan', 'Kemajuan', TindakLanjutRingkas::keterangan($peran, $u->satkerAktifId())]] as $kol)
            @php [$k, $nama] = $kol; $ket = $kol[2] ?? null; @endphp
            <th class="urutkan k-{{ $k }}{{ $urut === $k ? ' aktif' : '' }}{{ $ket ? ' berinfo' : '' }}">
              <button type="submit" form="filter" name="urut" value="{{ $berikut($k) }}" title="{{ $judulUrut($k, $nama) }}">
                {{ $nama }}
                <span class="panahurut" aria-hidden="true">{{ $urut === $k ? ($arah === 'naik' ? '↑' : '↓') : '⇅' }}</span>
              </button>
              @if($ket)<x-info :teks="$ket" />@endif
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($hasil as $rek)
          @php
            $lap = $rek->temuan->laporan;
            $lewat = $rek->telatTenggat();
            $lewatBatas = $rek->hariLewatPerbaikan();
            $cari = mb_strtolower(implode(' ', [$rek->kode, $rek->temuan->judul, $rek->uraian, $rek->refIdt(),
              $lap->nomor, $rek->refLhp(), $rek->asalData(), $rek->daftarSasaran()->pluck('satker')->filter()->unique('id')
                ->map(fn ($s) => $s->nama.' '.$s->namaPendek())->join(' ')]));
            /* Pembaruan yang belum dilihat: titik merah, dan barisnya membuka bagian yang berubah. */
            $belum = $belumDilihat[$rek->id] ?? null;
            $alamat = $belum ? route('rekomendasi.terbaru', $rek) : route('rekomendasi.show', $rek);
            if ($belum) {
              $baru = $belum->first();
              $intiBaru = explode(' — ', preg_split('/\.?\s*Catatan:/u', (string) $baru->aksi)[0])[0];
              $siapaBaru = $peran === P::SATKER ? '' : \App\Support\Pemberitahuan::sebutSatker($baru, $u);
            }
          @endphp
          <tr class="bukaan{{ $rek->perluPerhatian() ? ' awas' : '' }}{{ $belum ? ' ada-baru' : '' }}" data-rek="{{ $rek->id }}" data-href="{{ $alamat }}"
            data-cari="{{ $cari }}" data-perlu="{{ $rek->perluPerhatian() ? 1 : 0 }}">
            <td class="k-keterangan">
              <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>
              <x-asal-data :rek="$rek" />
            </td>
            <td class="k-uraian">
              <div class="uraian-isi">
                <x-sumber :j="$lap->sumber" />
                {{-- Kartu ponsel tanpa kolom Keterangan: penandanya ikut di sini. --}}
                <x-asal-data :rek="$rek" class="hanya-sempit" />
                <a class="linkbaris" href="{{ $alamat }}" style="font-size:13px">{{ $rek->uraian }}</a>
              </div>
              @if($belum)
                <div class="rek-baru" data-rek-baru>
                  <span class="titik-baru" aria-hidden="true"></span>
                  <span><b>Baru</b> · {{ $intiBaru }}{{ $siapaBaru && $siapaBaru !== '—' ? ' · '.$siapaBaru : '' }} · {{ Tampil::tglJam($baru->waktu) }}{{ $belum->count() > 1 ? ' · '.($belum->count() - 1).' lainnya' : '' }}</span>
                </div>
              @endif
            </td>
            <td class="mono k-tenggat" data-label="Tenggat jawab" style="font-size:12px;color:{{ $lewat ? 'var(--bad)' : 'inherit' }}">
              {{ Tampil::tgl($rek->tenggat_jawab) }}
              @if($lewat)
                <div class="lbl" style="color:var(--bad)">{{ Tampil::lamaTelat($rek->lewatTenggat()) }}</div>
              @endif
              @if($lewatBatas > 0)
                <div class="lbl" style="color:var(--bad)">lewat batas perbaikan {{ $lewatBatas }} hari</div>
              @endif
            </td>
            <td class="k-satker" style="font-size:12.5px">
              <span class="kepingsatker">
                @foreach($rek->satkerTampil($peran, $u->satkerAktifId()) as $s)
                  <span class="keping">{{ $s->namaPendek() }}</span>
                @endforeach
              </span>
            </td>
            <td class="k-kemajuan"><x-kemajuan :rek="$rek" /></td>
          </tr>
        @endforeach
        <tr data-kosong @if($hasil->isNotEmpty()) hidden @endif>
          <td colspan="5" style="color:var(--ink-3);padding:22px">
            @if($daftar->isNotEmpty())
              Tidak ada rekomendasi yang sesuai. Ubah kata kunci atau filter.
            @elseif($peran === P::SETBA)
              Belum ada rekomendasi. Rekomendasi muncul di sini sesudah laporannya dicatat lewat Catat laporan baru.
            @else
              Belum ada rekomendasi. Rekomendasi muncul di sini sesudah Setba mencatat laporannya.
            @endif
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection
