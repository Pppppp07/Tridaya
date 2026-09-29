@php
  use App\Support\Pemberitahuan;

  /* Satu baris pemberitahuan — padanan `baris` di Pemberitahuan prototipe. Seluruh
     barisnya satu link: yang dibaca orang kalimatnya, jadi kalimatnya yang
     harus bisa ditekan. */
  $r = $k->rekomendasi;
  $tujuan = Pemberitahuan::BAGIAN[$k->blok] ?? null;
  $bentuk = $k->tindakan?->bentuk?->nama;
@endphp
<a class="row{{ $redup ? ' sepi' : '' }}" href="{{ route('pemberitahuan.buka', $k) }}">
  <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:6px">
    <x-sumber :j="$r?->temuan->laporan->sumber ?? 'LHP'" />
    <span class="mono" style="font-size:11px;color:var(--ink-3)">{{ $r?->kode ?? $k->rekomendasi_id }}</span>
    <div style="flex:1"></div>
    <span class="lbl">{{ $k->waktu->format('Y-m-d H.i') }}</span>
  </div>
  <div style="font-size:13px;margin-bottom:5px">
    <b style="font-weight:600">{{ $k->label_pelaku }}</b> — {{ $k->aksi }}
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <span class="lbl">{{ $r?->temuan->judul ?? '—' }} · {{ Pemberitahuan::sebutSatker($k, auth()->user()) }}{{ $bentuk ? ' · '.$bentuk : '' }}</span>
    <div style="flex:1"></div>
    {{-- Pemberitahuan yang tidak menyebut bagiannya tetap membuka berkasnya, cuma
         tanpa menunjuk ke mana-mana. --}}
    @if($tujuan)
      <span class="tujuan">Buka {{ $tujuan }} <x-ikon n="ChevronRight" :s="12" /></span>
    @endif
  </div>
</a>
