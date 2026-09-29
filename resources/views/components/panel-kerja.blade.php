@props(['ikon', 'judul', 'info' => null, 'wajib' => false, 'ket' => '', 'lanjut' => '', 'aksiTersembunyi' => false])

{{-- Panel kerja di tab Kerjakan, untuk semua peran — padanan `PanelKerja`
     prototipe (25 Sep). Kata Hizkia: "rapihkan tampilan tindak lanjut untuk
     seluruh User yang ada dalam segala kondisi atau situasi seperti yang baru
     saja kita lakukan pada Dashboard dan Tambah Catatan baru". Bentuknya sama
     dengan formulir Catat laporan baru: kartu berkepala pita abu, baris
     `x-baris-isi` berlabel di kiri, bintang pada isian yang wajib ("* wajib
     diisi" sekali di atas), keterangan panjang di balik ikon Info, dan bilah
     tombol yang sejajar lajur isian.

     Kepalanya tetap seperti 22 Sep: nama tindakannya judul, satuan kerjanya
     disebut sebagai pemilik berkas dalam kalimat di bawahnya (slot `kalimat`).

     `ket` menyebut apa yang masih kurang (segitiga kuning); kalau tidak ada yang
     kurang, `lanjut` menyebut apa yang terjadi sesudah tombolnya ditekan. Yang
     ditulis di sini keadaan awalnya — sesudah itu skrip yang menggantinya
     (`aturKetKerja`). `aksiTersembunyi` menyembunyikan bilah tombol sampai
     skrip membukanya: panel putusan baru punya tombol sesudah putusannya
     dipilih. --}}
<section {{ $attributes->class(['kerja', 'fb']) }} aria-label="{{ $judul }}">
  <div class="kerja-kep">
    <span class="fb-no"><x-ikon :n="$ikon" :s="15" /></span>
    <span class="kerja-judul">
      <b>{{ $judul }}@if($info)<x-info :teks="$info" />@endif</b>
      @isset($kalimat)<span>{{ $kalimat }}</span>@endisset
    </span>
  </div>
  <div class="kerja-isi">
    @if($wajib)<p class="fb-legenda"><span class="fb-bintang" aria-hidden="true">*</span> wajib diisi</p>@endif
    {{ $slot }}
  </div>
  @isset($aksi)
    <div class="kerja-aksi" data-aksi-kerja @if($aksiTersembunyi) hidden @endif>
      {{ $aksi }}
      <span class="kerja-ket{{ $ket ? '' : ' lanjut' }}" data-ket-kerja @if(! $ket && ! $lanjut) hidden @endif><x-ikon :n="$ket ? 'AlertTriangle' : 'ArrowRight'" :s="15" /><span>{{ $ket ?: $lanjut }}</span></span>
    </div>
  @endisset
</section>
