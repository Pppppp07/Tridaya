@php
  use App\Aksi\Kemajuan;
  use App\Support\Tampil;

  /* Satuan kerja mengisi tindak lanjut satu baris — padanan PanelBalai.
     Isian dimuat dari draf yang tersimpan, bukan dari kosong: itu yang
     membuatnya bisa disunting lagi. Hitungan tombol dan kalimat bilah
     dikerjakan skrip; penjaganya tetap di TanggapanController. Sejak 25 Sep
     berbentuk panel kerja (`x-panel-kerja`): baris berlabel di kiri, bintang
     pada yang wajib. */
  $draf = $x->draf;
  $bukti = collect($draf?->bukti ?? []);
  $setoran = collect($draf?->setoran ?? []);
  $item = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item->where('terpenuhi', false)->values();
  $dana = $r->progresDana($x->satker_id, $x->tindakan_id);
  $angsur = $r->rencanaAngsur();
  $kirimDraf = $draf ? ['umur' => \App\Models\Rekomendasi::selisih($draf->terakhir)] : null;
  if ($kirimDraf) {
    $kirimDraf['sisa'] = max(0, Kemajuan::HARI_KIRIM_OTOMATIS - $kirimDraf['umur']);
    $kirimDraf['jatuh'] = $kirimDraf['umur'] >= Kemajuan::HARI_KIRIM_OTOMATIS;
    $kirimDraf['hari'] = Kemajuan::HARI_KIRIM_OTOMATIS;
  }
  $bentukLain = $r->tindakan->filter(fn ($tk) => $r->semuaBaris()->contains(fn ($y) => $y->tindakan_id === $tk->id && $y->satker_id === $x->satker_id))->count() > 1;
  $berkasLama = $r->lampiran->filter(fn ($d) => $d->label_oleh === $x->satker->namaPendek()
    && (! $d->tindakan_id || $d->tindakan_id === $x->tindakan_id))->map(fn ($d) => $d->nama_asli)->values();
  $idx = 0;
  /* Dikirim ulang untuk pemberkasan ulang: siapa yang menolak, alasannya,
     batas waktunya, dan keterangan Setba — berbaris seperti isian lain,
     tepat di atas isian perbaikannya. Dokumen yang diminta tidak diulang di
     sana: ia sudah jadi daftar yang harus dilampiri di bawah. */
  $kembali = $x->kembali_dari || $x->batas_perbaikan;
  $lewat = $x->batas_perbaikan && $x->batas_perbaikan->copy()->startOfDay()->lt(now()->startOfDay());
  /* Isian pemulihan nilai terbuka sendiri kalau sudah ada barisnya — draf
     yang dimuat ulang tidak boleh menyembunyikan isian yang sudah ditulis. */
  $bukaPulih = $r->pemulihan->count() + $setoran->count() > 0;
  $k = $x->id;
@endphp

<form method="post" action="{{ route('tanggapan.simpan', $x) }}" data-panel-balai
  data-target="{{ $dana['target'] ?? 0 }}" data-masuk="{{ $dana['masuk'] ?? 0 }}"
  data-angsur-rencana="{{ $angsur['rencana'] ?? 0 }}" data-angsur-kunci="{{ ($angsur['kunci'] ?? false) ? 1 : 0 }}"
  data-angsur-sudah="{{ $r->pemulihan->count() }}" data-setoran-lama="{{ $r->pemulihan->count() }}"
  data-bentuk="{{ $x->tindakan?->namaBentuk() }}" data-bentuk-lain="{{ $bentukLain ? 1 : 0 }}"
  data-berkas-lama='@json($berkasLama)'
  data-kirim-draf='@json($kirimDraf)'>
  @csrf
  <input type="hidden" name="tanggal" value="{{ $draf?->tanggal?->toDateString() ?? now()->toDateString() }}">

  <x-panel-kerja ikon="ClipboardCheck" judul="Isi tindak lanjut" wajib
    info="Isian ini khusus untuk bentuk tindak lanjut tersebut. Bentuk lain pada rekomendasi yang sama punya isiannya sendiri.">
    <x-slot:kalimat>@if($x->tindakan?->bentuk)Tindak lanjut <b>{{ $x->tindakan->namaBentuk() }}</b> dari unit Anda, dikirim ke Setba.@else{{ 'Tindak lanjut unit Anda, dikirim ke Setba.' }}@endif</x-slot:kalimat>

    @if($kembali)
      <div class="fb-subkep kerja-subkep"><b>Dikembalikan untuk pemberkasan ulang</b></div>
      <x-baris-isi label="Ditolak">
        <div class="kerja-teks">{{ $x->kembali_dari ?: 'Inspektorat' }}, lalu dikirim ulang Setba.</div>
      </x-baris-isi>
      @if($x->alasan_perbaikan)
        <x-baris-isi label="Alasan"><div class="kerja-teks">{{ $x->alasan_perbaikan }}</div></x-baris-isi>
      @endif
      @if($x->batas_perbaikan)
        <x-baris-isi label="Batas perbaikan">
          <div class="kerja-teks">{{ Tampil::tgl($x->batas_perbaikan) }}@if($lewat)<span class="kerja-meta lewat">sudah lewat</span>@endif</div>
        </x-baris-isi>
      @endif
      @if($x->keterangan_setba)
        <x-baris-isi label="Keterangan Setba"><div class="kerja-teks">{{ $x->keterangan_setba }}</div></x-baris-isi>
      @endif
      <div class="fb-subkep kerja-subkep"><b>Laporan perbaikan</b></div>
    @endif

    {{-- Uraian di paling atas. Kalimat inilah yang dibaca Setba lebih dulu,
         sebelum ia membuka berkasnya — jadi ia juga yang ditulis lebih dulu. --}}
    <x-baris-isi label="Apa yang sudah dikerjakan" :untuk="'uraian-'.$k" wajib
      info="Kalimat ini yang dibaca Setba lebih dulu, sebelum membuka berkasnya.">
      <textarea id="uraian-{{ $k }}" rows="3" name="uraian" aria-required="true"
        placeholder="Jelaskan tindakan yang sudah diambil pada tahap ini." data-uraian>{{ $draf?->uraian }}</textarea>
    </x-baris-isi>

    {{-- Link langsung pada butirnya. Satu tindakan, bukan centang lalu unggah
         di tempat lain. Butir terpenuhi begitu link-nya lengkap. --}}
    @if($item->isNotEmpty())
      <x-baris-isi label="Dokumen yang diminta" gabung wajib kosong :kunci="'dok-'.$k" :info="[
        'Setiap dokumen dilampirkan sebagai link: judul berkas dan alamatnya.',
        'Berkas baru bisa dikirim kalau seluruhnya sudah terlampir.',
      ]">
        <ul class="ceklis kerja-dok">
          @foreach($item as $i)
            @php $b = $bukti->first(fn ($y) => (int) ($y['untuk'] ?? 0) === $i->id); $n = $idx++; @endphp
            <li data-butir="{{ $i->id }}" @class(['buka' => (bool) $b])>
              <span class="tanda" data-tanda-butir><x-ikon n="CheckCircle2" :s="16" data-ikon-ada hidden /><x-ikon n="Circle" :s="16" data-ikon-belum /></span>
              <span class="isi">
                <span class="nama">{{ $i->nama }}</span>
                <span class="fb-dua" data-isi-butir @if(! $b) hidden @endif>
                  <input type="text" name="bukti[{{ $n }}][nama]" value="{{ $b['nama'] ?? '' }}" aria-label="Judul berkas {{ $i->nama }}"
                    placeholder="Judul, mis. {{ $i->nama }}" @if(! $b) disabled @endif data-bukti-nama>
                  <input type="text" class="mono" name="bukti[{{ $n }}][link]" value="{{ $b['link'] ?? '' }}" aria-label="Link berkas {{ $i->nama }}"
                    placeholder="Link, mis. https://…" @if(! $b) disabled @endif data-bukti-link>
                </span>
                <input type="hidden" name="bukti[{{ $n }}][jenis]" value="{{ $i->nama }}" @if(! $b) disabled @endif data-bukti-lain>
                <input type="hidden" name="bukti[{{ $n }}][untuk]" value="{{ $i->id }}" @if(! $b) disabled @endif data-bukti-lain>
              </span>
              <button type="button" class="fb-x" aria-label="Hapus link {{ $i->nama }}" data-hapus-butir @if(! $b) hidden @endif><x-ikon n="X" :s="14" /></button>
              <button type="button" class="btn btn-s" data-tambah-butir @if($b) hidden @endif><x-ikon n="ExternalLink" :s="13" /> Tambah link</button>
            </li>
          @endforeach
        </ul>
        <span class="fb-catatan" data-sisa-dok>{{ $item->count() }} dari {{ $item->count() }} belum dilampirkan</span>
        <span class="fb-catatan salah" data-minta-kurang hidden></span>
      </x-baris-isi>
    @endif

    <x-baris-isi :label="$item->isNotEmpty() ? 'Link lain' : 'Link berkas'" gabung :kunci="'lepas-'.$k"
      :info="$item->isNotEmpty() ? 'Bukti lain di luar dokumen yang diminta.' : 'Bukti tindak lanjut yang dikirim ke Setba.'">
      <div class="fb-dok" data-berkas-lepas>
        @foreach($bukti->filter(fn ($y) => empty($y['untuk'])) as $b)
          @php $n = $idx++; @endphp
          <div class="fb-dok-brs" data-lepas>
            <span style="flex:1;min-width:0">
              <span class="fb-dua">
                <input type="text" name="bukti[{{ $n }}][nama]" value="{{ $b['nama'] ?? '' }}" aria-label="Judul berkas"
                  placeholder="Judul, mis. Bukti setor dan Nota Konfirmasi KPPN" data-bukti-nama>
                <input type="text" class="mono" name="bukti[{{ $n }}][link]" value="{{ $b['link'] ?? '' }}" aria-label="Link berkas"
                  placeholder="Link, mis. https://…" data-bukti-link>
              </span>
              <input type="hidden" name="bukti[{{ $n }}][jenis]" value="{{ $b['jenis'] ?? 'Bukti dukung' }}">
            </span>
            <button type="button" class="fb-x" aria-label="Hapus link" data-hapus-lepas><x-ikon n="X" :s="14" /></button>
          </div>
        @endforeach
        <button type="button" class="fb-link" data-tambah-lepas>
          <x-ikon n="Plus" :s="13" /> {{ $item->isNotEmpty() ? 'Tambah link lain' : 'Tambah link berkas' }}
        </button>
      </div>
      <template data-templat-lepas>
        <div class="fb-dok-brs" data-lepas>
          <span style="flex:1;min-width:0">
            <span class="fb-dua">
              <input type="text" name="bukti[__i__][nama]" aria-label="Judul berkas"
                placeholder="Judul, mis. Bukti setor dan Nota Konfirmasi KPPN" data-bukti-nama>
              <input type="text" class="mono" name="bukti[__i__][link]" aria-label="Link berkas"
                placeholder="Link, mis. https://…" data-bukti-link>
            </span>
            <input type="hidden" name="bukti[__i__][jenis]" value="Bukti dukung">
          </span>
          <button type="button" class="fb-x" aria-label="Hapus link" data-hapus-lepas><x-ikon n="X" :s="14" /></button>
        </div>
      </template>
      <span class="fb-catatan salah" data-lepas-kurang hidden></span>
    </x-baris-isi>

    {{-- Bukan langkah bernomor yang selalu berdiri di formulir. Kata Bang
         Kamal: "setelah uraian, buka... pemulihan nilai? Kalau dia pemulihan
         nilai, klik-klik pemulihan nilai, muncul form ini." --}}
    @if($dana)
      <x-baris-isi label="Pemulihan nilai" gabung :kunci="'pulih-'.$k"
        :info="$angsur ? 'Rencana '.$angsur['rencana'].' angsuran'.(($angsur['kunci'] ?? false) ? ', dikunci pada jumlah itu' : '').'.' : 'Setoran ke kas negara, atau perbaikan fisik dan pengembalian barang.'">
        <div class="kerja-setor-kep">
          <span class="bar dana"><i data-bar-pulih style="width:0%"></i></span>
          <b class="mono" data-bakal>Rp 0</b>
          <span>dari {{ Tampil::rupiah($dana['target']) }}</span>
          @if($angsur)<span class="fb-catatan" data-tanda-angsur hidden></span>@endif
        </div>
        <span class="fb-catatan salah" data-lebih hidden></span>
        {{-- Langsung dengan baris pertamanya: yang menekan memang mau mencatat. --}}
        <button type="button" class="fb-link" data-buka-pulih @if($bukaPulih) hidden @endif>
          <x-ikon n="Wallet" :s="14" /> Catat pemulihan nilai
        </button>
        <div class="kerja-setor" data-isi-pulih @if(! $bukaPulih) hidden @endif>
          @foreach($setoran->values() as $n => $st)
            @include('rekomendasi.bagian.baris-setor', ['n' => $n, 'st' => $st])
          @endforeach
          <button type="button" class="fb-link" data-tambah-setor>
            <x-ikon n="Plus" :s="13" /> Tambah baris pemulihan
          </button>
          <span class="fb-catatan" data-kuota-habis hidden>
            Rencana {{ $angsur['rencana'] ?? 0 }} angsuran sudah terpakai seluruhnya. Minta Setba menyesuaikan rencananya bila masih ada setoran lain.
          </span>
          <span class="fb-catatan" data-lewat-rencana hidden>Melebihi rencana {{ $angsur['rencana'] ?? 0 }} angsuran — tetap dicatat.</span>
          <span class="fb-catatan salah" data-setor-kurang hidden></span>
        </div>
        <template data-templat-setor>
          @include('rekomendasi.bagian.baris-setor', ['n' => '__i__', 'st' => []])
        </template>
      </x-baris-isi>
    @endif

    {{-- Hitungan mundur kiriman otomatis hanya ditampilkan saat benar-benar
         berjalan — selama kewajibannya belum tuntas, yang perlu dibaca adalah
         keterangan kekurangan di bilah bawah. --}}
    <x-baris-lajur data-pesan-kirim-draf hidden>
      <div class="kerja-awas tenang" data-kotak-kirim-draf>
        <x-ikon n="Clock" :s="15" /><span data-teks-kirim-draf></span>
      </div>
    </x-baris-lajur>

    <x-slot:aksi>
      {{-- Dua tombol, dua maksud berbeda: menyimpan tidak memindahkan berkas,
           mengirim memindahkannya. --}}
      <button type="submit" class="btn" name="aksi" value="draf" data-simpan-draf>
        <x-ikon n="Check" :s="14" /> {{ $draf ? 'Perbarui draf' : 'Simpan draf' }}
      </button>
      <x-info teks="Berkas tidak pindah ke mana-mana. Isinya masih bisa diubah sampai Anda menekan Kirim." />
      <button type="submit" class="btn btn-p" name="aksi" value="kirim" data-kirim-setba data-pastikan='{}'>
        <x-ikon n="Send" :s="14" /> Kirim ke Setba
      </button>
    </x-slot:aksi>
  </x-panel-kerja>
</form>
