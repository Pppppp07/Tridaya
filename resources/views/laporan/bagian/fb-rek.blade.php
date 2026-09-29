{{-- Panel satu rekomendasi — padanan `bagianRek`. Urutannya seperti membaca
     LHP: bunyinya, rujukannya, sifatnya, lalu tindak lanjut yang diminta.
     Nilai dan angsuran menyusul di bawah karena angkanya dijumlah dari tindak
     lanjut, dan hanya tampil bila sifatnya menuntut uang.
     Masukan: $t, $r, $j, $tampilR. --}}
@php
  use App\Support\Tampil;

  $h = $form->huruf($j);
  $tl = $r['tindakan'];
  $nPen = $form->barisRek($r);
  $nilai = $form->nilaiRek($r);
  $sifatR = $sifat->firstWhere('id', (int) $r['sifat']);
  /* Keterangan "menuntut penyetoran" dari data master yang menentukan, bukan
     nama sifatnya. */
  $uang = (bool) $sifatR?->perlu_nilai;
  $ref = trim($r['ref_lhp']);
  /* Disalin dari suratnya, tidak dirakit sendiri — bentuknya berbeda tiap
     tahun LHP. Ref IDT-nya dirakit dari tahun, nomor surat, dan kode ini. */
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
      :info="['Salin persis dari laporannya, mis. 10.a atau II.4.4.f.', 'Kosongkan bila nomor temuan dan huruf rekomendasi sudah cukup.']">
      <div class="fb-sebaris">
        <input id="{{ $idR }}-ref" type="text" name="{{ $nama }}[ref_lhp]" class="mono fb-pendek" value="{{ $r['ref_lhp'] }}" placeholder="II.4.4.f">
        @if($tahun && $noSurat && $ref !== '')
          <span class="fb-catatan">Ref IDT <span class="mono">{{ $tahun }}.{{ $noSurat }}.{{ $ref }}</span></span>
        @endif
      </div>
    </x-baris-isi>
    {{-- Sifatnya yang menentukan apakah ada nilai yang ditagih, jadi mengganti
         sifat langsung menyegarkan halaman. --}}
    <x-baris-isi label="Sifat" untuk="{{ $idR }}-sifat" :wajib="true" info="Memisahkan yang menuntut setoran uang dari yang cukup perbaikan.">
      <select id="{{ $idR }}-sifat" name="{{ $nama }}[sifat]" class="fb-sedang" aria-required="true" data-sifat data-kirim>
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
              aksi="tl:{{ $r['id'] }}:{{ $k }}" :aktif="$k === $kAktif" :no="$k + 1" :teks="trim($x['bentuk'])"
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

    {{-- Angkanya dijumlah ulang oleh skrip selagi nilai tiap satuan kerja
         diketik; tanpa skrip, sesudah kiriman berikutnya. --}}
    @if($uang)
      <x-baris-isi label="Nilai dipulihkan" kunci="{{ $idR }}-nilai" :gabung="true"
        info="Dijumlah dari nilai tiap satuan kerja di tindak lanjutnya — tidak diketik sendiri.">
        <div class="fb-hitung">
          <b class="mono" data-nilai-rek>{{ $nilai > 0 ? Tampil::rupiah($nilai) : 'Rp 0' }}</b>
          <span data-nilai-kosong @if($nilai) hidden @endif>isi nilai tiap satuan kerja di tindak lanjutnya</span>
        </div>
      </x-baris-isi>
    @endif
    {{-- Rencana angsuran hanya berlaku kalau ada nilai yang ditagih. Selalu
         dirender supaya bisa muncul begitu nilainya diketik. --}}
    <div data-angsur-rek @if($nilai <= 0) hidden @endif>
      <x-baris-isi label="Rencana angsuran" untuk="{{ $idR }}-angsur">
        <div class="fb-sebaris">
          <div class="cacah fb-cacah">
            <input id="{{ $idR }}-angsur" type="text" name="{{ $nama }}[angsur]" class="mono" inputmode="numeric"
              value="{{ $r['angsur'] }}" placeholder="sekaligus" data-angsur>
            {{-- Diketik boleh, dinaik-turunkan juga boleh: angkanya kecil dan
                 sering dicoba-coba dulu. --}}
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
              ? 'Isi jumlah angsurannya dulu.'
              : ($r['kunci']
                ? 'Satuan kerja tidak bisa menyetor lebih banyak dari jumlah yang direncanakan.'
                : 'Satuan kerja boleh menyetor lebih atau kurang dari rencana.')" />
          </label>
        </div>
      </x-baris-isi>
    </div>
  </div>
</section>
