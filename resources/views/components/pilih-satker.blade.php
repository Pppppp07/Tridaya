@props([
  'satker',            /* seluruh satuan kerja */
  'terpilih' => [],    /* id yang sudah dicentang */
  'nama',              /* nama medan, mis. t[satker][] */
  'dari' => null,      /* batasi pilihan ke id ini saja — null berarti semuanya */
  'kosong' => null,    /* kalimat kalau tidak ada yang bisa dipilih */
  'cari' => null,      /* daftar panjang: keping terpilih di atas, sisanya di balik pencarian — null = otomatis, lebih dari 8 pilihan */
  'kirim' => false,    /* kirim formulirnya begitu dicentang */
  'hanyaAktif' => false, /* tawarkan unit kerja aktif saja — pilihan lama tetap terbaca */
  'dariTemuan' => false, /* pilihan dinamis mengikuti satker temuan di browser */
])
@php
  /* Centang, bukan daftar pilih ber-Ctrl (pilihan lama mudah terhapus tanpa sadar). */
  $terpilih = array_map('intval', (array) $terpilih);
  $pilihan = $satker;
  if ($hanyaAktif) {
      $pilihan = $pilihan->filter(fn ($s) => $s->aktif || in_array($s->id, $terpilih, true));
  }
  if ($dari !== null && ! $dariTemuan) {
      $dariList = array_map('intval', (array) $dari);
      $pilihan = collect($dariList)->map(fn ($id) => $satker->firstWhere('id', $id))->filter()->values();
  }
  /* Penanggung jawab tiap keping dibawa kepingnya untuk daftar "yang diberi tahu". */
  $pj = fn ($s) => 'data-pj="'.e($s->relationLoaded('penanggungJawab') ? ($s->penanggungJawab?->name ?? '') : '').'" data-pendek="'.e($s->namaPendek()).'"';
  $sisa = $pilihan->whereNotIn('id', $terpilih);
  /* Yang sudah dipilih di atas, menurut urutan dipilih. */
  $dipilih = collect($terpilih)->map(fn ($id) => $pilihan->firstWhere('id', $id))->filter()->values();
  /* Batas daftar yang masih ditampilkan utuh. */
  $cari ??= (! $dariTemuan && $pilihan->count() > 8);
  $dariList = $dariTemuan ? array_map('intval', (array) $dari) : [];
@endphp

@if($dariTemuan)
  <div data-satker-tl-wadah>
    <div data-satker-kosong style="font-size:13px;color:var(--ink-2);padding:9px 11px;
      border:1px dashed var(--line);border-radius:8px" @if(!empty($dariList)) hidden @endif>
      {{ $kosong ?: 'Pilih dulu satuan kerja terperiksa pada temuannya.' }}
    </div>
    <div data-satker-pilihan style="display:flex;flex-wrap:wrap;gap:7px" @if(empty($dariList)) hidden @endif>
      @foreach($pilihan as $s)
        <label class="kepingpilih" title="{{ $s->nama }}" data-satker-id="{{ $s->id }}"
          @if(!in_array($s->id, $dariList, true)) hidden @endif>
          <input type="checkbox" name="{{ $nama }}" value="{{ $s->id }}"
            @checked(in_array($s->id, $terpilih, true)) @if($kirim) data-kirim @endif data-pilih-satker {!! $pj($s) !!}>
          <span class="kotak" aria-hidden="true"><x-ikon n="Check" :s="9" :w="3.5" /></span>
          {{ $s->namaPendek() }}
        </label>
      @endforeach
    </div>
  </div>
@elseif($pilihan->isEmpty())
  <div style="font-size:13px;color:var(--ink-2);padding:9px 11px;
    border:1px dashed var(--line);border-radius:8px">
    {{ $kosong ?: 'Belum ada pilihan.' }}
  </div>
@elseif(! $cari)
  {{-- Daftar pendek tampil utuh, tanpa pencarian. --}}
  <div style="display:flex;flex-wrap:wrap;gap:7px">
    @foreach($pilihan as $s)
      <label class="kepingpilih" title="{{ $s->nama }}" data-satker-id="{{ $s->id }}">
        <input type="checkbox" name="{{ $nama }}" value="{{ $s->id }}"
          @checked(in_array($s->id, $terpilih, true)) @if($kirim) data-kirim @endif data-pilih-satker {!! $pj($s) !!}>
        <span class="kotak" aria-hidden="true"><x-ikon n="Check" :s="9" :w="3.5" /></span>
        {{ $s->namaPendek() }}
      </label>
    @endforeach
  </div>
@else
  <div data-pilih-cari>
    <div data-pilih-terpilih style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:9px" @if($dipilih->isEmpty()) hidden @endif>
      @foreach($dipilih as $s)
        <label class="kepingpilih" title="{{ $s->nama }}" data-nama="{{ mb_strtolower($s->nama.' '.$s->namaPendek()) }}" data-satker-id="{{ $s->id }}">
          <input type="checkbox" name="{{ $nama }}" value="{{ $s->id }}" checked
            @if($kirim) data-kirim @endif data-pilih-satker {!! $pj($s) !!}>
          <span class="kotak" aria-hidden="true"><x-ikon n="Check" :s="9" :w="3.5" /></span>
          {{ $s->namaPendek() }}
        </label>
      @endforeach
    </div>

    <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
      <input type="text" data-cari-satker style="flex:1 1 220px;min-width:0"
        placeholder="Cari satuan kerja — {{ $sisa->count() }} belum dipilih">
      <button type="button" class="btn btn-s" data-buka-daftar-satker
        data-tutup="Tutup daftar" data-buka="Lihat semua {{ $pilihan->count() }}">
        Lihat semua {{ $pilihan->count() }}
      </button>
    </div>

    <div class="daftarpilih" hidden>
      @foreach($sisa as $s)
        <label class="kepingpilih" title="{{ $s->nama }}" data-nama="{{ mb_strtolower($s->nama.' '.$s->namaPendek()) }}" data-satker-id="{{ $s->id }}">
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
