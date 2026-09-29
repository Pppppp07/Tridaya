@props([
  'satker',            /* seluruh satuan kerja */
  'terpilih' => [],    /* id yang sudah dicentang */
  'nama',              /* nama medan, mis. t[satker][] */
  'dari' => null,      /* batasi pilihan ke id ini saja — null berarti semuanya */
  'kosong' => null,    /* kalimat kalau tidak ada yang bisa dipilih */
  'cari' => null,      /* daftar panjang: keping terpilih di atas, sisanya di balik pencarian — null = otomatis, lebih dari 8 pilihan */
  'kirim' => false,    /* kirim formulirnya begitu dicentang */
  'hanyaAktif' => false, /* tawarkan unit kerja aktif saja — pilihan lama tetap terbaca */
])
@php
  /* Pemilih satuan kerja — padanan `PilihSatker` prototipe. Centang, bukan
     daftar pilih bertekan Ctrl: hampir tidak ada yang menemukan Ctrl sendiri,
     dan yang terjadi justru pilihan sebelumnya terhapus tanpa disadari. */
  $terpilih = array_map('intval', (array) $terpilih);
  /* Pilihan yang dibatasi mengikuti urutan daftar asalnya — satuan kerja
     terperiksa pada temuannya, menurut urutan dipilihnya — seperti di
     prototipe, bukan urutan data master. */
  $pilihan = $dari === null ? $satker
    : collect(array_map('intval', (array) $dari))->map(fn ($id) => $satker->firstWhere('id', $id))->filter()->values();
  /* Unit kerja yang dinonaktifkan di Data master tidak ditawarkan lagi; yang
     sudah terlanjur dipilih pada draf tetap tampil, tidak dibuang diam-diam. */
  if ($hanyaAktif) {
      $pilihan = $pilihan->filter(fn ($s) => $s->aktif || in_array($s->id, $terpilih, true));
  }
  /* Penanggung jawab tiap keping dibawa kepingnya — dibaca daftar "yang
     diberi tahu" di bawah pemilih tindak lanjut. */
  $pj = fn ($s) => 'data-pj="'.e($s->relationLoaded('penanggungJawab') ? ($s->penanggungJawab?->name ?? '') : '').'" data-pendek="'.e($s->namaPendek()).'"';
  $sisa = $pilihan->whereNotIn('id', $terpilih);
  /* Yang sudah dipilih berdiri di atas menurut urutan dipilihnya, seperti di
     prototipe — bukan urutan daftar. */
  $dipilih = collect($terpilih)->map(fn ($id) => $pilihan->firstWhere('id', $id))->filter()->values();
  /* Batas daftar yang masih enak dilihat utuh — AMBANG_CARI_SATKER prototipe.
     Di bawahnya kepingnya muat dua baris; di atasnya satu pertanyaan mulai
     terlihat seperti seluruh formulir. */
  $cari ??= $pilihan->count() > 8;
@endphp

@if($pilihan->isEmpty())
  <div style="font-size:13px;color:var(--ink-2);padding:9px 11px;
    border:1px dashed var(--line);border-radius:8px">
    {{ $kosong ?: 'Belum ada pilihan.' }}
  </div>
@elseif(! $cari)
  {{-- Daftar pendek tampil utuh: menyembunyikan lima keping di balik pencarian
       tidak menghemat apa pun, dan justru menambah satu tekanan untuk pekerjaan
       yang tadinya sekali klik. --}}
  <div style="display:flex;flex-wrap:wrap;gap:7px">
    @foreach($pilihan as $s)
      <label class="kepingpilih" title="{{ $s->nama }}">
        <input type="checkbox" name="{{ $nama }}" value="{{ $s->id }}"
          @checked(in_array($s->id, $terpilih, true)) @if($kirim) data-kirim @endif data-pilih-satker {!! $pj($s) !!}>
        <span class="kotak" aria-hidden="true"><x-ikon n="Check" :s="9" :w="3.5" /></span>
        {{ $s->namaPendek() }}
      </label>
    @endforeach
  </div>
@else
  <div data-pilih-cari>
    @if($dipilih->isNotEmpty())
      <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:9px">
        @foreach($dipilih as $s)
          <label class="kepingpilih" title="{{ $s->nama }}">
            <input type="checkbox" name="{{ $nama }}" value="{{ $s->id }}" checked
              @if($kirim) data-kirim @endif data-pilih-satker {!! $pj($s) !!}>
            <span class="kotak" aria-hidden="true"><x-ikon n="Check" :s="9" :w="3.5" /></span>
            {{ $s->namaPendek() }}
          </label>
        @endforeach
      </div>
    @endif

    <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
      <input type="text" data-cari-satker style="flex:1 1 220px;min-width:0"
        placeholder="Cari satuan kerja — {{ $sisa->count() }} belum dipilih">
      {{-- Untuk yang belum hafal namanya. Tanpa ini, isian pencarian yang
           kosong berarti layar kosong — dan yang tidak tahu harus mengetik apa
           tidak punya jalan masuk. --}}
      <button type="button" class="btn btn-s" data-buka-daftar-satker
        data-tutup="Tutup daftar" data-buka="Lihat semua {{ $pilihan->count() }}">
        Lihat semua {{ $pilihan->count() }}
      </button>
    </div>

    <div class="daftarpilih" hidden>
      @foreach($sisa as $s)
        <label class="kepingpilih" title="{{ $s->nama }}" data-nama="{{ mb_strtolower($s->nama.' '.$s->namaPendek()) }}">
          <input type="checkbox" name="{{ $nama }}" value="{{ $s->id }}"
            @if($kirim) data-kirim @endif data-pilih-satker {!! $pj($s) !!}>
          <span class="kotak" aria-hidden="true"><x-ikon n="Check" :s="9" :w="3.5" /></span>
          {{ $s->namaPendek() }}
        </label>
      @endforeach
      <span class="hint" style="margin:0" data-tak-cocok hidden>Tidak ada yang cocok.</span>
    </div>
  </div>
@endif
