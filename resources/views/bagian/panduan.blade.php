@php
  use App\Enums\PeranPengguna as P;

  /* Panduan singkat: terbuka lewat #panduan walau tanpa skrip (:target), atau lewat kelas
     `tampil` dari skrip. */
  $tugas = $peran->tugas();
  $guna = fn (string $rute) => match ($rute) {
      'rekomendasi.index' => match (true) {
          $peran === P::PIMPINAN => 'Rekomendasi Satker yang Anda pimpin beserta posisi berkas dan proses tindak lanjutnya.',
          /* Peran pemantau tidak punya filter Perlu dikerjakan. */
          $peran->hanyaMelihat() => 'Semua rekomendasi beserta posisi berkas dan proses tindak lanjutnya.',
          default => ($peran === P::SATKER ? 'Rekomendasi yang ditujukan ke unit Anda. ' : 'Semua rekomendasi yang Anda pegang. ')
            .'Filter Perlu dikerjakan berisi berkas yang menunggu tindakan Anda — jumlahnya sama dengan angka di menu ini.',
      },
      'laporan.index' => 'Laporan pemeriksaan — LHP dari BPK dan LHA dari Inspektorat — beserta temuan dan rekomendasinya.',
      'ringkasan'     => 'Angka utama per tindak lanjut satuan kerja. Klik kartunya untuk rincian, atau buka Tabel keseluruhan.',
      'master'        => 'Admin DTI mengelola user dan akses, satuan kerja beserta penanggung jawabnya, serta kategori dan referensi. Dashboard menampilkan data yang perlu dilengkapi; Log admin mencatat perubahannya. Angka merah di menu menunjukkan masalah kelengkapan akses dan penugasan yang perlu ditangani.',
      'log'           => 'Siapa melakukan apa dan kapan — perpindahan berkas, perubahan data master dan hak akses, masuk dan keluar — serta keaktifan tiap akun.',
      default         => '',
  };
  $alur = [
      'Satuan kerja mengirim tanggapan dan buktinya ke Setba.',
      'Setba meneruskannya ke UKI dengan surat pengantar.',
      'UKI menelaah, lalu mencatat hasil validasinya.',
      'Setba meneruskan ke Inspektorat, lalu hasil verifikasinya (CHV) dicatat.',
      'LHP lanjut ke SIPTL dan menunggu penilaian BPK. LHA selesai di Inspektorat.',
  ];
@endphp

<div id="panduan" class="tirai tirai-panduan" data-panduan>
  <div class="lembar panduan" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="panduan-judul">
    <div class="kep">
      <span class="ic-kotak"><x-ikon n="HelpCircle" :s="17" /></span>
      <span class="judul">
        <b id="panduan-judul">Panduan singkat</b>
        <span class="lbl">{{ $sebutan }}</span>
      </span>
      <a class="btn btn-s" href="#" data-tutup-panduan><x-ikon n="X" :s="13" /> Tutup</a>
    </div>
    <div class="bdn">
      <section>
        <h3>Tugas Anda</h3>
        <p>{{ $tugas }}</p>
      </section>
      <section>
        <h3>Menu</h3>
        <dl>
          @foreach($menu as $m)
            <div>
              <dt><x-ikon :n="$m['ikon']" :s="14" /> {{ $m['nama'] }}</dt>
              <dd>{{ $guna($m['rute']) }}</dd>
            </div>
          @endforeach
        </dl>
      </section>
      <section>
        <h3>Perjalanan satu tindak lanjut</h3>
        <ol>@foreach($alur as $x)<li>{{ $x }}</li>@endforeach</ol>
        <p class="catat">Berkas yang ditolak kembali ke Setba dulu, lalu dikirim ulang ke satuan kerja.</p>
      </section>
      <section>
        <h3>Tips</h3>
        <ul>
          <li>Ikon lonceng di kanan atas menampilkan pemberitahuan terbaru. Klik pemberitahuan untuk langsung membuka bagian yang berubah.</li>
          <li>Tekan <kbd>/</kbd> untuk mencari kode, temuan, atau satuan kerja.</li>
          <li>Tombol akun di kanan atas membuka profil, keamanan akun, pengaturan, dan tombol Keluar.</li>
          <li>Tanda <b>!</b> menyimpan keterangan lengkap — sentuh untuk membacanya.</li>
          <li>Di tabel tindak lanjut, buka baris satuan kerja untuk melihat Bukti &amp; tanggapan, Kerjakan, dan Progres.</li>
          <li>Klik area kosong di menu, tombol panah di bagian atas sidebar, atau tekan <kbd>[</kbd> untuk menciutkan atau melebarkan menu pada komputer dan tablet.</li>
          <li>Di ponsel, tutup menu dengan tombol ×, tombol Esc, atau sentuh area di luarnya.</li>
            <li>Tombol matahari/bulan di header mengganti tema dan langsung menyimpannya untuk akun Anda. Tema, lebar menu, animasi, dan halaman pertama juga bisa diatur melalui Profil &amp; pengaturan → Tampilan.</li>
        </ul>
      </section>
    </div>
  </div>
</div>
