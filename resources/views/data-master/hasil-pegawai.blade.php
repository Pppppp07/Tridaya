@php
  use App\Support\DirektoriIrm;
  use App\Support\Rangka;

  /* Hasil pencarian direktori di jendela Tambah pengguna — templat yang sama untuk halaman dan
     potongan skrip. */
  $mencari = mb_strlen($q) >= 2;
@endphp
<div class="lbl hasil-lbl">
  @if($error)
    {{ $error }}
  @elseif(! $mencari)
    Ketik minimal 2 karakter.
  @else
    {{ $hasil->count() ? $hasil->count().' pegawai ditemukan' : 'Tidak ada pegawai yang cocok dengan “'.$q.'”.' }}
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
            Sudah memiliki akun {{ $a->peran->pendek() }}{{ $a->peran === \App\Enums\PeranPengguna::SATKER && $a->satker ? ' · '.$a->satker->namaPendek() : '' }}
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
