@php
  use App\Enums\StatusTindakLanjut;
  use App\Support\Tampil;

  /* Pekerjaan SIPTL satu baris, di tab Kerjakan barisnya — padanan
     `PanelSiptlBaris`. Sejak 25 Sep berbentuk panel kerja (`x-panel-kerja`):
     kartu berkepala pita abu, label di kiri, bintang pada yang wajib, dan
     keterangan panjang di balik ikon Info.

     Karena putusan BPK dicatat sekali tiap unggahan, satu baris cuma pernah
     punya satu dari tiga pekerjaan ini: mencatat unggahan, mencatat putusan,
     atau mengirim ulang yang ditolak. */
  $hari = now()->toDateString();
  $nama = $x->satker->namaPendek();
  /* Bentuk tindak lanjutnya disebut di jendela penegasan kalau satuan kerja
     itu memikul lebih dari satu — "Bandung" saja tidak menjawab yang mana. Di
     kepala panel tidak: bentuknya sudah tertulis di kepala kelompoknya. */
  $rangkap = $r->sasaran->where('satker_id', $x->satker_id)->pluck('tindakan_id')->unique()->count() > 1;
  $siapa = $nama.($rangkap && $x->tindakan?->namaBentuk() ? ' ('.$x->tindakan->namaBentuk().')' : '');
  $siapNaik = $x->perluUnggah($jenis);
  $perluCek = $x->perluCek($jenis);
  $perluUlang = $x->perluKirimUlang($jenis);
  /* Id isian unik per baris: satu halaman memuat panel banyak baris. */
  $k = $x->id;
@endphp

@if($siapNaik)
  {{-- ---- 1. Mencatat unggahan ---- --}}
  <form method="post" action="{{ route('siptl.unggah', $x) }}" data-form-siptl
    data-siap-naik="1" data-satker="{{ $siapa }}" data-hari="{{ $hari }}">
    @csrf
    <x-panel-kerja ikon="Upload" judul="Catat unggahan ke SIPTL" wajib
      ket="Tanggal unggah harus terisi" lanjut="Statusnya jadi Belum Ditindaklanjuti sampai BPK memutus."
      :info="[
        'Unggahannya dikerjakan di aplikasi SIPTL milik BPK. Yang dicatat di sini tanggalnya.',
        'Begitu dicatat, statusnya otomatis Belum Ditindaklanjuti sampai BPK memutus.',
        'Yang naik hanya tindak lanjut satuan kerja pada baris ini. Satuan kerja lain tidak perlu ditunggu.',
      ]">
      <x-slot:kalimat>Berkas <b>{{ $nama }}</b> siap diunggah ke SIPTL.</x-slot:kalimat>
      {{-- Kotak tanggal unggah cuma pernah muncul di sini, selama memang
           tahap unggah — supaya tidak jadi bahan manipulasi. --}}
      <x-baris-isi label="Tanggal unggah" :untuk="'unggah-'.$k" wajib
        info="Pastikan sama dengan tanggal unggah yang tertulis di SIPTL — tanggal ini tidak bisa diubah lagi.">
        <div class="fb-sebaris">
          <input id="unggah-{{ $k }}" type="date" name="tanggal" aria-required="true" max="{{ $hari }}" data-tanggal-unggah>
          <span class="fb-catatan"><x-ikon n="Lock" :s="13" /> dikunci begitu dicatat</span>
        </div>
      </x-baris-isi>
      <x-slot:aksi>
        <button type="submit" class="btn btn-p" data-pastikan="{}" data-simpan-siptl>
          <x-ikon n="Upload" :s="14" /> Catat unggahan
        </button>
      </x-slot:aksi>
    </x-panel-kerja>
  </form>

@elseif($perluCek)
  {{-- ---- 2. Mencatat putusan BPK — sekali tiap unggahan ---- --}}
  <form method="post" action="{{ route('siptl.status', $x) }}" data-form-siptl
    data-siap-naik="0" data-satker="{{ $siapa }}" data-hari="{{ $hari }}">
    @csrf
    <input type="hidden" name="status" value="" data-status-pilih>
    <x-panel-kerja ikon="Clock" judul="Catat hasil pemantauan BPK" wajib
      ket="Pilih putusan BPK. Kalau BPK belum memutus, biarkan dulu."
      :info="[
        'Dibaca dari aplikasi SIPTL, lalu disalin apa adanya ke sini.',
        'Putusan BPK dicatat sekali untuk unggahan ini dan tidak bisa diubah lagi.',
        'Kalau Belum Sesuai, berkasnya dikirim ulang ke satuan kerja lalu diunggah ulang ke SIPTL — putusan berikutnya dicatat sesudah itu.',
        'Belum Ditindaklanjuti tidak pernah dipilih: itu keadaan awal yang terpasang sendiri saat unggahannya dicatat.',
      ]">
      <x-slot:kalimat>Berkas <b>{{ $nama }}</b> menunggu putusan BPK di SIPTL.</x-slot:kalimat>
      <x-baris-isi label="Diunggah ke SIPTL">
        <div class="kerja-teks">@if($x->siptl_tanggal)<span>{{ Tampil::tgl($x->siptl_tanggal) }}</span>@else{{ 'tidak tercatat' }}@endif<span class="kerja-meta">dikunci</span></div>
      </x-baris-isi>
      {{-- Pilihannya dua. BT keadaan awal yang terpasang sendiri; TD tidak
           pernah terjadi di lembar pemantauan. --}}
      <x-baris-isi label="Putusan BPK" gabung wajib kosong :kunci="'putusan-bpk-'.$k"
        info="Dicatat sekali untuk unggahan ini, lalu terkunci.">
        <div class="kerja-pilih" role="group" aria-label="Putusan BPK">
          <button type="button" class="ok" aria-pressed="false" data-kartu-status="SS">
            <x-ikon n="CheckCircle2" :s="18" /><b>SS</b><span>{{ StatusTindakLanjut::SS->pendek() }}</span>
          </button>
          <button type="button" class="bad" aria-pressed="false" data-kartu-status="BS">
            <x-ikon n="RotateCcw" :s="18" /><b>BS</b><span>{{ StatusTindakLanjut::BS->pendek() }}</span>
          </button>
        </div>
      </x-baris-isi>
      {{-- Tanggal dan catatannya baru ditanyakan sesudah ada putusannya. --}}
      <div class="kerja-susul" data-bila-pilih hidden>
      <x-baris-isi label="Tanggal pemantauan" :untuk="'pantau-'.$k" info="Kosongkan bila dipantau hari ini.">
        <input id="pantau-{{ $k }}" type="date" name="tgl_pantau" max="{{ $hari }}">
      </x-baris-isi>
      {{-- Catatannya wajib kalau Belum Sesuai: kalimat itulah yang menjelaskan
           kenapa berkasnya dikembalikan. Bintang dan keterangannya ditukar
           skrip menurut putusan yang dipilih. --}}
      <x-baris-isi label="Catatan BPK" :untuk="'catatan-bpk-'.$k" bila="BS"
        info="Salin apa yang tertulis di SIPTL untuk satuan kerja ini.">
        <textarea id="catatan-bpk-{{ $k }}" rows="3" maxlength="500" name="catatan"
          placeholder="Salin apa yang tertulis di SIPTL untuk satuan kerja ini." data-hitung-huruf data-catatan-bpk></textarea>
        <div class="hitunghuruf" data-jumlah-huruf>0/500</div>
      </x-baris-isi>
      </div>
      <div class="kerja-susul" data-akibat-bs hidden><x-baris-lajur>
        <div class="kerja-awas bad">
          <x-ikon n="AlertTriangle" :s="15" />
          <span>Sesudah disimpan, statusnya terkunci. Yang bisa dikerjakan berikutnya pada baris ini cuma mengirim berkasnya ulang ke satuan kerja.</span>
        </div>
      </x-baris-lajur></div>
      <x-slot:aksi>
        <button type="submit" class="btn btn-p" data-pastikan="{}" data-simpan-siptl>
          <x-ikon n="Check" :s="14" /> Simpan hasil
        </button>
      </x-slot:aksi>
    </x-panel-kerja>
  </form>

@elseif($perluUlang)
  {{-- ---- 3. Mengirim ulang yang ditolak BPK ---- --}}
  @php
    $pastikanUlang = ['judul' => 'Kirim ulang ke '.$siapa.' untuk pemberkasan ulang?',
      'ket' => 'Tandanya berubah jadi belum memadai, berkasnya kembali ke mejanya, dan catatan ini yang dibacanya bersama keterangan Setba. Catatan unggahannya dilepas, jadi sesudah diperbaiki berkasnya diunggah ulang ke SIPTL.',
      'tombol' => 'Ya, kirim ulang', 'nada' => 'jingga'];
    $lanjutUlang = 'Berkas kembali ke '.$nama.' untuk diperbaiki, lalu diunggah ulang ke SIPTL.';
  @endphp
  <form method="post" action="{{ route('siptl.ulangBpk', $r) }}" data-form-ulang-bpk data-lanjut="{{ $lanjutUlang }}">
    @csrf
    <input type="hidden" name="sasaran_id" value="{{ $x->id }}">
    <x-panel-kerja ikon="RotateCcw" judul="Kirim ulang ke satuan kerja" wajib
      :ket="$x->catatan_bpk ? '' : 'Catatan untuk satuan kerja harus terisi'" :lanjut="$lanjutUlang"
      :info="[
        'BPK menilai tindak lanjut ini Belum Sesuai. Setba yang mengirimkannya ulang ke satuan kerja.',
        'Catatan unggahannya dilepas: sesudah diperbaiki, berkas yang baru yang diunggah ke SIPTL.',
        'Yang dikirim ulang hanya satuan kerja pada baris ini. Yang lain tetap di tempatnya.',
      ]">
      <x-slot:kalimat>Berkas <b>{{ $nama }}</b> dikembalikan untuk pemberkasan ulang.</x-slot:kalimat>
      <x-baris-isi label="Ditolak BPK">
        <div class="kerja-teks">{{ $x->catatan_bpk ?: 'Tanpa catatan.' }}@if($x->tgl_pantau)<span class="kerja-meta">dipantau {{ Tampil::tgl($x->tgl_pantau) }}</span>@endif</div>
      </x-baris-isi>
      @if((int) $x->nilai > 0)
        <x-baris-isi label="Nilai tak diterima" :untuk="'nilai-bpk-'.$k"
          :info="['Bagian nilai yang buktinya tidak diterima BPK.', 'Kosongkan bila seluruh nilainya diterima.']">
          <div class="fb-sebaris">
            <input id="nilai-bpk-{{ $k }}" type="text" class="mono fb-pendek" inputmode="numeric" placeholder="0" name="nilai">
            <span class="fb-catatan">dari nilai {{ Tampil::rupiah($x->nilai) }}</span>
          </div>
        </x-baris-isi>
      @endif
      <x-baris-isi label="Catatan untuk satuan kerja" :untuk="'alasan-bpk-'.$k" wajib
        :info="'Kalimat inilah yang dibaca '.$nama.' saat berkasnya kembali'.($x->catatan_bpk ? ' — diisikan dari catatan BPK di atas.' : '.')">
        <textarea id="alasan-bpk-{{ $k }}" rows="3" maxlength="500" name="alasan" aria-required="true"
          placeholder="Sebutkan apa yang kurang di satuan kerja ini." data-hitung-huruf data-alasan-bpk>{{ $x->catatan_bpk }}</textarea>
        <div class="hitunghuruf" data-jumlah-huruf>{{ mb_strlen((string) $x->catatan_bpk) }}/500</div>
      </x-baris-isi>
      <x-baris-isi label="Dokumen yang diminta" gabung :kunci="'dok-bpk-'.$k" :info="[
        'Isi kalau catatan BPK memang meminta dokumen tertentu.',
        'Satuan kerja wajib melampirkan seluruhnya sebelum bisa mengirim ulang ke Setba.',
      ]">
        @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => [''], 'placeholder' => 'Nama dokumen', 'tambah' => 'Tambah dokumen'])
      </x-baris-isi>
      <x-baris-isi label="Keterangan Setba" :untuk="'ket-bpk-'.$k"
        info="Gunanya memperjelas maksud BPK supaya satuan kerja mudah memperbaikinya.">
        <textarea id="ket-bpk-{{ $k }}" rows="2" name="keterangan" maxlength="500"
          placeholder="Perjelas maksud catatan BPK supaya satuan kerja mudah memperbaikinya."></textarea>
      </x-baris-isi>
      <x-slot:aksi>
        <button type="submit" class="btn btn-p" data-kirim-ulang-bpk data-pastikan='@json($pastikanUlang)'>
          <x-ikon n="RotateCcw" :s="14" /> Kirim ulang ke satuan kerja
        </button>
      </x-slot:aksi>
    </x-panel-kerja>
  </form>
@endif
