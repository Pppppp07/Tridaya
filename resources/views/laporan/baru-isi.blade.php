@php
  use App\Enums\SumberLaporan;
  use App\Models\Rekomendasi;
  use App\Support\Tampil;

  /* Langkah 1 — isi laporan, satu halaman seperti mengisi dokumen (Hizkia,
     24 Sep): surat laporan di atas, lalu temuan dengan rekomendasi dan tindak
     lanjut di dalamnya. Temuan, rekomendasi, dan tindak lanjut berjajar ke
     samping sebagai tab; yang tampil hanya yang terpilih, jadi halamannya tidak
     memanjang berapa pun jumlahnya. */
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
  {{-- Kepala bagian yang bisa dibuka-tutup — padanan `KepalaBagian`. Tanpa
       skrip, tombolnya mengirim isian dan menampilkan halaman dengan bagian
       ini terbuka atau tertutup. --}}
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
        {{-- Mengganti sumber mengganti daftar kategori temuan dan dasar
             hitungan tenggatnya, jadi halamannya langsung disegarkan. --}}
        <select id="fb-jenis" name="surat[sumber]" class="fb-sedang" aria-required="true" data-kirim>
          <option value="LHP" @selected($s['sumber'] === 'LHP')>LHP — Laporan Hasil Pemeriksaan</option>
          <option value="LHA" @selected($s['sumber'] === 'LHA')>LHA — Laporan Hasil Audit</option>
        </select>
        <span class="fb-catatan">
          Tenggat {{ $sumber->hariTenggat() }} {{ $sumber->satuanTenggat() }}
          <x-info :teks="$sumber->dasarHukum()" />
        </span>
      </div>
    </x-baris-isi>
    <x-baris-isi label="Nomor surat" untuk="fb-nomor" :wajib="true">
      <input id="fb-nomor" type="text" name="surat[nomor]" class="mono fb-sedang" aria-required="true"
        value="{{ $s['nomor'] }}" placeholder="000/LHP/XVIII/00/2026">
    </x-baris-isi>
    {{-- `max` supaya browser ikut menolak tanggal di depan. Bukan penjaga
         sesungguhnya — itu `salahTanggal` di server. --}}
    <x-baris-isi label="Tanggal surat" untuk="fb-tgldok" :wajib="true">
      <input id="fb-tgldok" type="date" name="surat[tgl_surat]" aria-required="true" value="{{ $s['tgl_surat'] }}" max="{{ $ini }}">
    </x-baris-isi>
    <x-baris-isi label="Tanggal diterima" untuk="fb-tglterima" :wajib="true"
      :info="['Dari cap terima suratnya, bukan tanggal Anda mengisi formulir ini.',
        'Tenggat seluruh rekomendasi laporan ini dihitung dari tanggal ini.']">
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
    {{-- Surat aslinya tebal — LHP bisa ratusan halaman. Yang disimpan
         link-nya, bukan berkasnya, dan ketiadaannya ditandai di halaman
         laporannya, bukan ditahan di sini. --}}
    <x-baris-isi label="Surat asli" untuk="fb-berkas-judul"
      :info="['Boleh dilengkapi belakangan — suratnya kerap sampai lebih dulu daripada salinan arsipnya.',
        'Yang disimpan link-nya, bukan berkasnya.']">
      <div class="fb-dua">
        <input id="fb-berkas-judul" type="text" name="berkas[judul]" value="{{ $d['berkas']['judul'] }}"
          placeholder="Judul berkas, mis. LHP beserta lampirannya">
        <input type="text" class="mono" name="berkas[link]" aria-label="Link surat asli" value="{{ $d['berkas']['link'] }}"
          placeholder="Link, mis. https://…">
      </div>
    </x-baris-isi>
  </div>
  <input type="hidden" name="ui[surat]" value="{{ $suratBuka ? '1' : '0' }}" data-ui-surat>
</section>

<div class="fb-daftar-kep">
  <b>Temuan dan rekomendasi</b>
  <span>{{ $ringkas['tem'] }} temuan · {{ $ringkas['rek'] }} rekomendasi · {{ $ringkas['pen'] }} penugasan</span>
</div>

{{-- Deret tab temuan menempel di bawah batang atas selagi isinya digulir,
     jadi temuan lain dan "Tambah temuan" selalu terjangkau. --}}
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
