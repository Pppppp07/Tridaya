@php
  use App\Support\Tampil;

  /* Satuan kerja mengisi tindak lanjut satu baris. Isian dimuat dari draf; hitungan tombol oleh
     skrip, penjaganya di TanggapanController. */
  $draf = $x->draf;
  $bukti = collect($draf?->bukti ?? []);
  $setoran = collect($draf?->setoran ?? []);
  $semuaItem = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item;
  $item = $semuaItem->where('terpenuhi', false)->values();
  $dana = $r->progresDana($x->satker_id, $x->tindakan_id);
  $angsur = $r->rencanaAngsur();
  $bentukLain = $r->tindakan->filter(fn ($tk) => $r->semuaBaris()->contains(fn ($y) => $y->tindakan_id === $tk->id && $y->satker_id === $x->satker_id))->count() > 1;
  $berkasLama = $r->lampiran->filter(fn ($d) => $d->label_oleh === $x->satker->namaPendek()
    && (! $d->tindakan_id || $d->tindakan_id === $x->tindakan_id))->map(fn ($d) => $d->nama_asli)->values();
  $idx = 0;
  /* Dikirim ulang: penolak, alasan, batas waktu, dan keterangan Setba, tepat di atas isian
     perbaikan. */
  $kembali = $x->kembali_dari || $x->batas_perbaikan;
  $lewat = $x->batas_perbaikan && $x->batas_perbaikan->copy()->startOfDay()->lt(now()->startOfDay());
  /* Isian pemulihan terbuka sendiri bila sudah ada barisnya. */
  $bukaPulih = $r->pemulihan->count() + $setoran->count() > 0;
  $k = $x->id;
@endphp

<form method="post" action="{{ route('tanggapan.simpan', $x) }}" data-panel-balai
  data-target="{{ $dana['target'] ?? 0 }}" data-masuk="{{ $dana['masuk'] ?? 0 }}"
  data-angsur-rencana="{{ $angsur['rencana'] ?? 0 }}" data-angsur-kunci="{{ ($angsur['kunci'] ?? false) ? 1 : 0 }}"
  data-angsur-sudah="{{ $r->pemulihan->count() }}" data-setoran-lama="{{ $r->pemulihan->count() }}"
  data-bentuk="{{ $x->tindakan?->namaBentuk() }}" data-bentuk-lain="{{ $bentukLain ? 1 : 0 }}"
  data-dok-sudah="{{ $semuaItem->where('terpenuhi', true)->count() }}"
  data-berkas-lama='@json($berkasLama)'>
  @csrf
  <input type="hidden" name="tanggal" value="{{ $draf?->tanggal?->toDateString() ?? now()->toDateString() }}">

  <x-panel-kerja ikon="ClipboardCheck" judul="Isi tindak lanjut" wajib
    info="Tanggapan dan bukti pada form ini hanya untuk bentuk tindak lanjut yang sedang dibuka.">
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

    <x-baris-isi label="Apa yang sudah dikerjakan" :untuk="'uraian-'.$k" wajib
      info="Jelaskan tindakan yang sudah dilakukan dan hasilnya. Setba menggunakan uraian ini untuk meninjau bukti yang dikirim.">
      <textarea id="uraian-{{ $k }}" rows="3" name="uraian" aria-required="true"
        placeholder="Jelaskan tindakan yang sudah diambil pada tahap ini." data-uraian>{{ $draf?->uraian }}</textarea>
    </x-baris-isi>

    {{-- Butir terpenuhi begitu link-nya lengkap (tidak ada centang terpisah). Boleh dicicil:
         tanpa bintang wajib. --}}
    @if($item->isNotEmpty())
      <x-baris-isi label="Dokumen yang diminta" gabung :kunci="'dok-'.$k" :info="[
        'Bukti yang diminta untuk penugasan ini. Isi judul dan link file pada setiap dokumen.',
        'Dokumen boleh dikirim bertahap. Yang belum terlampir tetap tercatat dan bisa dikirim menyusul.',
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

    {{-- Pemulihan nilai dibuka bila diperlukan, bukan langkah tetap. --}}
    @if($dana)
      <x-baris-isi label="Pemulihan nilai" gabung :kunci="'pulih-'.$k"
        :info="$angsur ? 'Rencana '.$angsur['rencana'].' transaksi angsuran'.(($angsur['kunci'] ?? false) ? '; jumlah transaksi dibatasi sesuai rencana' : '; jumlah transaksi dapat berbeda dari rencana').'.' : 'Catat nilai pemulihan dari setoran ke kas negara, perbaikan fisik, atau pengembalian barang, beserta buktinya.'">
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

    {{-- Yang dipantau disebut kepada yang dipantau: draf terbaca Setba. --}}
    @if($draf)
      <x-baris-lajur>
        <p class="kerja-teks kerja-redup">Draf tersimpan {{ $draf->updated_at ? Tampil::tglJam($draf->updated_at) : Tampil::tgl($draf->terakhir) }}. Setba dapat memantau kemajuannya, tetapi draf belum terkirim.</p>
      </x-baris-lajur>
    @endif

    <x-slot:aksi>
      {{-- Menyimpan tidak memindahkan berkas; mengirim memindahkannya. --}}
      <button type="submit" class="btn" name="aksi" value="draf" data-simpan-draf>
        <x-ikon n="Check" :s="14" /> {{ $draf ? 'Perbarui draf' : 'Simpan draf' }}
      </button>
      <x-info :teks="[
        'Simpan draf untuk melanjutkan pengisian nanti. Draf belum terkirim dan berkas tetap di unit Anda sampai Anda menekan Kirim ke Setba.',
        'Hanya Setba yang dapat memantau kemajuan draf: uraian, dokumen yang sudah disiapkan, nilai pemulihan, serta nama dan waktu penyimpanannya. Link berkas di draf tidak ditampilkan.',
        'Setba menerima pemberitahuan di sistem (tanpa email) setiap kali draf disimpan.',
      ]" />
      <button type="submit" class="btn btn-p" name="aksi" value="kirim" data-kirim-setba data-pastikan='{}'>
        <x-ikon n="Send" :s="14" /> Kirim ke Setba
      </button>
    </x-slot:aksi>
  </x-panel-kerja>
</form>
