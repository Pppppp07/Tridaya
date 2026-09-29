@props(['id', 'panel', 'aksi', 'aktif' => false, 'no', 'teks' => '', 'kosong' => '', 'ok' => false, 'kecil' => false, 'utuh' => null])

{{-- Satu tab pada deret temuan, rekomendasi, atau tindak lanjut — padanan
     `TabIsi`: nomor, judul singkat, dan tanda lengkap/belum. Keadaannya tetap
     terlihat walau isinya tidak sedang ditampilkan. Tombol kirim: tanpa skrip,
     memilih tab mengirim isian dan menampilkan panelnya; dengan skrip panelnya
     ditukar di tempat (panah kiri-kanan, Home, End juga berpindah tab). --}}
<button type="submit" name="aksi" value="{{ $aksi }}" role="tab" id="{{ $id }}"
  aria-selected="{{ $aktif ? 'true' : 'false' }}" aria-controls="{{ $panel }}" tabindex="{{ $aktif ? '0' : '-1' }}"
  class="fb-tab{{ $aktif ? ' aktif' : '' }}{{ $kecil ? ' kecil' : '' }}" title="{{ $utuh ?: ($teks ?: $kosong) }}" data-tab>
  <span class="fb-no">{{ $no }}</span>
  <span class="fb-tab-teks{{ $teks ? '' : ' fb-kosong' }}" data-tab-teks data-kosong="{{ $kosong }}">{{ $teks ?: $kosong }}</span>
  @if($ok)<x-ikon n="CheckCircle2" :s="15" class="fb-tab-ok" />@else<x-ikon n="Circle" :s="15" class="fb-tab-kurang" />@endif
  <span class="fb-sr">{{ $ok ? ', lengkap' : ', belum lengkap' }}</span>
</button>
