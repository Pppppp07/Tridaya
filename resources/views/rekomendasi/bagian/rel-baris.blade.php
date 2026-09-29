@php
  use App\Enums\PosisiBerkas;

  /* Rel tahap SATU baris penugasan — padanan RelBaris. Yang menempuh tahapan
     tindak lanjut tiap satuan kerja, bukan rekomendasinya.

     Sejak 27 Sep kartu x-panel-kerja, sama dengan Bukti & tanggapan, Kerjakan,
     dan Riwayat di bawahnya; kalimat kepalanya menyebut pemilik berkasnya
     (`nama`) dan posisinya (`keadaan`, sama dengan kolom Posisi berkas). */
  $keadaanTahap = function (int $n) use ($posisi) {
      $kini = $posisi->tahap();
      if ($posisi === PosisiBerkas::TUNTAS || $posisi->transit()) {
          return $n <= $kini ? 'selesai' : 'belum';
      }

      return $n < $kini ? 'selesai' : ($n > $kini ? 'belum' : 'aktif');
  };
  $tahap = [];
  foreach (PosisiBerkas::TAHAP as $i => $nm) {
      $langkah = ['nama' => $i === 1 ? 'Tanggapan satker' : $nm, 'nomor' => $i + 1, 'keadaan' => $keadaanTahap($i + 1), 'ket' => null];
      if ($i === 1 && $posisi === PosisiBerkas::SETBA_KEMBALI) {
          $langkah['nama'] = 'Pengembalian Setba';
      }
      $tahap[] = $langkah;
      if ($posisi->transit() && $i + 1 === $posisi->tahap()) {
          $tahap[] = ['nama' => 'Setba', 'nomor' => null, 'keadaan' => 'aktif',
            'ket' => $posisi === PosisiBerkas::SETBA_TINJAU ? 'Tinjau tanggapan' : 'Teruskan hasil UKI'];
      }
  }
@endphp
<x-panel-kerja class="tl-alur" ikon="Route" judul="Alur verifikasi" :info="[
  'Tahap yang ditempuh tindak lanjut satuan kerja ini, dari registrasi sampai surat dan penetapan.',
  'Centang hijau sudah dilalui; lingkaran biru posisi berkasnya sekarang.',
]">
  <x-slot:kalimat>Berkas <b>{{ $nama }}</b> saat ini: {{ $keadaan }}.</x-slot:kalimat>
  <div class="relbaris" role="list" aria-label="Alur verifikasi tindak lanjut">
    @foreach($tahap as $i => $t)
      {{-- --i: urutannya, untuk garis tahap yang terisi berurutan (26 Sep). --}}
      <div class="{{ $t['keadaan'] }}" role="listitem" @if($t['keadaan'] === 'aktif') aria-current="step" @endif style="--i: {{ $i }}"
        aria-label="{{ $t['nama'] }}: {{ $t['keadaan'] === 'selesai' ? 'sudah dilalui' : ($t['keadaan'] === 'aktif' ? 'posisi saat ini' : 'tahap berikutnya') }}">
        @if($i > 0)<x-ikon n="ChevronRight" :s="12" class="tl-arah" />@endif
        <span class="bul">
          @if($t['keadaan'] === 'selesai')<x-ikon n="Check" :s="11" />@elseif($t['nomor']){{ $t['nomor'] }}@else<x-ikon n="Clock" :s="11" />@endif
        </span>
        <span class="nm">{{ $t['nama'] }}</span>
        @if($t['ket'] || $t['keadaan'] === 'aktif')<span class="tl-alur-ket">{{ $t['ket'] ?: 'Posisi saat ini' }}</span>@endif
      </div>
    @endforeach
  </div>
  @if($posisi === PosisiBerkas::SETBA_KEMBALI)
    <div class="tl-alur-kembali">
      <span>{{ $kembaliDari ?: 'Pemeriksa' }}</span><x-ikon n="ArrowRight" :s="12" />
      <b>Setba (saat ini)</b><x-ikon n="ArrowRight" :s="12" /><span>Satuan kerja untuk perbaikan</span>
    </div>
  @endif
</x-panel-kerja>
