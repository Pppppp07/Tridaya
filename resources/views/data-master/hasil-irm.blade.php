@php
  use App\Support\DirektoriIrm;
  use App\Support\Rangka;

  /* Hasil pencarian IRM di jendela pemilih penanggung jawab. Dipakai dua kali:
     saat halamannya digambar (`?pj=&q=`), dan sebagai potongan yang diambil
     skrip sambil mengetik — satu templat, jadi keduanya tidak mungkin berbeda.

     Yang dikirim tiap tombol cuma NIP. Nama, jabatan, dan email diambil ulang
     dari IRM di server: "jangan tertukar atributnya". */
  $kini = $unit->penanggungJawab;
@endphp
<div class="lbl hasil-lbl">
  @if($error ?? null)
    {{ $error }}
  @elseif($mencari)
    {{ $hasil->count() ? $hasil->count().' pegawai cocok' : 'Tidak ada pegawai di IRM yang cocok dengan “'.$q.'”.' }}
  @else
    {{ $hasil->count() ? 'Pegawai '.$unit->namaPendek().' di IRM' : 'Unit kerja ini belum punya pegawai di IRM. Ketik nama atau NIP untuk mencari.' }}
  @endif
</div>
<div class="daftarpegawai">
  @foreach($hasil as $p)
    @php
      $pegang = $pemegang[$p['nip']] ?? null;
      $sekarang = $pegang && $pegang->id === $unit->id;
      /* Petugas pusat yang sudah berakun tidak bisa sekaligus jadi
         penanggung jawab unit kerja — satu NIP satu akun (27 Sep). */
      $berakun = ($pusat ?? collect())[$p['nip']] ?? null;
      $pastikan = $kini && ! $pegang && ! $berakun ? [
        'judul' => 'Ganti penanggung jawab '.$unit->namaPendek().'?',
        'ket' => $kini->name.' tidak lagi bisa masuk sebagai '.$unit->namaPendek().'. Mulai sekarang '
          .$p['nama'].' yang mengerjakan tindak lanjutnya dan menerima pemberitahuannya, di aplikasi dan lewat email '.$p['email'].'.',
        'tombol' => 'Ya, ganti', 'nada' => 'jingga', 'balik' => false,
      ] : null;
    @endphp
    <form method="post" action="{{ route('master.unit.pj', $unit) }}">
      @csrf
      <input type="hidden" name="nip" value="{{ $p['nip'] }}">
      <button type="submit" class="pegawai" @disabled($pegang || $berakun)
        @if($pastikan) data-pastikan='@json($pastikan)' @endif>
        <span class="rupa">{{ Rangka::inisial($p['nama']) }}</span>
        <span class="teks">
          <b>{{ $p['nama'] }}</b>
          <span class="mono">NIP {{ DirektoriIrm::nipTampil($p['nip']) }}</span>
          <span>{{ $p['jabatan'] }} · {{ $pendekUnit[$p['unit']] ?? $p['unitNama'] }}</span>
          <span>{{ $p['email'] }}</span>
        </span>
        <span class="ket">
          @if($sekarang)
            Penanggung jawab sekarang
          @elseif($pegang)
            Sudah memegang {{ $pegang->namaPendek() }}
          @elseif($berakun)
            Sudah berakun {{ $berakun->peran->pendek() }}
          @elseif($p['unit'] !== $unit->kode)
            <span class="beda">Pegawai unit lain</span> · Pilih
          @else
            Pilih <x-ikon n="ChevronRight" :s="13" />
          @endif
        </span>
      </button>
    </form>
  @endforeach
</div>
