{{-- Panel satu rekomendasi, urut seperti membaca LHP. Nilai dan angsuran hanya tampil bila
     sifatnya menuntut setoran. --}}
@php
  use App\Support\Tampil;

  $h = $form->huruf($j);
  $tl = $r['tindakan'];
  $nPen = $form->barisRek($r);
  $nilai = $form->nilaiRek($r);
  $sifatR = $sifat->firstWhere('id', (int) $r['sifat']);
  /* Yang menentukan keterangan "menuntut penyetoran" dari data master, bukan nama sifatnya. */
  $uang = (bool) $sifatR?->perlu_nilai;
  $ref = trim($r['ref_lhp']);
  /* Draf lama tanpa tanda: otomatis bila kosong atau sama dengan usulannya (aturan server). */
  $refOtomatis = $r['ref_otomatis'] ?? ($ref === '' || $ref === $form->refUsulan((string) $t['nomor'], $j));
  /* Disalin dari surat (bentuknya berbeda tiap tahun); Ref IDT dirakit dari kode ini. */
  $tahun = substr($d['surat']['tgl_surat'] ?: '', 0, 4);
  $noSurat = explode('/', $d['surat']['nomor'])[0] ?? '';
  $ringkasR = implode(' · ', array_filter([
    $ref !== '' ? 'Ref '.$ref : null,
    $sifatR?->nama,
    count($tl).' tindak lanjut',
    $nPen > 0 ? $nPen.' penugasan' : null,
    $nilai > 0 ? Tampil::rupiahSingkat($nilai) : null,
  ]));
  $idR = 'fb-'.$r['id'];
  $nama = 'tem['.$t['id'].'][rekom]['.$r['id'].']';
  $kAktif = min((int) ($d['tl'][$r['id']] ?? 0), max(0, count($tl) - 1));
  $angsur = (int) $r['angsur'];
  $berisi = trim($r['uraian']) !== '' || $nPen > 0;
  $tanyaHapus = ['judul' => 'Hapus rekomendasi '.$h.'?', 'ket' => 'Uraian dan tindak lanjutnya ikut terhapus.',
    'tombol' => 'Ya, hapus rekomendasi', 'nada' => 'merah'];
@endphp
<section id="fb-rek-{{ $r['id'] }}" role="tabpanel" aria-labelledby="fb-tab-rek-{{ $r['id'] }}" class="fb-rek buka"
  @unless($tampilR) hidden @endunless data-panel-rek="{{ $r['id'] }}" data-blok-rek>
  <x-kepala-panel :no="$h" :judul="$form->intiUraian(trim($r['uraian']))" kosong="Uraian belum diisi" :ringkas="$ringkasR" :ok="$form->rekOk($r)">
    @if(count($t['rekom']) > 1)
      <x-slot:aksi>
        <button type="submit" name="aksi" value="hapus-rek:{{ $t['id'] }}:{{ $r['id'] }}" class="fb-hapus" aria-label="Hapus rekomendasi {{ $h }}"
          @if($berisi) data-pastikan='@json($tanyaHapus)' @endif>
          Hapus<span class="fb-hapus-apa"> rekomendasi</span>
        </button>
      </x-slot:aksi>
    @endif
  </x-kepala-panel>
  <div class="fb-isi">
    <x-baris-isi label="Uraian" untuk="{{ $idR }}-uraian" :wajib="true">
      <textarea id="{{ $idR }}-uraian" name="{{ $nama }}[uraian]" rows="2" aria-required="true"
        placeholder="Apa yang harus dilakukan satuan kerja" data-ikut-judul="rek:{{ $r['id'] }}">{{ $r['uraian'] }}</textarea>
    </x-baris-isi>
    <x-baris-isi label="Ref LHP" untuk="{{ $idR }}-ref"
      :info="['Nomor rujukan rekomendasi dalam laporan pemeriksaan, misalnya 1.1.a.', 'Awalnya terisi dari nomor temuan dan huruf rekomendasi. Sesuaikan jika rujukan pada surat berbeda; jika dikosongkan, rujukan otomatis digunakan.']">
      <div class="fb-sebaris">
        <input id="{{ $idR }}-ref" type="text" name="{{ $nama }}[ref_lhp]" class="mono fb-pendek" value="{{ $r['ref_lhp'] }}" placeholder="II.4.4.f"
          data-ref-lhp="{{ $h }}" data-ref-otomatis="{{ $refOtomatis ? 1 : 0 }}">
        @if($tahun && $noSurat && $ref !== '')
          <span class="fb-catatan">Ref IDT <span class="mono">{{ $tahun }}.{{ $noSurat }}.{{ $ref }}</span></span>
        @endif
      </div>
    </x-baris-isi>
    {{-- Mengganti sifat langsung menyegarkan halaman. --}}
    <x-baris-isi label="Sifat" untuk="{{ $idR }}-sifat" :wajib="true" info="Jenis kewajiban rekomendasi. Sifat yang memerlukan pemulihan nilai menampilkan isian nilai untuk tiap Satker.">
      <select id="{{ $idR }}-sifat" name="{{ $nama }}[sifat]" class="fb-sedang" aria-required="true" data-sifat>
        @foreach($sifat->filter(fn ($x) => $x->aktif || (string) $r['sifat'] === (string) $x->id) as $x)
          <option value="{{ $x->id }}" data-uang="{{ $x->perlu_nilai ? 1 : 0 }}"
            @selected((string) $r['sifat'] === (string) $x->id)>{{ $x->nama }}{{ $x->aktif ? '' : ' (nonaktif)' }}</option>
        @endforeach
      </select>
    </x-baris-isi>

    <div class="fb-sub fb-sub-tl">
      <div class="fb-subkep">
        <b>Tindak lanjut yang diminta</b>
        <span class="fb-ket">{{ count($tl) }} tindak lanjut · {{ $nPen }} penugasan</span>
      </div>
      <div class="fb-tab-baris">
        <div role="tablist" aria-label="Tindak lanjut rekomendasi {{ $h }}" class="fb-tabs">
          @foreach($tl as $k => $x)
            <x-tab-isi :kecil="true" id="fb-tab-tl-{{ $r['id'] }}-{{ $k }}" panel="fb-tl-{{ $r['id'] }}-{{ $k }}"
              aksi="tl:{{ $r['id'] }}:{{ $k }}" :aktif="$k === $kAktif" :no="$k + 1" :teks="$form->namaBentuk((string) $x['bentuk'])"
              kosong="Bentuk belum diisi" :ok="$form->tindakanOk($x)" />
          @endforeach
        </div>
        <button type="submit" name="aksi" value="tambah-tindakan:{{ $t['id'] }}:{{ $r['id'] }}" class="fb-tambah kecil">
          <x-ikon n="Plus" :s="13" /> Tambah tindak lanjut
        </button>
      </div>
      @foreach($tl as $k => $tk)
        @include('laporan.bagian.fb-tl', ['tk' => $tk, 'k' => $k, 'nTl' => count($tl), 'uang' => $uang, 'tampilK' => $k === $kAktif])
      @endforeach
      <input type="hidden" name="ui[tl][{{ $r['id'] }}]" value="{{ $kAktif }}" data-ui-tl>
    </div>

    {{-- Dijumlah ulang skrip selagi diketik; tanpa skrip sesudah kiriman berikutnya. --}}
    @if($uang)
      <x-baris-isi label="Nilai yang harus dipulihkan" kunci="{{ $idR }}-nilai" :gabung="true"
        info="Total nilai yang harus dipulihkan, dihitung dari nilai tiap satuan kerja pada seluruh bentuk tindak lanjut. Angka ini bukan jumlah pemulihan yang sudah dilakukan.">
        <div class="fb-hitung">
          <b class="mono" data-nilai-rek>{{ $nilai > 0 ? Tampil::rupiah($nilai) : 'Rp 0' }}</b>
          <span data-nilai-kosong @if($nilai) hidden @endif>isi nilai tiap satuan kerja di tindak lanjutnya</span>
        </div>
      </x-baris-isi>
    @endif
    {{-- Selalu dirender supaya bisa muncul begitu nilainya diketik. --}}
    <div data-angsur-rek @if($nilai <= 0) hidden @endif>
      <x-baris-isi label="Rencana angsuran" untuk="{{ $idR }}-angsur">
        <div class="fb-sebaris">
          <div class="cacah fb-cacah">
            <input id="{{ $idR }}-angsur" type="text" name="{{ $nama }}[angsur]" class="mono" inputmode="numeric"
              value="{{ $r['angsur'] }}" placeholder="sekaligus" data-angsur>
            <span class="tombol">
              <button type="submit" name="aksi" value="angsur:naik:{{ $t['id'] }}:{{ $r['id'] }}" aria-label="Tambah satu angsuran"
                @disabled($angsur >= 24) data-angsur-naik><x-ikon n="ChevronUp" :s="13" /></button>
              <button type="submit" name="aksi" value="angsur:turun:{{ $t['id'] }}:{{ $r['id'] }}" aria-label="Kurangi satu angsuran"
                @disabled(! $angsur) data-angsur-turun><x-ikon n="ChevronDown" :s="13" /></button>
            </span>
          </div>
          <span class="fb-catatan" data-angsur-ket>
            {{ $angsur > 1 ? 'kali · sekitar '.Tampil::rupiah((int) round($nilai / $angsur)).' per angsuran' : 'kosongkan bila dibayar sekaligus' }}
          </span>
          <label class="fb-centang">
            <input type="checkbox" name="{{ $nama }}[kunci]" value="1" @checked($r['kunci'] && $angsur) @disabled(! $angsur) data-angsur-kunci>
            <span>Kunci pada jumlah rencana</span>
            <x-info :teks="! $angsur
              ? 'Isi jumlah rencana angsuran untuk menggunakan pilihan ini.'
              : ($r['kunci']
                ? 'Jumlah transaksi angsuran dibatasi sesuai rencana. Batas ini mengatur jumlah transaksi, bukan nilai rupiahnya.'
                : 'Jumlah transaksi angsuran dapat berbeda dari rencana. Nilai yang harus dipulihkan tetap mengikuti penugasan.')" />
          </label>
        </div>
      </x-baris-isi>
    </div>
  </div>
</section>
