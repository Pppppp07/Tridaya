{{-- Daftar rekomendasi di balik satu angka tabel keseluruhan — padanan
     `DaftarPintas`. Hizkia (24 Sep): data di tabel "bisa dijadikan seperti
     fungsi shortcut untuk berpindah atau masuk ke rincian laporan tersebut
     berdasarkan letak, satuan kerja, baris dan kolom yang dipilih". Angka yang
     di baliknya cuma satu rekomendasi langsung membuka rinciannya (server);
     lebih dari satu membuka daftar ini, yang skrip tempatkan di dekat selnya. --}}
@php
  use App\Support\Dasbor;

  $namaKolom = [
    'rek' => 'Rekomendasi', 'tugas' => 'Tindak lanjut', 'M' => $kataM, 'BM' => $kataBM, 'lhp' => 'LHP',
    'SS' => 'SS', 'BS' => 'BS', 'BT' => 'BT', 'TD' => 'TD', 'nilai' => 'Nilai', 'sisaBpk' => 'Sisa SIPTL', 'sisa' => 'Sisa Inspektorat',
  ][$pintas['kolom']] ?? $pintas['kolom'];
  $daftar = $pintas['daftar'];
  $nTugas = array_sum(array_map(fn ($e) => $e['baris']->count(), $daftar));
  $letak = implode(' · ', array_filter([$pintas['namaSatker'], $pintas['tahun'] !== '' ? 'tahun '.$pintas['tahun'] : 'semua tahun']));
@endphp
<div id="dsb-pintas" class="dsb-menu dsb-pintas-wadah" role="dialog" aria-label="{{ $namaKolom }}, {{ $letak }}"
  data-menu data-lebar="500" data-buka-otomatis data-jangkar="{{ 'pintas:'.$pintas['kunci'].'@dsb-pintas' }}" tabindex="-1">
  <div class="kepala-menu">
    <div class="judul-menu">
      <b>{{ $namaKolom }}</b>
      <span>{{ count($daftar) }} rekomendasi{{ $nTugas !== count($daftar) ? ' · '.$nTugas.' tindak lanjut' : '' }}</span>
    </div>
    <span class="dsb-pintas-letak">{{ $letak }}</span>
  </div>
  <ul class="isi-menu dsb-pintas">
    @foreach($daftar as ['x' => $x, 'baris' => $baris, 'rp' => $rp])
      @php
        $satkerNama = $baris->map(fn ($b) => $b->satker?->namaPendek() ?? 'Tidak diisi')->unique()->values();
        $bentuk = $baris->map(fn ($b) => Dasbor::bentuk($b))->filter()->unique()->values();
        $meta = implode(' · ', array_filter([
          $pintas['satker'] !== null ? $bentuk->take(2)->implode(', ')
            : $satkerNama->take(2)->implode(', ').($satkerNama->count() > 2 ? ' +'.($satkerNama->count() - 2) : ''),
          $rp > 0 ? $rpk($rp) : null,
        ]));
      @endphp
      <li>
        <button type="submit" name="lihat" value="{{ \App\Http\Controllers\RingkasanController::nilaiLihat($x['rek']->id, $baris) }}" title="{{ $x['rek']->uraian }}">
          <span class="dsb-pintas-kode">{{ $x['rek']->kode }}</span>
          <span class="dsb-pintas-ur">{{ Dasbor::pokokUraian($x['rek']->uraian) }}</span>
          @if($meta !== '')<span class="dsb-pintas-meta">{{ $meta }}</span>@endif
          <x-ikon n="ChevronRight" :s="15" class="dsb-pintas-panah" />
        </button>
      </li>
    @endforeach
  </ul>
  {{-- Tanpa skrip daftarnya tidak bisa ditutup dengan klik di luar. --}}
  <div data-tanpa-js style="padding:8px 12px;border-top:1px solid var(--line)">
    <button type="submit" name="ubah" value="tutup" class="btn btn-s">Tutup daftar</button>
  </div>
</div>
