@php
  use App\Support\DirektoriIrm;
  use App\Support\Rangka;

  /* Hasil pencarian IRM di jendela pemilih penanggung jawab — templat yang sama untuk halaman
     dan potongan skrip. Tombol hanya mengirim NIP. */
  $kini = $unit->penanggungJawab;
@endphp
<div class="lbl hasil-lbl">
  @if($error ?? null)
    {{ $error }}
  @elseif($mencari)
    {{ $hasil->count() ? $hasil->count().' pegawai ditemukan' : 'Tidak ada pegawai di IRM yang cocok dengan “'.$q.'”.' }}
  @else
    {{ $hasil->count() ? 'Pegawai '.$unit->namaPendek().' di IRM' : 'Belum ada pegawai satuan kerja ini di IRM. Cari berdasarkan nama atau NIP.' }}
  @endif
</div>
<div class="daftarpegawai">
  @foreach($hasil as $p)
    @php
      $pegang = $pemegang[$p['nip']] ?? null;
      $sekarang = $pegang && $pegang->id === $unit->id;
      /* Pegawai yang sudah berakun mendapat akses Satker pada identitas yang sama. */
      $berakun = ($pusat ?? collect())[$p['nip']] ?? null;
      $pastikan = $kini && ! $pegang ? [
        'judul' => 'Ganti penanggung jawab '.$unit->namaPendek().'?',
        'ket' => $kini->name.' tidak lagi bisa masuk sebagai '.$unit->namaPendek().'. Mulai sekarang '
          .$p['nama'].' yang mengerjakan tindak lanjutnya dan menerima pemberitahuannya, di aplikasi dan lewat email '.$p['email'].'.',
        'tombol' => 'Ya, ganti', 'nada' => 'jingga', 'balik' => false,
      ] : null;
    @endphp
    <form method="post" action="{{ route('master.unit.pj', $unit) }}">
      @csrf
      <input type="hidden" name="nip" value="{{ $p['nip'] }}">
      <button type="submit" class="pegawai" @disabled($pegang)
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
            Tambahkan akses Satuan kerja · peran lain tetap tersedia
          @elseif($p['unit'] !== $unit->kode)
            <span class="beda">Pegawai satuan kerja lain</span> · Pilih
          @else
            Pilih <x-ikon n="ChevronRight" :s="13" />
          @endif
        </span>
      </button>
    </form>
  @endforeach
</div>
