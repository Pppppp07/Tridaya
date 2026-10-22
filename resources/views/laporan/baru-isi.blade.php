@php
  use App\Enums\SumberLaporan;
  use App\Models\Rekomendasi;
  use App\Support\Tampil;

  /* Langkah 1: isi laporan dalam satu halaman. Temuan, rekomendasi, dan tindak lanjut sebagai
     deret tab. */
  $s = $d['surat'];
  $sumber = SumberLaporan::from($s['sumber']);
  $salah = $form->salahTanggal($s);
  $ini = now()->toDateString();
  $renaksi = $s['tgl_terima'] ? Rekomendasi::hitungTenggat(\Illuminate\Support\Carbon::parse($s['tgl_terima']), $sumber) : null;
  $ringkasSurat = implode(' · ', array_filter([
    $s['sumber'],
    trim($s['nomor']) ?: 'nomor belum diisi',
    $s['tgl_surat'] ? 'surat '.Tampil::tgl($s['tgl_surat']) : null,
    $s['tgl_terima'] ? 'diterima '.Tampil::tgl($s['tgl_terima']) : null,
  ]));
  $temAktif = $d['temuan'][$iAktif];
@endphp

{{-- Sekali di atas, bukan di tiap isian. --}}
<p class="fb-legenda"><span class="fb-bintang" aria-hidden="true">*</span> wajib diisi</p>

<section id="fb-surat" class="fb-bag{{ $suratBuka ? ' buka' : '' }}" aria-label="Surat laporan" data-bagian-surat>
  {{-- Kepala bagian buka-tutup; tanpa skrip tombolnya mengirim isian lalu menampilkan bagian
       itu. --}}
  <div class="fb-kep{{ $suratBuka ? ' buka' : '' }}">
    <button type="submit" name="aksi" value="surat" class="fb-alih" aria-expanded="{{ $suratBuka ? 'true' : 'false' }}"
      aria-controls="fb-surat-isi" data-alih-surat>
      <span class="fb-no"><x-ikon n="FileText" :s="15" /></span>
      <span class="fb-judul">
        <b title="Surat laporan">Surat laporan</b>
        <span class="fb-ringkas">{{ $ringkasSurat }}</span>
      </span>
      <span class="fb-status{{ $ok1 ? ' ok' : '' }}">
        @if($ok1)<x-ikon n="CheckCircle2" :s="14" />@else<x-ikon n="Circle" :s="14" />@endif
        <span>{{ $ok1 ? 'Lengkap' : 'Belum lengkap' }}</span>
      </span>
      <x-ikon n="ChevronDown" :s="16" class="fb-panah" />
    </button>
  </div>
  <div class="fb-isi" id="fb-surat-isi" @unless($suratBuka) hidden @endunless>
    <x-baris-isi label="Sumber laporan" untuk="fb-jenis" :wajib="true">
      <div class="fb-sebaris">
        {{-- Mengganti sumber langsung menyegarkan halaman (kategori dan tenggat ikut berganti). --}}
        <select id="fb-jenis" name="surat[sumber]" class="fb-sedang" aria-required="true" data-kirim>
          <option value="LHP" @selected($s['sumber'] === 'LHP')>LHP — Laporan Hasil Pemeriksaan</option>
          <option value="LHA" @selected($s['sumber'] === 'LHA')>LHA — Laporan Hasil Audit</option>
        </select>
        <span class="fb-catatan">
          Tenggat {{ $sumber->hariTenggat() }} {{ $sumber->satuanTenggat() }}
          <x-info :teks="'Batas penyampaian jawaban atas rekomendasi: '.$sumber->hariTenggat().' '.$sumber->satuanTenggat().' sejak tanggal laporan diterima.'" />
        </span>
      </div>
    </x-baris-isi>
    <x-baris-isi label="Nomor surat" untuk="fb-nomor" :wajib="true">
      <input id="fb-nomor" type="text" name="surat[nomor]" class="mono fb-sedang" aria-required="true"
        value="{{ $s['nomor'] }}" placeholder="000/LHP/XVIII/00/2026">
    </x-baris-isi>
    {{-- `max` hanya bantuan browser; penjaganya `salahTanggal` di server. --}}
    <x-baris-isi label="Tanggal surat" untuk="fb-tgldok" :wajib="true">
      <input id="fb-tgldok" type="date" name="surat[tgl_surat]" aria-required="true" value="{{ $s['tgl_surat'] }}" max="{{ $ini }}">
    </x-baris-isi>
    <x-baris-isi label="Tanggal diterima" untuk="fb-tglterima" :wajib="true"
      :info="['Isi tanggal laporan benar-benar diterima sesuai catatan penerimaan surat.',
        'Tanggal ini menjadi dasar perhitungan tenggat jawab rekomendasi pada laporan.']">
      <div class="fb-sebaris">
        <input id="fb-tglterima" type="date" name="surat[tgl_terima]" aria-required="true" value="{{ $s['tgl_terima'] }}"
          @if($s['tgl_surat']) min="{{ $s['tgl_surat'] }}" @endif max="{{ $ini }}" data-kirim>
        @if($salah)
          <span class="fb-catatan salah">{{ $salah }}</span>
        @elseif($renaksi)
          <span class="fb-catatan">Rencana aksi {{ Tampil::tgl($renaksi) }}</span>
        @endif
      </div>
    </x-baris-isi>
    {{-- Yang disimpan link surat; ketiadaannya ditandai di halaman laporan, tidak ditahan di
         sini. --}}
    <x-baris-isi label="Surat asli" untuk="fb-berkas-judul"
      info="Link laporan pemeriksaan asli beserta lampirannya sebagai rujukan temuan dan rekomendasi.">
      <div class="fb-dua">
        <input id="fb-berkas-judul" type="text" name="berkas[judul]" value="{{ $d['berkas']['judul'] }}"
          placeholder="Judul berkas, mis. LHP beserta lampirannya">
        <input type="text" class="mono" name="berkas[link]" aria-label="Link surat asli" value="{{ $d['berkas']['link'] }}"
          placeholder="Link, mis. https://…">
      </div>
      <x-panduan-link-bukti />
      @error('berkas.link')<span class="pesan bad" role="alert">{{ $message }}</span>@enderror
    </x-baris-isi>
  </div>
  <input type="hidden" name="ui[surat]" value="{{ $suratBuka ? '1' : '0' }}" data-ui-surat>
</section>

<div class="fb-daftar-kep">
  <b>Temuan dan rekomendasi</b>
  <span>{{ $ringkas['tem'] }} temuan · {{ $ringkas['rek'] }} rekomendasi · {{ $ringkas['pen'] }} penugasan</span>
</div>

{{-- Deret tab temuan menempel di bawah batang atas selagi digulir. --}}
<div class="fb-temuan">
  <div class="fb-tab-baris fb-tab-tem">
    <div role="tablist" aria-label="Temuan" class="fb-tabs">
      @foreach($d['temuan'] as $i => $x)
        <x-tab-isi id="fb-tab-tem-{{ $x['id'] }}" panel="fb-tem-{{ $x['id'] }}" aksi="temuan:{{ $x['id'] }}"
          :aktif="$i === $iAktif" :no="$i + 1" :teks="trim($x['judul'])" kosong="Belum berjudul" :ok="$form->temOk($x)" />
      @endforeach
    </div>
    <button type="submit" name="aksi" value="tambah-temuan" class="fb-tambah">
      <x-ikon n="Plus" :s="15" /> Tambah temuan
    </button>
  </div>
  @foreach($d['temuan'] as $i => $t)
    @include('laporan.bagian.fb-temuan', ['t' => $t, 'i' => $i, 'tampil' => $i === $iAktif])
  @endforeach
</div>
{{-- Tab yang dipilih lewat skrip, dibawa ke kiriman berikutnya. --}}
<input type="hidden" name="ui[aktif]" value="{{ $temAktif['id'] }}" data-ui-aktif>
<input type="hidden" name="ui[buka]" value="{{ $d['buka'] ?? '' }}" data-ui-buka>
