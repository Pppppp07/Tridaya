{{-- Satu sel matriks status — padanan `SelBanding`: batang 100% untuk satu
     versi pada satu ukuran (jumlah atau nilai). Persennya di dalam potongan
     kalau muat; nilai tiap keadaan selalu tertulis di deret bawahnya.
     Rupiahnya tanpa "Rp" — kepala kolomnya sudah menyebut satuannya.
     Masukan: $label, $ket, $isi, $uang, $pilih, $aksi ("hasil"/"bpk"). --}}
@php
  $nilaiDari = fn ($x) => $uang ? $x['rp'] : $x['n'];
  $jumlah = array_sum(array_map($nilaiDari, $isi));
  $teks = fn ($x) => $uang ? preg_replace('/^Rp\s*/', '', $rpk($x['rp'])) : (string) $x['n'];
@endphp
<div class="dsb-bb">
  {{-- Di panel sempit kepala kolom matriksnya hilang; sebutannya pindah ke sini. --}}
  <span class="mk-ket">{{ $ket }}</span>
  {{-- Tanpa rupiah sama sekali (LHA umumnya begitu): batang abu kosong dan
       "0 diakui · 0 sisa" terbaca seperti data yang hilang. --}}
  @if($jumlah === 0)
    <div class="dsb-catatan-bb">Tanpa nilai rupiah.</div>
  @else
    <div class="batang-bb" role="group" aria-label="{{ $label }}">
      @foreach($isi as $x)
        @continue($nilaiDari($x) <= 0)
        @php
          $bagian = $jumlah ? $nilaiDari($x) / $jumlah : 0;
          $on = in_array($x['k'], $pilih, true);
          $pct = (int) round($bagian * 100);
        @endphp
        <button type="submit" name="ubah" value="{{ $aksi.':'.$x['k'].'@dsb-p-status' }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
          class="seg-bb{{ $pilih && ! $on ? ' redup' : '' }}"
          style="flex-grow:{{ $nilaiDari($x) }};background:{{ $x['warna'] }};color:var(--d-teks-status, {{ $x['tulisan'] }})"
          aria-label="{{ $x['nama'] }}: {{ $uang ? $rpk($x['rp']) : $x['n'].' tindak lanjut' }} ({{ $pct }}%)"
          data-petunjuk="{{ $petunjuk(['judul' => $x['nama'],
            'baris' => [['warna' => $x['warna'], 'nilai' => $uang ? $rpk($x['rp']) : $x['n'], 'nama' => $uang ? 'nilai' : 'tindak lanjut']],
            'ket' => $pct.'% · '.($uang ? $x['n'].' tindak lanjut' : $rpk($x['rp']))]) }}">
          @if($bagian >= 0.15)<span>{{ $pct }}%</span>@endif
        </button>
      @endforeach
    </div>
    <div class="angka-bb">
      @foreach($isi as $x)
        <span @if($pilih && ! in_array($x['k'], $pilih, true)) class="redup" @endif><i style="background:{{ $x['warna'] }}"></i><b>{{ $teks($x) }}</b>{{ ($uang && ! empty($x['sebutRp'])) ? $x['sebutRp'] : ($x['pendek'] ?? $x['nama']) }}</span>
      @endforeach
    </div>
  @endif
</div>
