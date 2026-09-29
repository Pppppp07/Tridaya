@php
  use App\Support\DirektoriIrm;
  use App\Support\Rangka;

  /* Hasil pencarian direktori di jendela Tambah pengguna — padanan daftar di
     `JendelaPenggunaBaru` prototipe. Dipakai dua kali: saat halamannya
     digambar (`?jendela=pengguna&q=`), dan sebagai potongan yang diambil skrip
     sambil mengetik — satu templat, jadi keduanya tidak mungkin berbeda.

     Tiap baris cuma link ke langkah berikutnya dengan NIP-nya. Nama,
     jabatan, dan email diambil ulang dari direktori di server. */
  $mencari = mb_strlen($q) >= 2;
@endphp
<div class="lbl hasil-lbl">
  @if($error)
    {{ $error }}
  @elseif(! $mencari)
    Ketik paling tidak dua huruf.
  @else
    {{ $hasil->count() ? $hasil->count().' pegawai cocok' : 'Tidak ada pegawai yang cocok dengan “'.$q.'”.' }}
  @endif
</div>
<div class="daftarpegawai">
  @foreach($hasil as $p)
    @php
      $a = $akun[$p['nip']] ?? null;
      $sudah = $a && $a->aktif;
    @endphp
    @if($sudah)
      <span class="pegawai" aria-disabled="true">
    @else
      <a class="pegawai" href="{{ route('master', ['tab' => 'pengguna', 'jendela' => 'pengguna', 'q' => $q, 'nip' => $p['nip']]) }}">
    @endif
        <span class="rupa">{{ Rangka::inisial($p['nama']) }}</span>
        <span class="teks">
          <b>{{ $p['nama'] }}</b>
          <span class="mono">NIP {{ DirektoriIrm::nipTampil($p['nip']) }}</span>
          <span>{{ $p['jabatan'] }} · {{ $p['unitNama'] }}</span>
          <span>{{ $p['email'] }}</span>
        </span>
        <span class="ket">
          @if($sudah)
            Sudah berakun {{ $a->peran->pendek() }}{{ $a->peran === \App\Enums\PeranPengguna::SATKER && $a->satker ? ' · '.$a->satker->namaPendek() : '' }}
          @elseif($a)
            Akun nonaktif · Aktifkan <x-ikon n="ChevronRight" :s="13" />
          @else
            Pilih <x-ikon n="ChevronRight" :s="13" />
          @endif
        </span>
    @if($sudah)
      </span>
    @else
      </a>
    @endif
  @endforeach
</div>
