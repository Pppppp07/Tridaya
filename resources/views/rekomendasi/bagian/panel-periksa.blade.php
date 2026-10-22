@php
  use App\Enums\HasilTelaah;
  use App\Enums\PeranPengguna;
  use App\Enums\PosisiBerkas;

  /* Putusan UKI atau Inspektorat atas satu baris (hanya satuan kerja yang barisnya dibuka). */
  $uki = $x->pos() === PosisiBerkas::UKI;
  $atasNama = ! $uki && $peran === PeranPengguna::SETBA;
  $nama = $uki ? 'UKI' : 'Inspektorat';
  $kataM = HasilTelaah::M->nama($jenis);
  $kataBM = HasilTelaah::BM->nama($jenis);
  /* Satuan kerja LAIN yang belum memadai: CHV tetap berbunyi belum memadai walau baris ini
     memadai. */
  $lainBelum = $uki ? collect() : $r->semuaBaris()
    ->filter(fn ($y) => $y->satker_id !== $x->satker_id && $y->hasil !== HasilTelaah::M)
    ->map->satker->unique('id')->values();
  $hari = now()->toDateString();
  /* Kiriman bertahap: dokumen yang belum terlampir disebut namanya sebagai bahan putusan. */
  $belumDok = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item->where('terpenuhi', false)->values();
  $jumlahDok = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item->count();
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
      'Catat putusan, nomor surat, tanggal, dan catatan sesuai CHV yang diterbitkan Inspektorat.',
      'Setba mencatat hasilnya; pihak yang menetapkan keputusan tetap Inspektorat.',
    ] : 'Catat hasil pemeriksaan '.$nama.' untuk penugasan ini, beserta surat dan catatan perbaikannya.'">
    <x-slot:kalimat>Berkas <b>{{ $x->satker->namaPendek() }}</b> menunggu putusan {{ $nama }}.</x-slot:kalimat>

    @if($atasNama)
      <x-baris-lajur>
        <div class="kerja-awas">
          <x-ikon n="FileText" :s="15" />
          <span>Anda mengerjakan meja Inspektorat atas nama mereka. Salin apa yang tertulis di surat CHV.</span>
        </div>
      </x-baris-lajur>
    @endif

    @if($belumDok->isNotEmpty())
      <x-baris-lajur>
        <div class="kerja-awas" data-dok-kurang>
          <x-ikon n="AlertTriangle" :s="15" />
          <span>Dokumen yang diminta baru {{ $jumlahDok - $belumDok->count() }} dari {{ $jumlahDok }} terlampir. Belum: {{ $belumDok->pluck('nama')->join(', ') }}.</span>
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
      {{-- Diperingatkan sebelum ditekan: satu CHV memutus seluruh rekomendasi. --}}
      @if($lainBelum->isNotEmpty())
        <div class="kerja-awas" style="margin-top:8px" data-hanya-m hidden>
          <x-ikon n="AlertTriangle" :s="15" />
          <span>
            Surat CHV-nya tercatat <b>{{ mb_strtolower($kataBM) }}</b>. Tanda untuk
            satuan kerja ini tetap {{ mb_strtolower($kataM) }}, tapi satu CHV
            memutus seluruh rekomendasi — dan {{ $lainBelum->count() }} satuan kerja
            lain belum: {{ $lainBelum->take(3)->map->namaPendek()->join(', ') }}{{ $lainBelum->count() > 3 ? ' dan '.($lainBelum->count() - 3).' lainnya' : '' }}.<x-info :teks="[
              'Putusan penugasan ini dicatat tersendiri. Rangkuman hasil CHV untuk rekomendasi baru menjadi '.mb_strtolower($kataM).' setelah seluruh penugasannya dinyatakan '.mb_strtolower($kataM).'.',
            ]" />
          </span>
        </div>
      @endif
    </x-baris-isi>

    {{-- Isian muncul sesudah putusan dipilih: memadai wajib surat (nomor, tanggal), belum
         memadai wajib catatan. --}}
    <div class="kerja-susul" data-mode-isi hidden>
      <x-baris-isi :label="$uki ? 'Nomor surat hasil validasi' : 'Nomor CHV'" :untuk="'nomor-'.$k" bila="M" :info="[
        $uki ? 'Nomor surat UKI yang memuat hasil validasi tindak lanjut ini.' : 'Nomor Catatan Hasil Verifikasi (CHV) dari Inspektorat untuk tindak lanjut ini.',
        'Salin nomor sesuai surat. Wajib diisi untuk hasil '.mb_strtolower($kataM).'; boleh kosong untuk hasil '.mb_strtolower($kataBM).'.',
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
          'Tanggal terakhir Satker harus melengkapi perbaikan, sesuai batas waktu yang ditetapkan Inspektorat.',
        ]">
          <input id="batas-{{ $k }}" type="date" name="batas_waktu" min="{{ $hari }}" aria-required="true" data-f="batas_waktu">
        </x-baris-isi>
      @endif

      <x-baris-isi label="Catatan" :untuk="'catatan-'.$k" bila="BM" info-bila="BM"
        info="Jelaskan kekurangan bukti atau tindakan yang perlu diperbaiki oleh satuan kerja.">
        <textarea id="catatan-{{ $k }}" rows="3" name="catatan" data-f="catatan" data-wajib-bila="BM"
          data-contoh-m="Contoh: bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok dengan nilai temuan."
          data-contoh-bm="Contoh: bukti setor belum dilampiri Nota Konfirmasi KPPN."></textarea>
      </x-baris-isi>

      {{-- Belum memadai: dokumen yang harus dilengkapi; Setba menyetel daftar akhirnya sebelum
           mengirim ulang. --}}
      <x-baris-isi label="Dokumen yang diminta" gabung :kunci="'dok-periksa-'.$k" data-hanya-bm hidden :info="[
        'Daftar bukti yang perlu dilengkapi Satker sesuai kekurangan yang ditemukan dalam pemeriksaan.',
      ]">
        @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => [''], 'placeholder' => 'Contoh: Nota Konfirmasi KPPN setoran sisa', 'tambah' => 'Tambah dokumen'])
      </x-baris-isi>

      {{-- Perihal, LHV, dan pindaian suratnya boleh menyusul. --}}
      @php $buktiTinjau = \App\Models\Lampiran::where('sasaran_id', $x->id)->whereNull('ditolak_pada')->orderByDesc('id')->limit(100)->get(); @endphp
      @if($buktiTinjau->isNotEmpty())
        <x-baris-isi label="Bukti yang perlu diganti" gabung data-hanya-bm hidden
          info="Bukti yang ditolak dan harus dilengkapi ulang oleh Satker. Pilih dokumennya dan jelaskan alasan pada Catatan; arsip lama tetap tersimpan.">
          <div class="bukti-tolak-list">
          @foreach($buktiTinjau as $bukti)
            <label><input type="checkbox" name="bukti_tolak[]" value="{{ $bukti->id }}"> <span>{{ $bukti->labelTampil() }}</span></label>
          @endforeach
          </div>
        </x-baris-isi>
      @endif
      <x-opsional :judul="$uki ? 'Perihal surat dan link berkas' : 'LHV, perihal surat, dan link berkas'" :jumlah="$uki ? 2 : 4">
        {{-- LHV mengesahkan CHV: nomor dari CHV, tanggal dari LHV. --}}
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
            <input type="text" class="mono" name="link" aria-label="Link berkas hasil" placeholder="Link satu file Google Drive/Docs">
          </div>
          <x-panduan-link-bukti />
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
