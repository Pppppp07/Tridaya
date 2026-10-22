@extends(\App\Support\Halaman::bagian() ? 'rangka-bagian' : 'rangka')
@section('judul', 'Review data historis')
@section('isi')
@php
  use App\Support\Historis\PetaKolom;
  use App\Support\Historis\ReviewHistoris;

  $ikut = $semua->where('ikut', true);
  $siap = $ikut->where('siap', true)->count();
  $perlu = $ikut->count() - $siap;
  $hasil = collect($draf->isian['hasil'])->keyBy(fn ($h) => $h['sheet'].'|'.$h['baris']);
  $kembar = $semua->sum(fn ($r) => count($r['kembar'] ?? []));
  $bisaDitandai = $ikut->filter(fn ($r) => ! $r['errors'] && ! $r['diperiksa'])->count();
  /* Alamat daftar dengan saringan yang sedang berlaku, ditimpa sebagian. */
  $tautan = fn (array $ubah = []) => route('laporan.historis.review', array_filter(
    array_merge(['draf' => $draf, 'filter' => $filter, 'kolom' => $kolom, 'keadaan' => $keadaan], $ubah), fn ($v) => $v !== '' && $v !== null));
  $urutKeadaan = ['terbaca', 'aturan', 'diubah', 'kosong', 'tidak_ada', 'tidak_terbaca'];
@endphp
<div class="body hrv">
  <div class="hrv-head"><div><a class="hrv-back" href="{{ route('laporan.historis.index') }}">← Data historis</a><h2>{{ $draf->diimpor_pada ? 'Hasil impor' : 'Review data historis' }}</h2><p>{{ $draf->nama_berkas }}</p></div><a class="btn btn-s" href="{{ route('laporan.historis.sumber', $draf) }}" download><x-ikon n="Download" :s="15" /> File asli</a></div>
  <ol class="hrv-steps" aria-label="Tahap impor"><li>1 · Upload file</li><li @if(!$draf->diimpor_pada) aria-current="step" @endif>2 · Review data</li><li @if($draf->diimpor_pada) aria-current="step" @endif>3 · Konfirmasi & impor</li></ol>
  @if($draf->diimpor_pada)
    <section class="hrv-card hrv-success"><h3>Data sudah masuk ke sistem</h3><p>{{ $hasil->count() }} penugasan diimpor pada {{ $draf->diimpor_pada->format('d M Y · H:i') }}. Review dan sumber asli tetap tersimpan.</p><a class="btn btn-s" href="{{ route('laporan.index') }}">Buka Daftar laporan →</a></section>
  @else
    <div class="hrv-counts"><div><strong>{{ $semua->count() }}</strong><span>Baris terbaca</span></div><div><strong>{{ $siap }}</strong><span>Siap diimpor</span></div><div><strong>{{ $perlu }}</strong><span>Perlu review</span></div><div><strong>{{ $semua->where('ikut', false)->count() }}</strong><span>Dikeluarkan</span></div></div>
    <p class="hint">Satu baris adalah satu penugasan Satker. Ref IDT yang sama dapat digabung menjadi satu rekomendasi setelah identitasnya sesuai. Data yang sudah ada tidak ditimpa.@if($kembar) {{ $kembar }} baris yang isinya sama persis dengan baris lain sudah digabung.@endif</p>
  @endif

  {{-- Bahan diskusi: kolom sistem mana yang terbaca, kosong, atau tidak terbaca dari file. --}}
  <section class="hrv-card hrv-ringkas" id="ringkasan-kolom">
    <h3>Kolom yang dibaca sistem</h3>
    <p class="hint">Tiap kolom sistem dibandingkan dengan kolom file asalnya. Klik angka untuk melihat barisnya, lalu klik baris untuk membandingkan isinya berdampingan.</p>
    <div class="tw"><table class="hrv-ringkas-tabel">
      <thead><tr><th scope="col">Kolom sistem</th><th scope="col">Diambil dari</th>@foreach($urutKeadaan as $kd)<th scope="col" class="num">{{ PetaKolom::KEADAAN[$kd]['nama'] }}</th>@endforeach</tr></thead>
      <tbody>
        @foreach($ringkas['kolom'] as $k => $x)
          @php $bermasalah = $x['jumlah']['tidak_terbaca'] + ($x['wajib'] ? $x['jumlah']['kosong'] + $x['jumlah']['tidak_ada'] : 0); @endphp
          <tr @class(['ada-masalah' => $bermasalah > 0, 'dipilih' => $kolom === $k])>
            <th scope="row">{{ $x['label'] }}@if($x['wajib'])<span class="wajib" aria-hidden="true">*</span>@endif</th>
            <td class="dari">{{ $x['dari'] ? implode(', ', $x['dari']) : 'Tidak ada di file' }}@if($x['cara'])<span class="hrv-sub">{{ $x['cara'] }}</span>@endif</td>
            @foreach($urutKeadaan as $kd)
              <td class="num">
                @if($x['jumlah'][$kd])
                  <a href="{{ $tautan(['kolom' => $k, 'keadaan' => $kd, 'filter' => '', 'page' => null]) }}#daftar-baris" data-di-tempat
                    @class(['n', 'warn' => $kd === 'tidak_terbaca' || ($x['wajib'] && in_array($kd, ['kosong', 'tidak_ada'], true))])
                    title="Lihat baris dengan {{ mb_strtolower($x['label']) }} {{ mb_strtolower(PetaKolom::KEADAAN[$kd]['nama']) }}">{{ $x['jumlah'][$kd] }}</a>
                @else
                  <span class="nol" aria-hidden="true">–</span><span class="sr-only">0</span>
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table></div>
    <p class="hint"><span class="wajib">*</span> wajib diisi sebelum impor. Kolom wajib yang kosong ikut ditandai di daftar baris.</p>
    @if($ringkas['lain'])
      <details class="hrv-lain"><summary>Kolom file yang tidak disimpan ({{ count($ringkas['lain']) }})</summary>
        <p class="hint">Kolom ini ada di file tetapi tidak disimpan sendiri: isinya dihitung aplikasi dari kolom lain, atau memang tidak dibutuhkan. Kolom {{ implode(', ', \App\Support\Historis\BacaExcel::KOLOM_TIDAK_DIPAKAI) }} tidak dibaca sama sekali.</p>
        <div class="tw"><table><thead><tr><th scope="col">Kolom file</th><th scope="col" class="num">Baris terisi</th><th scope="col">Keterangan</th></tr></thead><tbody>
          @foreach($ringkas['lain'] as $x)<tr><td>{{ $x['judul'] }}</td><td class="num">{{ $x['terisi'] }}</td><td>{{ $x['ket'] ?: 'Belum dipetakan.' }}</td></tr>@endforeach
        </tbody></table></div>
      </details>
    @endif
  </section>

  @if(!$draf->diimpor_pada)
    @if($bisaDitandai)
      <form method="post" action="{{ route('laporan.historis.periksa', $draf) }}" class="hrv-tandai">@csrf<input type="hidden" name="versi" value="{{ $draf->versi }}">
        <button class="btn btn-s" type="submit"><x-ikon n="Check" :s="15" /> Tandai {{ $bisaDitandai }} baris tanpa masalah sudah diperiksa</button>
        <span class="hint">Baris yang masih perlu diperbaiki tidak ikut ditandai.</span>
      </form>
    @endif
    <details class="hrv-card hrv-batch"><summary>Isi tanggal bersama untuk beberapa baris</summary>
      <form method="post" action="{{ route('laporan.historis.massal', $draf) }}" class="hrv-grid">@csrf<input type="hidden" name="versi" value="{{ $draf->versi }}">
        <label class="fld hrv-wide"><span>Nomor laporan</span><select name="nomor" required><option value="">Pilih laporan</option><option value="*">Semua laporan</option>@foreach($semua->pluck('data.nomor')->unique() as $nomor)<option value="{{ $nomor }}">{{ $nomor }}</option>@endforeach</select></label>
        <label class="fld"><span>Tanggal surat</span><input type="date" name="tgl_surat"></label><label class="fld"><span>Tanggal diterima</span><input type="date" name="tgl_terima"></label>
        <label class="fld" for="hrv-acuan-bersama"><span>Tanggal acuan data <x-info teks="Tanggal saat posisi, status, dan nilai pada data historis ini diketahui berlaku. Bukan tanggal upload atau tanggal putusan pemeriksa." /></span><input id="hrv-acuan-bersama" aria-label="Tanggal acuan data" type="date" name="tanggal_acuan"></label>
        <div class="hrv-wide"><p class="hint">Hanya tanggal yang diisi yang diterapkan pada laporan pilihan. Baris yang berubah perlu diperiksa kembali.</p><button class="btn btn-s" type="submit">Terapkan tanggal</button></div>
      </form>
    </details>
    <nav class="hrv-filters" aria-label="Filter review">@foreach(['' => 'Semua', 'kolom' => 'Ada kolom bermasalah', 'perlu' => 'Perlu review', 'siap' => 'Siap', 'duplikat' => 'Ref IDT sudah ada', 'keluar' => 'Dikeluarkan'] as $k => $nama)<a class="btn btn-s {{ $filter === $k && $kolom === '' ? 'btn-p' : '' }}" href="{{ route('laporan.historis.review', array_filter(['draf' => $draf, 'filter' => $k])) }}" data-di-tempat @if($filter === $k && $kolom === '') aria-current="page" @endif>{{ $nama }}</a>@endforeach</nav>
  @endif
  @if($kolom !== '')
    <p class="hrv-saring">Menampilkan {{ $jumlahTampil }} baris dengan <b>{{ ReviewHistoris::LABEL[$kolom] }}</b> {{ $keadaan !== '' ? mb_strtolower(PetaKolom::KEADAAN[$keadaan]['nama']) : 'bermasalah' }}. <a href="{{ $tautan(['kolom' => '', 'keadaan' => '', 'page' => null]) }}" data-di-tempat>Tampilkan semua baris</a></p>
  @endif
  {{-- Filter, angka ringkasan, dan halaman berikutnya menukar isi halaman di tempat (simtlhp.js). --}}
  <div class="tw hrv-table" id="daftar-baris" data-ganti-di-tempat><table><thead><tr><th>Sumber</th><th>Laporan & rekomendasi</th><th>Satker</th><th>Kolom</th><th>Status review</th><th></th></tr></thead><tbody>
    @forelse($rows as $row)
      @php
        $data = $row['data'];
        $asli = \App\Support\Historis\BacaExcel::kunci($row['asli']);
        $h = $hasil->get($row['sheet'].'|'.$row['baris']);
        $tak = array_keys(array_filter($row['masalah'], fn ($x) => $x['keadaan'] === 'tidak_terbaca'));
        $kosong = array_values(array_diff(array_keys($row['masalah']), $tak));
        $nama = fn ($daftar) => implode(', ', array_map(fn ($k) => $row['masalah'][$k]['label'], $daftar));
        $bandingkan = route('laporan.historis.baris', [$draf, $row['index']]);
      @endphp
      <tr data-href="{{ $bandingkan }}"><td class="hrv-source">{{ $row['sheet'] }}<span>Baris {{ $row['baris'] }}{{ ($row['kembar'] ?? []) ? ' · +'.count($row['kembar']).' kembar' : '' }}</span></td><td><strong>{{ $data['kode'] ?: 'Ref IDT belum terisi' }}</strong><span class="hrv-sub">{{ $data['nomor'] }}</span><span class="hrv-excerpt">{{ $data['uraian'] }}</span></td><td>{{ $row['satker_nama'] ?: ($asli['satker baru'] ?? $asli['satker lama'] ?? $data['kode_satker']) }}<span class="hrv-sub">{{ $row['satker_nama'] ? 'Kode '.($row['satker_kode'] ?: 'belum tersedia') : 'Belum dipetakan ke Data Master' }}</span></td>
        <td class="hrv-kolom">
          @if($tak)<span class="warn"><b>Tidak terbaca:</b> {{ $nama($tak) }}</span>@endif
          @if($kosong)<span><b>Kosong:</b> {{ $nama($kosong) }}</span>@endif
          @if(!$row['masalah'])<span class="hrv-badge ok">Kolom lengkap</span>@endif
        </td>
        <td>@if($draf->diimpor_pada)<span class="hrv-badge {{ $h ? 'ok' : '' }}">{{ $h ? 'Diimpor' : 'Dikeluarkan' }}</span>@elseif(!$row['ikut'])<span class="hrv-badge">Dikeluarkan</span>@elseif($row['duplikat_id'])<span class="hrv-badge bad">Ref IDT sudah ada</span>@elseif($row['errors'])<span class="hrv-badge bad">{{ count($row['errors']) }} perlu diperbaiki</span>@elseif(!$row['diperiksa'])<span class="hrv-badge warn">Belum diperiksa</span>@else<span class="hrv-badge ok">Siap diimpor</span>@endif</td>
        <td><a class="btn btn-s" href="{{ $bandingkan }}">{{ $draf->diimpor_pada ? 'Detail' : 'Bandingkan' }} →</a>@if($h)<a class="hrv-link" href="{{ route('rekomendasi.show', $h['rekomendasi_id']) }}">Buka rekomendasi</a>@endif</td></tr>
    @empty<tr><td colspan="6" class="hrv-empty">Tidak ada baris pada filter ini.</td></tr>@endforelse
  </tbody></table></div>
  @if($jumlahTampil > 30)<nav class="hrv-pagination" aria-label="Halaman review">@if($page > 1)<a class="btn btn-s" href="{{ $tautan(['page' => $page - 1]) }}#daftar-baris" data-di-tempat>← Sebelumnya</a>@endif<span>{{ $page }} / {{ ceil($jumlahTampil / 30) }}</span>@if($page * 30 < $jumlahTampil)<a class="btn btn-s" href="{{ $tautan(['page' => $page + 1]) }}#daftar-baris" data-di-tempat>Berikutnya →</a>@endif</nav>@endif
  @if(!$draf->diimpor_pada)
    <section class="hrv-card hrv-confirm"><h3>Konfirmasi hasil review</h3><p>{{ $siap }} dari {{ $ikut->count() }} penugasan pilihan siap dimasukkan. @if($perlu)Selesaikan review atau keluarkan baris yang belum siap.@endif</p>
      <form method="post" action="{{ route('laporan.historis.impor', $draf) }}">@csrf<input type="hidden" name="versi" value="{{ $draf->versi }}">
        <label class="hrv-check"><input type="checkbox" name="konfirmasi" value="1" required @disabled($perlu || !$siap)><span>Saya sudah memeriksa identitas, nilai, status, posisi berkas, dan tanggal pada seluruh data pilihan.</span></label>
        <button class="btn btn-p" type="submit" @disabled($perlu || !$siap)>Konfirmasi & impor {{ $ikut->count() }} penugasan</button>
      </form><p class="hint">Pemeriksaan duplikat dan perubahan data dijalankan lagi saat konfirmasi. Jika gagal, seluruh impor dari konfirmasi ini dibatalkan.</p>
    </section>
  @endif
</div>
@endsection
