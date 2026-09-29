@props(['id', 'judul', 'kelas' => null, 'info' => null, 'kunciTabel' => null, 'tabel' => false,
  'kosong' => null, 'wawasan' => null, 'alat' => null, 'isiTabel' => null])

{{-- Satu panel rincian — padanan `Panel` di DasborUji.jsx. Tombol tabel
     menukar grafik dengan tabelnya (keadaannya dibawa alamat: `tabel[]`).
     Kosong = kalimat pengganti isi, dan tombol tabelnya ikut hilang. --}}
<section class="dsb-panel{{ $kelas ? ' '.$kelas : '' }}" aria-labelledby="{{ $id }}">
  <div class="dsb-pkepala">
    <h3 id="{{ $id }}">{{ $judul }}</h3>
    @if($info)<x-info :teks="$info" />@endif
    <span class="sela"></span>
    {{ $alat }}
    @if($kunciTabel && ! $kosong)
      <button type="submit" name="ubah" value="{{ 'tabel:'.$kunciTabel.'@'.$id }}" class="ikonbtn"
        aria-pressed="{{ $tabel ? 'true' : 'false' }}"
        title="{{ $tabel ? 'Tampilkan grafik' : 'Tampilkan sebagai tabel' }}"
        aria-label="{{ $tabel ? 'Tampilkan grafik' : 'Tampilkan sebagai tabel' }}">
        @if($tabel)<x-ikon n="BarChart3" :s="15" />@else<x-ikon n="Table2" :s="15" />@endif
      </button>
    @endif
  </div>
  @if($wawasan && ! $kosong)<p class="dsb-wawasan">{{ $wawasan }}</p>@endif
  @if($kosong)
    <div class="dsb-kosong">{{ $kosong }}</div>
  @elseif($tabel && $isiTabel)
    {{ $isiTabel }}
  @else
    {{ $slot }}
  @endif
</section>
