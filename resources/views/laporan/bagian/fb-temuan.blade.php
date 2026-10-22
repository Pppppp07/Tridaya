{{-- Panel satu temuan. Seluruh panel dirender; yang tidak terpilih disembunyikan. Masukan: $t,
     $i, $tampil. --}}
@php
  use App\Support\Tampil;

  $nilai = $form->nilaiTem($t);
  $nSat = count($t['satker']);
  $ringkasT = implode(' · ', array_filter([
    trim($t['nomor']) !== '' ? 'butir '.trim($t['nomor']) : null,
    count($t['rekom']).' rekomendasi',
    $nSat > 0 ? $nSat.' satuan kerja' : null,
    $nilai > 0 ? 'nilai '.Tampil::rupiahSingkat($nilai) : null,
  ]));
  $idT = 'fb-'.$t['id'];
  $nama = 'tem['.$t['id'].']';
  /* Rekomendasi terpilih: yang dibuka bila milik temuan ini, selain itu yang pertama. */
  $rAktif = collect($t['rekom'])->firstWhere('id', $d['buka'])['id'] ?? $t['rekom'][0]['id'];
  /* Menghapus yang sudah berisi ditanyakan dulu; yang masih kosong langsung. */
  $berisi = trim($t['judul']) !== '' || trim($t['sebab']) !== '' || trim($t['akibat']) !== ''
    || collect($t['rekom'])->contains(fn ($r) => trim($r['uraian']) !== '' || $form->barisRek($r));
  $tanyaHapus = ['judul' => 'Hapus temuan '.($i + 1).'?', 'ket' => 'Isiannya ikut terhapus, termasuk seluruh rekomendasinya.',
    'tombol' => 'Ya, hapus temuan', 'nada' => 'merah'];
  $kategoriSumber = $kategori->filter(fn ($k) => $k->sumber->value === $d['surat']['sumber']);
@endphp
<section id="fb-tem-{{ $t['id'] }}" role="tabpanel" aria-labelledby="fb-tab-tem-{{ $t['id'] }}" class="fb-bag fb-tem buka"
  @unless($tampil) hidden @endunless data-panel-tem="{{ $t['id'] }}">
  <x-kepala-panel :no="$i + 1" :judul="trim($t['judul'])" kosong="Belum berjudul" :ringkas="$ringkasT" :ok="$form->temOk($t)">
    @if(count($d['temuan']) > 1)
      <x-slot:aksi>
        <button type="submit" name="aksi" value="hapus-temuan:{{ $t['id'] }}" class="fb-hapus" aria-label="Hapus temuan {{ $i + 1 }}"
          @if($berisi) data-pastikan='@json($tanyaHapus)' @endif>
          Hapus<span class="fb-hapus-apa"> temuan</span>
        </button>
      </x-slot:aksi>
    @endif
  </x-kepala-panel>
  <div class="fb-isi">
    <x-baris-isi label="Judul temuan" untuk="{{ $idT }}-judul" :wajib="true" info="Ringkasan masalah yang ditemukan dalam pemeriksaan, ditulis dalam satu kalimat.">
      <input id="{{ $idT }}-judul" type="text" name="{{ $nama }}[judul]" aria-required="true" value="{{ $t['judul'] }}"
        placeholder="Satu kalimat yang menyebut perkaranya" data-ikut-judul="tem:{{ $t['id'] }}">
    </x-baris-isi>
    <x-baris-isi label="Nomor di surat" untuk="{{ $idT }}-nomor">
      <input id="{{ $idT }}-nomor" type="text" name="{{ $nama }}[nomor]" class="mono fb-pendek" value="{{ $t['nomor'] }}" placeholder="1.1"
        data-nomor-temuan>
    </x-baris-isi>
    <x-baris-isi label="Kategori temuan" untuk="{{ $idT }}-kategori" :wajib="true"
      :info="$d['surat']['sumber'] === 'LHA'
        ? 'Klasifikasi temuan sesuai LHA Inspektorat.'
        : 'Klasifikasi temuan sesuai LHP BPK.'">
      <select id="{{ $idT }}-kategori" name="{{ $nama }}[kategori]" class="fb-sedang" aria-required="true">
        <option value="">— pilih —</option>
        @foreach($kategoriSumber as $k)
          <option value="{{ $k->id }}" @selected((string) $t['kategori'] === (string) $k->id)>{{ $k->nama }}</option>
        @endforeach
        {{-- Yang sudah dipilih tetap tampil walau sudah dinonaktifkan. --}}
        @if($t['kategori'] && ! $kategoriSumber->firstWhere('id', (int) $t['kategori']))
          @php $lama = \App\Models\KategoriTemuan::find($t['kategori']); @endphp
          @if($lama)<option value="{{ $lama->id }}" selected>{{ $lama->nama }}</option>@endif
        @endif
      </select>
    </x-baris-isi>
    {{-- Daftar ini membatasi pilihan satuan kerja pada tiap rekomendasi; dicentang langsung
         terkirim. --}}
    <x-baris-isi label="Satuan kerja terperiksa" kunci="{{ $idT }}-satker" :gabung="true" :wajib="true" :kosong="! $nSat"
      :info="['Unit kerja yang menjadi objek pemeriksaan pada temuan ini.', 'Satker yang dipilih di sini menjadi pilihan pelaksana tindak lanjut untuk rekomendasi terkait.']">
      <x-pilih-satker :satker="$satker" :terpilih="$t['satker']" nama="{{ $nama }}[satker][]" :hanya-aktif="true" />
    </x-baris-isi>
    <x-baris-isi label="Sebab" untuk="{{ $idT }}-sebab" :wajib="true" info="Penyebab terjadinya masalah yang dijelaskan dalam laporan pemeriksaan.">
      <textarea id="{{ $idT }}-sebab" name="{{ $nama }}[sebab]" rows="2" aria-required="true"
        placeholder="Mengapa hal itu bisa terjadi">{{ $t['sebab'] }}</textarea>
    </x-baris-isi>
    <x-baris-isi label="Akibat" untuk="{{ $idT }}-akibat" :wajib="true">
      <textarea id="{{ $idT }}-akibat" name="{{ $nama }}[akibat]" rows="2" aria-required="true"
        placeholder="Kerugian atau risiko yang ditimbulkan">{{ $t['akibat'] }}</textarea>
    </x-baris-isi>
    <x-baris-isi label="Kategori internal" untuk="{{ $idT }}-intern" :wajib="true"
      info="Kelompok temuan untuk monitoring internal BPSDM, terpisah dari klasifikasi pada laporan pemeriksaan.">
      <select id="{{ $idT }}-intern" name="{{ $nama }}[intern]" class="fb-sedang" aria-required="true">
        @foreach($intern->where('aktif', true) as $k)
          <option value="{{ $k->id }}" @selected((string) $t['intern'] === (string) $k->id)>{{ $k->nama }}</option>
        @endforeach
        {{-- Kategori yang sudah dinonaktifkan tetap boleh dipertahankan pada temuan yang
             memakainya. --}}
        @php $inAktif = $intern->firstWhere('id', (int) $t['intern']); @endphp
        @if($inAktif && ! $inAktif->aktif)
          <option value="{{ $inAktif->id }}" selected>{{ $inAktif->nama }}</option>
        @endif
      </select>
    </x-baris-isi>

    <div class="fb-sub">
      <div class="fb-subkep">
        <b>Rekomendasi</b>
        <span class="fb-ket">{{ count($t['rekom']) }}</span>
      </div>
      <div class="fb-tab-baris">
        <div role="tablist" aria-label="Rekomendasi temuan {{ $i + 1 }}" class="fb-tabs">
          @foreach($t['rekom'] as $j => $x)
            <x-tab-isi :kecil="true" id="fb-tab-rek-{{ $x['id'] }}" panel="fb-rek-{{ $x['id'] }}" aksi="rek:{{ $x['id'] }}"
              :aktif="$x['id'] === $rAktif" :no="$form->huruf($j)" :teks="$form->intiUraian(trim($x['uraian']))"
              :utuh="trim($x['uraian'])" kosong="Uraian belum diisi" :ok="$form->rekOk($x)" />
          @endforeach
        </div>
        <button type="submit" name="aksi" value="tambah-rek:{{ $t['id'] }}" class="fb-tambah kecil">
          <x-ikon n="Plus" :s="14" /> Tambah rekomendasi
        </button>
      </div>
      @foreach($t['rekom'] as $j => $r)
        @include('laporan.bagian.fb-rek', ['r' => $r, 'j' => $j, 'tampilR' => $r['id'] === $rAktif])
      @endforeach
    </div>
  </div>
</section>
