{{-- Panel satu tindak lanjut. Satuan kerjanya hanya dari yang terperiksa pada temuannya. --}}
@php
  $dipilih = array_map('intval', collect($tk['satker'])->pluck('satker')->all());
  $nilaiTk = collect($tk['satker'])->pluck('nilai', 'satker');
  $idTk = 'fb-'.$r['id'].'-'.$k;
  $namaTk = 'tem['.$t['id'].'][rekom]['.$r['id'].'][tindakan]['.$tk['id'].']';
  /* Siapa yang diberi tahu begitu laporannya disimpan. */
  $dipilihSk = collect($dipilih)->map(fn ($id) => $satker->firstWhere('id', (int) $id))->filter()->values();
  $adaPj = $dipilihSk->filter(fn ($s) => $s->penanggungJawab)->values();
  $tanpaPj = $dipilihSk->reject(fn ($s) => $s->penanggungJawab)->values();
  /* Baris nilai: yang dicentang dulu (urut tindak lanjut), sisanya tersembunyi sampai dicentang. */
  $pilihanNilai = $satker->filter(fn ($s) => $s->aktif || in_array($s->id, $dipilih, true));
@endphp
<div id="fb-tl-{{ $r['id'] }}-{{ $k }}" role="tabpanel" aria-labelledby="fb-tab-tl-{{ $r['id'] }}-{{ $k }}" class="fb-tl"
  @unless($tampilK) hidden @endunless data-tindak data-panel-tl="{{ $r['id'] }}:{{ $k }}">
  <x-baris-isi label="Bentuk" untuk="{{ $idTk }}-bentuk" :wajib="true"
    info="Jenis tindakan yang harus dilakukan Satker untuk memenuhi rekomendasi. Pilihan ini mengisi usulan dokumen bukti.">
    <div class="fb-sebaris fb-bentuk">
      {{-- Daftar dari Data Master; mengganti bentuk mengirim formulir supaya usulan dokumennya ikut. --}}
      <select id="{{ $idTk }}-bentuk" name="{{ $namaTk }}[bentuk]" aria-required="true"
        data-kirim data-ikut-judul="tl:{{ $r['id'] }}:{{ $k }}">
        @if($tk['bentuk'] === '')<option value="" selected>Pilih bentuk</option>@endif
        @foreach($bentuk->filter(fn ($x) => $x->aktif || (string) $tk['bentuk'] === (string) $x->id) as $x)
          <option value="{{ $x->id }}" @selected((string) $tk['bentuk'] === (string) $x->id)>{{ $x->nama }}{{ $x->aktif ? '' : ' (nonaktif)' }}</option>
        @endforeach
      </select>
      @if($nTl > 1)
        <button type="submit" name="aksi" value="hapus-tindakan:{{ $t['id'] }}:{{ $r['id'] }}:{{ $k }}" class="fb-hapus"
          aria-label="Hapus tindak lanjut {{ $k + 1 }}">
          Hapus<span class="fb-hapus-apa"> tindak lanjut</span>
        </button>
      @endif
    </div>
  </x-baris-isi>

  <x-baris-isi label="Satuan kerja" kunci="{{ $idTk }}-satker" :gabung="true" :wajib="true" :kosong="! $dipilih"
    info="Pilih pelaksana tindak lanjut dari daftar satuan kerja terperiksa pada temuan ini.">
    <x-pilih-satker :satker="$satker" :terpilih="$dipilih" :dari="$t['satker']" nama="{{ $namaTk }}[satker][]"
      :hanya-aktif="true" kosong="Pilih dulu satuan kerja terperiksa pada temuannya." :dari-temuan="true" />
    {{-- Unit kerja tanpa penanggung jawab disebut: pemberitahuannya tidak sampai ke siapa pun. --}}
    <div class="penerima" data-penerima @if($dipilihSk->isEmpty()) hidden @endif>
      <x-ikon n="Bell" :s="12" />
      <span data-penerima-isi>@if($adaPj->isNotEmpty())Pemberitahuan dikirim lewat aplikasi dan email ke: @foreach($adaPj as $ii => $s){{ $ii ? ', ' : '' }}<b>{{ $s->penanggungJawab->name }}</b> ({{ $s->namaPendek() }})@endforeach.@endif @if($tanpaPj->isNotEmpty())<span class="tanpa">{{ $tanpaPj->map(fn ($s) => $s->namaPendek())->join(', ') }} belum punya penanggung jawab — minta Admin DTI memilihnya di Data master agar pemberitahuannya terkirim.</span>@endif</span>
    </div>
  </x-baris-isi>

  {{-- Baris tiap satuan kerja tampil begitu dicentang (skrip); tanpa skrip sesudah halaman
       disegarkan. --}}
  <div data-nilai-tindak @if(! $uang || ! $dipilih) hidden @endif>
    <x-baris-isi label="Nilai tiap satuan kerja" kunci="{{ $idTk }}-nilai" :gabung="true"
      info="Isi nilai yang harus dipulihkan oleh tiap satuan kerja. Kosongkan jika satuan kerja tersebut tidak memiliki kewajiban pemulihan nilai.">
      <div class="fb-nilai">
        @foreach($pilihanNilai as $sk)
          <label class="fb-nilai-brs" data-nilai-satker="{{ $sk->id }}" @if(! in_array($sk->id, $dipilih, true)) hidden @endif>
            <span>{{ $sk->namaPendek() }}</span>
            <input type="text" class="mono" inputmode="numeric" name="{{ $namaTk }}[nilai][{{ $sk->id }}]"
              value="{{ $nilaiTk[$sk->id] ?? '' }}" placeholder="tidak dibebani">
          </label>
        @endforeach
      </div>
    </x-baris-isi>
  </div>

  {{-- Bukti melekat pada tindak lanjut dan ditetapkan di muka (tahap Rencana Aksi). --}}
  <x-baris-isi label="Dokumen yang diminta" kunci="{{ $idTk }}-dok" :gabung="true"
    info="Daftar bukti yang perlu dilengkapi satuan kerja. Periksa dan sesuaikan usulan dokumen dengan bentuk tindak lanjut.">
    <div class="fb-dok">
      @foreach($tk['dokumen'] as $j2 => $dok)
        <div class="fb-dok-brs">
          <input type="text" name="{{ $namaTk }}[dokumen][]" value="{{ $dok }}" aria-label="Dokumen {{ $j2 + 1 }}" placeholder="Nama dokumen">
          <button type="submit" name="aksi" value="hapus-dok:{{ $t['id'] }}:{{ $r['id'] }}:{{ $k }}:{{ $j2 }}" class="fb-x"
            aria-label="Hapus dokumen {{ $j2 + 1 }}"><x-ikon n="X" :s="14" /></button>
        </div>
      @endforeach
      <button type="submit" name="aksi" value="tambah-dok:{{ $t['id'] }}:{{ $r['id'] }}:{{ $k }}" class="fb-link">
        <x-ikon n="Plus" :s="13" /> Tambah dokumen
      </button>
    </div>
  </x-baris-isi>

  <x-baris-isi label="Rencana aksi" untuk="{{ $idTk }}-tgl" :wajib="true"
    :info="['Catat jadwal rencana aksi dan target selesai untuk bentuk tindak lanjut ini.', 'Jadwal ini terpisah dari tenggat jawab yang dihitung dari tanggal laporan diterima.']">
    <div class="fb-sebaris">
      <input id="{{ $idTk }}-tgl" type="date" name="{{ $namaTk }}[tgl_renaksi]" aria-required="true" value="{{ $tk['tgl_renaksi'] }}">
      {{-- Label dan tanggalnya satu pasang, supaya di layar sempit turun bersama. --}}
      <span class="fb-pasang">
        <label class="fb-sublbl" for="{{ $idTk }}-target">Target selesai</label>
        <input id="{{ $idTk }}-target" type="date" name="{{ $namaTk }}[target]" value="{{ $tk['target'] }}">
      </span>
    </div>
  </x-baris-isi>

  <x-baris-isi label="Catatan" untuk="{{ $idTk }}-catatan" info="Petunjuk tambahan untuk satuan kerja yang ditugaskan pada tindak lanjut ini.">
    <textarea id="{{ $idTk }}-catatan" name="{{ $namaTk }}[catatan]" rows="1" placeholder="Mis. bukti apa yang diharapkan">{{ $tk['catatan'] }}</textarea>
  </x-baris-isi>
</div>
