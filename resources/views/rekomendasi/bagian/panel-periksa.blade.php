@php
  use App\Enums\HasilTelaah;
  use App\Enums\PeranPengguna;
  use App\Enums\PosisiBerkas;

  /* Putusan UKI atau Inspektorat atas satu baris — padanan PanelPeriksa.
     Daftarnya berisi satu satuan kerja saja: yang barisnya sedang dibuka, jadi
     "Tanda tiap satuan kerja" tidak ditanyakan — putusannya memang putusan
     satuan kerja itu (25 Sep). Berbentuk panel kerja (`x-panel-kerja`):
     putusannya dua kartu, dan isian lainnya baru muncul sesudah dipilih,
     karena yang wajib bergantung pada putusan itu. */
  $uki = $x->pos() === PosisiBerkas::UKI;
  $atasNama = ! $uki && $peran === PeranPengguna::SETBA;
  $nama = $uki ? 'UKI' : 'Inspektorat';
  $kataM = HasilTelaah::M->nama($jenis);
  $kataBM = HasilTelaah::BM->nama($jenis);
  /* Satuan kerja LAIN yang belum memadai: surat CHV-nya akan berbunyi belum
     memadai walau baris ini ditandai memadai — satu CHV memutus seluruh
     rekomendasi. */
  $lainBelum = $uki ? collect() : $r->semuaBaris()
    ->filter(fn ($y) => $y->satker_id !== $x->satker_id && $y->hasil !== HasilTelaah::M)
    ->map->satker->unique('id')->values();
  $hari = now()->toDateString();
  $k = $x->id;
@endphp

<form method="post" action="{{ route('sasaran.putus', $x) }}" id="r-periksa" data-panel-periksa
  data-uki="{{ $uki ? 1 : 0 }}" data-kata-m="{{ $kataM }}" data-kata-bm="{{ $kataBM }}"
  data-lain-belum="{{ $lainBelum->count() }}" data-hari="{{ $hari }}">
  @csrf
  <input type="hidden" name="hasil" value="" data-mode>
  <x-panel-kerja ikon="ClipboardCheck" wajib aksi-tersembunyi
    :judul="$uki ? 'Telaah berkas' : ($atasNama ? 'Catat hasil verifikasi Inspektorat' : 'Catat hasil verifikasi')"
    :info="$atasNama ? [
      'Meja Inspektorat boleh dikerjakan Inspektorat sendiri, dan boleh juga oleh Setba.',
      'Kalau Setba yang mengerjakan, yang dicatat adalah isi surat CHV yang dikirim Inspektorat — riwayatnya menyebut Setba sebagai pencatat, dengan Inspektorat sebagai yang berwenang.',
    ] : 'Putusannya ditentukan '.$nama.'. Aplikasi tidak menghitungnya sendiri dari kelengkapan berkas.'">
    <x-slot:kalimat>Berkas <b>{{ $x->satker->namaPendek() }}</b> menunggu putusan {{ $nama }}.</x-slot:kalimat>

    @if($atasNama)
      <x-baris-lajur>
        <div class="kerja-awas">
          <x-ikon n="FileText" :s="15" />
          <span>Anda mengerjakan meja Inspektorat atas nama mereka. Salin apa yang tertulis di surat CHV.</span>
        </div>
      </x-baris-lajur>
    @endif

    <x-baris-isi label="Putusan" gabung wajib kosong :kunci="'putusan-'.$k">
      <div class="kerja-pilih" role="group" aria-label="Putusan">
        <button type="button" class="ok" aria-pressed="false" data-pilih-mode="M">
          <x-ikon n="Check" :s="18" /><b>{{ $kataM }}</b><span>{{ $uki ? 'Lanjut ke Inspektorat lewat Setba' : 'Tindak lanjutnya sudah memadai' }}</span>
        </button>
        <button type="button" class="bad" aria-pressed="false" data-pilih-mode="BM">
          <x-ikon n="RotateCcw" :s="18" /><b>{{ $kataBM }}</b><span>Kembali untuk pemberkasan ulang</span>
        </button>
      </div>
      {{-- Disebutkan sebelum tombolnya ditekan, bukan sesudahnya. Satu CHV
           memutus SELURUH rekomendasi — tanpa peringatan ini orang menekan
           memadai lalu suratnya tercatat sebaliknya. --}}
      @if($lainBelum->isNotEmpty())
        <div class="kerja-awas" style="margin-top:8px" data-hanya-m hidden>
          <x-ikon n="AlertTriangle" :s="15" />
          <span>
            Surat CHV-nya tercatat <b>{{ mb_strtolower($kataBM) }}</b>. Tanda untuk
            satuan kerja ini tetap {{ mb_strtolower($kataM) }}, tapi satu CHV
            memutus seluruh rekomendasi — dan {{ $lainBelum->count() }} satuan kerja
            lain belum: {{ $lainBelum->take(3)->map->namaPendek()->join(', ') }}{{ $lainBelum->count() > 3 ? ' dan '.($lainBelum->count() - 3).' lainnya' : '' }}.<x-info :teks="[
              'Tanda per satuan kerja dan putusan surat itu dua hal yang berbeda.',
              'Tandanya menggerakkan berkas satuan kerja ini; putusan suratnya menilai perkaranya secara utuh.',
              'Mbak Puspi: “satker 1 sama satker 2 sudah, cuma satker 3 karena masih ada kekurangan makanya rekomendasi ini belum selesai, belum memadai.”',
              'Begitu satuan kerja terakhir memadai, CHV berikutnya baru bisa berbunyi memadai — dan yang dipakai selalu CHV yang terakhir terbit.',
            ]" />
          </span>
        </div>
      @endif
    </x-baris-isi>

    {{-- Isiannya baru ditanyakan sesudah putusannya dipilih. Bintangnya ikut
         putusan: memadai berdiri di atas surat (nomor dan tanggal wajib),
         belum memadai berdiri di atas catatan. --}}
    <div class="kerja-susul" data-mode-isi hidden>
      <x-baris-isi :label="$uki ? 'Nomor surat hasil validasi' : 'Nomor CHV'" :untuk="'nomor-'.$k" bila="M" :info="[
        'Status berdiri di atas surat. Menyatakan memadai tanpa nomor suratnya berarti menyatakan sesuatu yang tidak punya dasar.',
        'Waktu mengembalikan berkas, nomornya boleh menyusul — yang wajib catatannya, supaya satuan kerja tahu apa yang kurang.',
      ]">
        <input id="nomor-{{ $k }}" type="text" class="mono fb-sedang" name="nomor" data-f="nomor" data-wajib-bila="M"
          placeholder="{{ $uki ? '031/VAL-UKI/BPSDM/VIII/2026' : '65/CHV/ITJEN/VIII/2026' }}">
      </x-baris-isi>
      <x-baris-isi label="Tanggal surat" :untuk="'tgl-surat-'.$k" bila="M">
        <input id="tgl-surat-{{ $k }}" type="date" name="tgl_surat" max="{{ $hari }}" data-f="tgl_surat" data-wajib-bila="M">
      </x-baris-isi>

      {{-- Penolakan Inspektorat: batas waktunya milik Inspektorat, wajib. --}}
      @if(! $uki)
        <x-baris-isi label="Batas waktu perbaikan" :untuk="'batas-'.$k" wajib data-hanya-bm hidden :info="[
          'Ditetapkan Inspektorat bersama penolakannya — sampai kapan satuan kerja harus memperbaiki.',
          'Satuan kerja membacanya sesudah Setba mengirim ulang berkasnya, bersama alasannya.',
        ]">
          <input id="batas-{{ $k }}" type="date" name="batas_waktu" min="{{ $hari }}" aria-required="true" data-f="batas_waktu">
        </x-baris-isi>
      @endif

      <x-baris-isi label="Catatan" :untuk="'catatan-'.$k" bila="BM" info-bila="BM"
        info="Lewat catatan ini satuan kerja tahu apa yang perlu diperbaiki.">
        <textarea id="catatan-{{ $k }}" rows="3" name="catatan" data-f="catatan" data-wajib-bila="BM"
          data-contoh-m="Contoh: bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok dengan nilai temuan."
          data-contoh-bm="Contoh: bukti setor belum dilampiri Nota Konfirmasi KPPN."></textarea>
      </x-baris-isi>

      {{-- Belum memadai: dokumen apa yang harus dilengkapi. Setba memeriksanya
           dulu dan menyetel daftar akhirnya sebelum mengirim ulang. --}}
      <x-baris-isi label="Dokumen yang diminta" gabung :kunci="'dok-periksa-'.$k" data-hanya-bm hidden :info="[
        'Dokumen yang harus dilengkapi satuan kerja saat pemberkasan ulang.',
        'Berkasnya ke Setba dulu. Setba memeriksa daftar ini, boleh menyesuaikannya, lalu mengirim ulang ke satuan kerja.',
      ]">
        @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => [''], 'placeholder' => 'Contoh: Nota Konfirmasi KPPN setoran sisa', 'tambah' => 'Tambah dokumen'])
      </x-baris-isi>

      {{-- Perihal, LHV, dan pindaian suratnya boleh menyusul. --}}
      <x-opsional :judul="$uki ? 'Perihal surat dan link berkas' : 'LHV, perihal surat, dan link berkas'" :jumlah="$uki ? 2 : 4">
        {{-- LHV — surat pengantar Eselon 1 yang mengesahkan CHV. Rapat 30 Agu:
             nomor dicatat dari CHV, TANGGAL dari LHV. Terbitnya belakangan. --}}
        @if(! $uki)
          <x-baris-isi label="Nomor LHV" :untuk="'lhv-'.$k" info="Surat pengantar Eselon 1 yang mengesahkan CHV.">
            <div class="fb-sebaris">
              <input id="lhv-{{ $k }}" type="text" class="mono fb-pendek" name="nomor_lhv" placeholder="PW.02.01-Ij/412">
              <span class="fb-pasang">
                <label class="fb-sublbl" for="tgl-lhv-{{ $k }}">Tanggal LHV</label>
                <input id="tgl-lhv-{{ $k }}" type="date" name="tgl_lhv" max="{{ $hari }}">
              </span>
            </div>
          </x-baris-isi>
        @endif
        <x-baris-isi label="Perihal surat" :untuk="'perihal-'.$k">
          <input id="perihal-{{ $k }}" type="text" name="perihal"
            placeholder="{{ $uki ? 'Hasil validasi tindak lanjut' : 'Catatan hasil verifikasi tindak lanjut' }}">
        </x-baris-isi>
        <x-baris-isi :label="'Berkas hasil '.($uki ? 'validasi' : 'verifikasi')" gabung :kunci="'berkas-hasil-'.$k">
          <div class="fb-dua">
            <input type="text" name="berkas" aria-label="Judul berkas hasil"
              placeholder="Judul, mis. {{ $uki ? 'Catatan hasil validasi UKI' : 'Catatan hasil verifikasi Inspektorat' }}">
            <input type="text" class="mono" name="link" aria-label="Link berkas hasil" placeholder="Link, mis. https://…">
          </div>
        </x-baris-isi>
      </x-opsional>
    </div>

    <x-slot:aksi>
      <button type="submit" class="btn btn-ok" data-tetapkan data-pastikan='{}'>
        <x-ikon n="Check" :s="14" data-ikon-m /><x-ikon n="RotateCcw" :s="14" data-ikon-bm hidden /> <span data-teks-tetapkan>Tetapkan</span>
      </button>
      <button type="button" class="btn" data-batal-mode><x-ikon n="X" :s="14" /> Batal</button>
    </x-slot:aksi>
  </x-panel-kerja>
</form>
