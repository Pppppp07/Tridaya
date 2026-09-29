{{-- Sel-sel angka satu baris tabel keseluruhan. Nol ditulis "–" dan
     dipudarkan (pembaca layar tetap mendengar "0"); belum memadai yang tidak
     nol merah, sisa Inspektorat yang tidak nol tebal. Angka yang ada isinya
     sekaligus pintasan: klik membuka rekomendasi di baliknya menurut baris
     (tahun, satuan kerja) dan kolomnya. Persen tidak bisa diklik — ia
     perbandingan. --}}
@foreach($KOLOM as $x)
  @if($x['k'] === 'pct')
    <td class="{{ $kelasSel($x) }}">
      <span class="isi-pct">
        <span class="dsb-tk-pita" aria-hidden="true"><i style="width:{{ $pct($b) }}%"></i></span>
        <span class="n">{{ $b['M'] + $b['BM'] ? $pct($b).'%' : '—' }}</span>
      </span>
    </td>
  @else
    @php
      $v = $b[$x['k']] ?? 0;
      $kelas = trim($kelasSel($x).(! $v ? ' nol' : '').($v && $x['k'] === 'BM' ? ' belum' : '').($v && $x['k'] === 'sisa' ? ' tebal' : ''));
      $teks = ! empty($x['rp']) ? $rpk($v) : $v;
    @endphp
    <td class="{{ $kelas }}">
      @if($v)
        <button type="submit" name="ubah" value="{{ 'pintas:'.$letak['tahun'].'|'.$letak['satker'].'|'.$x['k'].'@dsb-pintas' }}"
          class="dsb-tk-pintas" title="Lihat rekomendasinya"
          aria-label="{{ $teks }} {{ mb_strtolower($x['nama']) }}, {{ $letak['label'] }} — lihat rekomendasinya">{{ $teks }}</button>
      @else
        <span aria-hidden="true">–</span><span class="dsb-sr">{{ ! empty($x['rp']) ? 'Rp 0' : '0' }}</span>
      @endif
    </td>
  @endif
@endforeach
