{{-- Sisa nilai terbesar sebagai TABEL (Hizkia, 24 Sep: "pada bagian ini
     mungkin akan lebih baik dalam bentuk table"), satu baris per TINDAK
     LANJUT satuan kerja: apa, satuan kerja mana, menunggu siapa, dan berapa
     sisanya. Klik barisnya (atau uraiannya) untuk membuka rincian
     rekomendasinya — tombol Kembali di sana mendarat di sini lagi. Di panel
     sempit (ponsel) kolom Kode disembunyikan dan "Menunggu" pindah ke bawah
     nama satuan kerja. --}}
@php use App\Support\Dasbor; @endphp
<x-dsb-panel id="dsb-p-perlu" :kelas="$kelas" judul="Sisa nilai terbesar"
  :info="'Lima tindak lanjut '.$kecil($kataBM).' dengan sisa nilai terbesar. Klik barisnya untuk membuka rinciannya.'"
  :kosong="! $d['perlu'] ? 'Tidak ada yang '.$kecil($kataBM).'.' : null">
  <div class="dsb-tw">
    <table class="dsb-tabel dsb-sisa">
      <thead>
        <tr>
          <th class="dsb-sisa-kode">Kode</th>
          <th>Rekomendasi</th>
          <th>Satuan kerja</th>
          <th class="dsb-sisa-tg">Menunggu</th>
          <th class="num">Sisa nilai</th>
        </tr>
      </thead>
      <tbody>
        @foreach($d['perlu'] as ['x' => $x, 'b' => $b, 'sisa' => $sisa])
          @php
            /* "Kenapa rekomendasi ini belum selesai, nyangkut di mana" (Mbak
               Puspi): satuan kerjanya, dan siapa yang ditunggu sekarang menurut
               posisi berkas baris itu (Satker, Setba, UKI, Itjen). */
            $mejaIni = Dasbor::meja($b);
            $ditunggu = $mejaIni === 'selesai' ? '' : Dasbor::PENDEK_MEJA[$mejaIni];
          @endphp
          <tr data-baris-klik>
            <td class="dsb-sisa-kode">{{ $x['rek']->kode }}</td>
            <td class="dsb-sisa-ur">
              <button type="submit" name="lihat" value="{{ \App\Http\Controllers\RingkasanController::nilaiLihat($x['rek']->id, [$b]) }}" title="{{ $x['rek']->kode }} — {{ $x['rek']->uraian }}">
                <span>{{ Dasbor::pokokUraian($x['rek']->uraian) }}</span>
              </button>
            </td>
            <td class="dsb-sisa-st">
              {{ $b->satker_id ? $namaSatker($b->satker_id) : '–' }}
              @if($ditunggu)<span class="dsb-sisa-tg-bawah">Menunggu {{ $ditunggu }}</span>@endif
            </td>
            <td class="dsb-sisa-tg">{{ $ditunggu ?: '–' }}</td>
            <td class="num dsb-sisa-rp">{{ $sisa ? $rpk($sisa) : '–' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</x-dsb-panel>
