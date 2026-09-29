@extends('rangka')
@section('judul', 'Rekomendasi')
@section('isi')
@php
  use App\Enums\PeranPengguna as P;
  use App\Enums\SumberLaporan;
  use App\Support\Tampil;
  use App\Support\TindakLanjutRingkas;

  $u = auth()->user();
  $peran = $u->peran;

  /* Sekali tekan mengurutkan menurut kolom itu; tekan lagi membalik arahnya;
     sekali lagi kembali ke urutan bawaan. Nilai tombolnya keadaan berikutnya. */
  $berikut = fn ($k) => $urut !== $k ? "{$k}:naik" : ($arah === 'naik' ? "{$k}:turun" : 'mendesak');
  $judulUrut = fn ($k, $nama) => $urut !== $k ? 'Urutkan menurut '.mb_strtolower($nama)
    : ($arah === 'naik' ? 'Tekan lagi untuk membalik urutannya' : 'Tekan lagi untuk kembali ke urutan bawaan');
@endphp

<div class="body">
  {{-- Seluruh filter satu formulir GET: alamatnya bisa disimpan dan dibagi,
       dan semuanya tetap bekerja tanpa skrip. Isian tersembunyi di depan
       supaya tombol yang ditekan menimpanya. `data-ganti-di-tempat` (29 Sep):
       dengan skrip, hasilnya ditukar di tempat tanpa memuat ulang halaman. --}}
  <form id="filter" method="get" action="{{ route('rekomendasi.index') }}" class="kepalatabel" data-ganti-di-tempat>
    {{-- Tombol bawaan untuk Enter di kotak cari: tanpa nama, jadi tidak
         membawa pilihan apa pun (29 Sep). Tanpa ini browser memakai tombol
         kirim pertama — keping "Semua" jenis laporan — dan pilihan LHP/LHA
         yang sedang menyala ikut terlepas. --}}
    <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Cari</button>
    <input type="hidden" name="keadaan" value="{{ $keadaanKini ?? 'semua' }}">
    <input type="hidden" name="urut" value="{{ $urut === 'mendesak' ? 'mendesak' : $urut.':'.$arah }}">

    <div class="pilihgrup" role="group" aria-label="Jenis laporan">
      @foreach(['semua' => 'Semua', 'LHP' => 'LHP', 'LHA' => 'LHA'] as $k => $n)
        <button type="submit" name="lingkup" value="{{ $k }}" aria-pressed="{{ $lingkup === $k ? 'true' : 'false' }}"
          title="{{ $k === 'semua' ? 'Kedua jenis laporan sekaligus — dipakai untuk monev' : 'Hanya '.SumberLaporan::from($k)->nama().' dari '.SumberLaporan::from($k)->penerbit() }}">
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
      {{-- Filter satuan kerja tidak diberikan kepada satuan kerja: baginya
           ia tidak memfilter apa pun, dan isinya membocorkan daftar satuan
           kerja lain. --}}
      @if($peran !== P::SATKER)
        <select name="sk" data-kirim>
          <option value="semua">Semua satuan kerja</option>
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
    </div>

    @if($keadaanKini === 'siptl')
      <div class="hint">
        {{ $hasil->filter->perluUnggahSiptl()->count() }} perlu diunggah
        · {{ $hasil->filter->perluCekBpk()->count() }} menunggu penilaian BPK
        · {{ $hasil->filter->perluKirimUlangBpk()->count() }} ditolak BPK
        <x-info :teks="[
          'Kumpulan pekerjaan yang dikerjakan di SIPTL — aplikasi milik BPK, di luar sistem ini.',
          'Perlu diunggah: berkasnya diunggah di SIPTL, lalu tanggalnya dicatat di sini.',
          'Menunggu penilaian BPK: BPK tidak mengirim pemberitahuan apa pun, jadi statusnya dicek berkala di SIPTL lalu dicatat di sini.',
          'Ditolak BPK: putusannya Belum Sesuai dan sudah tercatat. Yang tersisa mengirim berkasnya ulang ke satuan kerja, lalu mengunggahnya lagi — putusan berikutnya dicatat sesudah itu.',
          'Hanya LHP. LHA berhenti di Inspektorat dan tidak pernah sampai ke BPK.',
        ]" />
      </div>
    @endif
  </form>

  {{-- Kelas k-* menandai kolomnya untuk tata letak menurut lebar layar: di
       layar sempit tiap baris jadi kartu dan kepala kolomnya jadi deretan
       tombol urut (22 Sep). --}}
  <div class="tw daftar-rek">
    <table>
      <thead>
        <tr>
          <th class="num k-no">No</th>
          @foreach([['uraian', 'Uraian'], ['satker', 'Satuan kerja'], ['tenggat', 'Tenggat jawab'], ['kemajuan', 'Kemajuan', TindakLanjutRingkas::KETERANGAN]] as $kol)
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
        @foreach($hasil as $no => $rek)
          @php
            $lap = $rek->temuan->laporan;
            $lewat = $rek->telatTenggat();
            $lewatBatas = $rek->hariLewatPerbaikan();
            $cari = mb_strtolower(implode(' ', [$rek->kode, $rek->temuan->judul, $rek->uraian, $rek->refIdt(),
              $lap->nomor, $rek->refLhp(), $rek->daftarSasaran()->first()?->satker?->namaPendek()]));
          @endphp
          <tr class="bukaan{{ $rek->perluPerhatian() ? ' awas' : '' }}" data-rek="{{ $rek->id }}" data-href="{{ route('rekomendasi.show', $rek) }}"
            data-cari="{{ $cari }}" data-perlu="{{ $rek->perluPerhatian() ? 1 : 0 }}">
            <td class="num mono k-no" style="font-size:12px;color:var(--ink-3)">
              <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>
              <span data-no>{{ $no + 1 }}</span>
            </td>
            <td class="k-uraian">
              <div class="uraian-isi">
                <x-sumber :j="$lap->sumber" />
                <a class="linkbaris" href="{{ route('rekomendasi.show', $rek) }}" style="font-size:13px">{{ $rek->uraian }}</a>
              </div>
            </td>
            <td class="k-satker" style="font-size:12.5px">
              <span class="kepingsatker">
                @foreach($rek->satkerTampil($peran, $u->satker_id) as $s)
                  <span class="keping">{{ $s->namaPendek() }}</span>
                @endforeach
              </span>
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
