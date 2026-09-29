{{-- Panel satu tindak lanjut — padanan `bagianTindak`: panel bertepi tipis di
     bawah deret tabnya. Nomornya sudah di tab, jadi bentuknya jadi baris isian
     pertama. Satuan kerjanya dipilih di dalam tindakannya sendiri, dan hanya
     dari yang terperiksa pada temuannya.
     Masukan: $t, $r, $tk, $k, $nTl, $uang, $tampilK. --}}
@php
  $dipilih = array_map('intval', collect($tk['satker'])->pluck('satker')->all());
  $nilaiTk = collect($tk['satker'])->pluck('nilai', 'satker');
  $idTk = 'fb-'.$r['id'].'-'.$k;
  $namaTk = 'tem['.$t['id'].'][rekom]['.$r['id'].'][tindakan]['.$tk['id'].']';
  /* Siapa yang diberi tahu begitu laporannya disimpan — padanan PenerimaPemberitahuan. */
  $dipilihSk = collect($dipilih)->map(fn ($id) => $satker->firstWhere('id', (int) $id))->filter()->values();
  $adaPj = $dipilihSk->filter(fn ($s) => $s->penanggungJawab)->values();
  $tanpaPj = $dipilihSk->reject(fn ($s) => $s->penanggungJawab)->values();
  /* Baris nilai: yang dicentang dulu, menurut urutan tindak lanjutnya (di
     prototipe barisnya mengikuti daftar sasaran), lalu sisanya — tersembunyi
     sampai dicentang. */
  $pilihanNilai = collect(array_merge($dipilih, array_diff(array_map('intval', $t['satker']), $dipilih)))
    ->map(fn ($id) => $satker->firstWhere('id', $id))->filter()->values();
@endphp
<div id="fb-tl-{{ $r['id'] }}-{{ $k }}" role="tabpanel" aria-labelledby="fb-tab-tl-{{ $r['id'] }}-{{ $k }}" class="fb-tl"
  @unless($tampilK) hidden @endunless data-tindak data-panel-tl="{{ $r['id'] }}:{{ $k }}">
  <x-baris-isi label="Bentuk" untuk="{{ $idTk }}-bentuk" :wajib="true" info="Pilih dari usulan atau ketik sendiri persis seperti bunyi suratnya.">
    <div class="fb-sebaris fb-bentuk">
      {{-- Boleh diketik sendiri. Daftarnya cuma usulan — yang tercatat harus
           sama dengan yang tertulis di suratnya. --}}
      <input id="{{ $idTk }}-bentuk" type="text" list="bentuk-tl" name="{{ $namaTk }}[bentuk]" aria-required="true"
        value="{{ $tk['bentuk'] }}" placeholder="Mis. Surat teguran" data-ikut-judul="tl:{{ $r['id'] }}:{{ $k }}">
      @if($nTl > 1)
        <button type="submit" name="aksi" value="hapus-tindakan:{{ $t['id'] }}:{{ $r['id'] }}:{{ $k }}" class="fb-hapus"
          aria-label="Hapus tindak lanjut {{ $k + 1 }}">
          Hapus<span class="fb-hapus-apa"> tindak lanjut</span>
        </button>
      @endif
    </div>
  </x-baris-isi>

  <x-baris-isi label="Satuan kerja" kunci="{{ $idTk }}-satker" :gabung="true" :wajib="true" :kosong="! $dipilih"
    info="Dipilih dari satuan kerja terperiksa pada temuannya.">
    <x-pilih-satker :satker="$satker" :terpilih="$dipilih" :dari="$t['satker']" nama="{{ $namaTk }}[satker][]"
      :hanya-aktif="true" kosong="Pilih dulu satuan kerja terperiksa pada temuannya." />
    {{-- Bang Kamal: "Pas saat kepilih di-submit, langsung notif kan ke admin
         Makassar, SDA, Bandung, Jayapura." Unit kerja tanpa penanggung jawab
         disebut terang-terangan: pemberitahuannya tidak sampai ke siapa pun. --}}
    <div class="penerima" data-penerima @if($dipilihSk->isEmpty()) hidden @endif>
      <x-ikon n="Bell" :s="12" />
      <span data-penerima-isi>@if($adaPj->isNotEmpty())Pemberitahuan dikirim lewat aplikasi dan email ke: @foreach($adaPj as $ii => $s){{ $ii ? ', ' : '' }}<b>{{ $s->penanggungJawab->name }}</b> ({{ $s->namaPendek() }})@endforeach.@endif @if($tanpaPj->isNotEmpty())<span class="tanpa">{{ $tanpaPj->map(fn ($s) => $s->namaPendek())->join(', ') }} belum punya penanggung jawab — pilih di Data master agar pemberitahuannya terkirim.</span>@endif</span>
    </div>
  </x-baris-isi>

  {{-- Sifatnya yang menentukan, bukan angkanya: yang tahu duluan ke mana
       nilainya dibagi justru suratnya. Baris tiap satuan kerja tampil begitu
       dicentang (skrip); tanpa skrip, sesudah halaman disegarkan. --}}
  <div data-nilai-tindak @if(! $uang || ! $dipilih) hidden @endif>
    <x-baris-isi label="Nilai tiap satuan kerja" kunci="{{ $idTk }}-nilai" :gabung="true"
      info="Kosongkan bagi satuan kerja yang tidak dibebani setoran.">
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

  {{-- Bukti yang diminta melekat pada tindak lanjutnya, bukan pada
       rekomendasinya, dan ditetapkan di muka: SOP menempatkan penetapan bukti
       pada tahap Rencana Aksi. --}}
  <x-baris-isi label="Dokumen yang diminta" kunci="{{ $idTk }}-dok" :gabung="true"
    info="Bukti yang harus dikirim satuan kerja. Usulannya mengikuti bentuk tindak lanjut.">
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

  {{-- Tiap tindak lanjut punya rencana aksinya sendiri: menyusun SOP tentu
       lebih lama daripada menerbitkan surat teguran. --}}
  <x-baris-isi label="Rencana aksi" untuk="{{ $idTk }}-tgl" :wajib="true"
    :info="['Surat BPK sendiri tidak bertenggat.', 'Tanggal ini rencana yang disepakati, bukan batas yang mengunci.']">
    <div class="fb-sebaris">
      <input id="{{ $idTk }}-tgl" type="date" name="{{ $namaTk }}[tgl_renaksi]" aria-required="true" value="{{ $tk['tgl_renaksi'] }}">
      {{-- Label dan tanggalnya satu pasang, supaya di layar sempit turun bersama. --}}
      <span class="fb-pasang">
        <label class="fb-sublbl" for="{{ $idTk }}-target">Target selesai</label>
        <input id="{{ $idTk }}-target" type="date" name="{{ $namaTk }}[target]" value="{{ $tk['target'] }}">
      </span>
    </div>
  </x-baris-isi>

  <x-baris-isi label="Catatan" untuk="{{ $idTk }}-catatan" info="Terbaca oleh satuan kerja yang dituju.">
    <textarea id="{{ $idTk }}-catatan" name="{{ $namaTk }}[catatan]" rows="1" placeholder="Mis. bukti apa yang diharapkan">{{ $tk['catatan'] }}</textarea>
  </x-baris-isi>
</div>
