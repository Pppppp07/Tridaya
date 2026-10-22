@props(['id', 'panel', 'aksi', 'aktif' => false, 'no', 'teks' => '', 'kosong' => '', 'ok' => false, 'kecil' => false, 'utuh' => null])

{{-- Satu tab temuan/rekomendasi/tindak lanjut: nomor, judul singkat, tanda lengkap. Tanpa
     skrip, memilih tab mengirim isian lalu menampilkan tab itu. --}}
<button type="submit" name="aksi" value="{{ $aksi }}" role="tab" id="{{ $id }}"
  aria-selected="{{ $aktif ? 'true' : 'false' }}" aria-controls="{{ $panel }}" tabindex="{{ $aktif ? '0' : '-1' }}"
  class="fb-tab{{ $aktif ? ' aktif' : '' }}{{ $kecil ? ' kecil' : '' }}" title="{{ $utuh ?: ($teks ?: $kosong) }}" data-tab>
  <span class="fb-no">{{ $no }}</span>
  <span class="fb-tab-teks{{ $teks ? '' : ' fb-kosong' }}" data-tab-teks data-kosong="{{ $kosong }}">{{ $teks ?: $kosong }}</span>
  @if($ok)<x-ikon n="CheckCircle2" :s="15" class="fb-tab-ok" />@else<x-ikon n="Circle" :s="15" class="fb-tab-kurang" />@endif
  <span class="fb-sr">{{ $ok ? ', lengkap' : ', belum lengkap' }}</span>
</button>
