@extends(\App\Support\Halaman::bagian() ? 'rangka-bagian' : 'rangka')
@section('judul', 'Catat laporan baru')
@section('isi')
@php
  use App\Support\Tampil;

  /* Catat laporan baru, dua langkah: isi lalu tinjau. Satu formulir: tombol apa pun membawa
     seluruh isian. */
  $n = $d['n'];

  $langkah = [
    ['n' => 1, 'nama' => 'Isi laporan', 'ket' => 'Surat, temuan, dan rekomendasinya'],
    ['n' => 2, 'nama' => 'Tinjau & kirim', 'ket' => 'Periksa lalu kirim ke satuan kerja'],
  ];

  /* Mengajukan memecahnya jadi penugasan ke banyak satuan kerja — tidak bisa ditarik lewat
     aplikasi. */
  $cek = fn ($teks) => '<li><span style="color:var(--ok);flex:none">&#10003;</span><span>'.e($teks).'</span></li>';
  $tanyaAjukan = [
    'judul' => 'Ajukan laporan ini?',
    'ket' => "Laporan akan pecah jadi {$ringkas['pen']} penugasan dari {$ringkas['rek']} rekomendasi, "
      ."untuk {$ringkas['sat']} satuan kerja. Tiap penugasan berjalan dengan tenggat dan posisinya sendiri.",
    'rincian' => '<ul class="ceklis">'
      .$cek("{$ringkas['tem']} temuan, {$ringkas['rek']} rekomendasi")
      .$cek("{$ringkas['sat']} satuan kerja menerima {$ringkas['pen']} penugasan")
      .$cek("{$ringkas['ten']} tanggal tenggat berbeda")
      .$cek('Nilai yang ditagih '.Tampil::rupiah($ringkas['nilai']))
      .'</ul>',
    'tombol' => 'Ya, ajukan laporan',
    'nada' => 'hijau',
  ];

  /* Meninggalkan formulir tidak membuang isian; "Kosongkan formulir" bertanya dulu. */
  $tanyaKosong = [
    'judul' => 'Kosongkan formulir ini?',
    'ket' => 'Seluruh isian — data surat, temuan, dan rekomendasinya — dihapus, '
      .'termasuk draf yang tersimpan. Belum ada yang terkirim ke satuan kerja mana pun.',
    'tombol' => 'Ya, kosongkan',
    'nada' => 'merah',
  ];
@endphp

<div class="body">
  {{-- `data-langkah`: posisi gulir dipulihkan kecuali langkahnya berganti. `data-fokus`: kursor
       ke isian kosong pertama di bagian yang ditunjuk. `data-gulir`: bagian yang dituju. --}}
  <form method="post" action="{{ route('laporan.baru.simpan') }}" data-form-baru data-langkah="{{ $n }}"
    @if(session('fokus')) data-fokus @endif @if($gulir ?? null) data-gulir="{{ $gulir }}" @endif>
    @csrf
    {{-- Tombol bawaan untuk Enter: tanpa ini Enter memakai "Kembali ke beranda". Yang ini hanya
         menyimpan lalu kembali ke tempat yang sama. --}}
    <button type="submit" name="aksi" value="simpan" class="fb-sr" style="visibility:hidden" tabindex="-1" aria-hidden="true">Simpan</button>

    <div class="lajurisi fb">
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px">
        <button class="link" type="submit" name="aksi" value="tinggalkan">
          <x-ikon n="ArrowLeft" :s="16" /> Kembali ke beranda
        </button>
        <div style="flex:1"></div>
        @if($adaIsi)
          <button class="btn" type="submit" name="aksi" value="kosongkan" data-pastikan='@json($tanyaKosong)'>
            <x-ikon n="X" :s="15" /> Kosongkan formulir
          </button>
        @endif
        <button class="btn" type="submit" name="aksi" value="simpan-draf" @disabled(! $adaIsi)>
          <x-ikon n="Save" :s="15" /> Simpan draft
        </button>
        {{-- Tidak dimatikan selagi belum lengkap: server yang menahan sambil membuka bagian
             yang kurang. --}}
        @if($n === 1)
          <button class="btn btn-p" type="submit" name="aksi" value="maju">
            Tinjau &amp; kirim <x-ikon n="ArrowRight" :s="15" />
          </button>
        @else
          <button class="btn btn-p" type="submit" name="aksi" value="ajukan" data-pastikan='@json($tanyaAjukan)'>
            Ajukan laporan <x-ikon n="Send" :s="15" />
          </button>
        @endif
      </div>

      @if($adaDraf && $d['disimpan'])
        <div class="akibat" style="margin-bottom:14px">
          <x-ikon n="Save" :s="15" />
          <span>
            Melanjutkan draf yang disimpan {{ Tampil::tgl($d['disimpan']) }}. Draf ini cuma
            terlihat oleh Setba dan belum terkirim ke satuan kerja mana pun.
          </span>
        </div>
      @endif

      {{-- Stepper: yang sudah lewat bercentang, yang sedang dikerjakan bergaris biru. --}}
      <div class="langkah">
        @foreach($langkah as $l)
          <div @class(['aktif' => $n === $l['n'], 'selesai' => $n > $l['n']])>
            <span class="bul">@if($n > $l['n'])<x-ikon n="Check" :s="13" />@else{{ $l['n'] }}@endif</span>
            <span>
              <span class="nm">{{ $l['nama'] }}</span>
              <span class="ket">{{ $l['ket'] }}</span>
            </span>
          </div>
        @endforeach
      </div>

      @if($n === 1)
        @include('laporan.baru-isi')
      @else
        @include('laporan.baru-tinjau')
      @endif

      <div class="bilah">
        @if($n === 2)
          <button class="btn" type="submit" name="aksi" value="mundur">
            <x-ikon n="ArrowLeft" :s="14" /> Kembali mengisi
          </button>
        @endif
        @if($n === 1)
          <button class="btn btn-p" type="submit" name="aksi" value="maju">
            Tinjau &amp; kirim <x-ikon n="ArrowRight" :s="14" />
          </button>
        @else
          <button class="btn btn-ok" type="submit" name="aksi" value="ajukan" data-pastikan='@json($tanyaAjukan)'>
            <x-ikon n="Send" :s="14" /> Ajukan laporan
          </button>
        @endif
        {{-- Yang kurang disebut isiannya; "Tunjukkan" membuka bagian itu lalu menggulir ke
             sana. --}}
        <span class="ket">
          @if($n === 1)
            <span data-kurang @unless($kurang) hidden @endunless><x-ikon n="AlertTriangle" :s="15" class="fb-ikon kurang" /><span data-kurang-teks>{{ $kurang['teks'] ?? '' }}</span> <button type="submit" name="aksi" value="tunjukkan" class="fb-tunjuk">Tunjukkan</button></span>
            <span data-lengkap @if($kurang) hidden @endif><x-ikon n="CheckCircle2" :s="15" class="fb-ikon" />Sudah lengkap · <span data-lengkap-teks>{{ $ringkas['tem'] }} temuan, {{ $ringkas['rek'] }} rekomendasi, {{ $ringkas['sat'] }} satuan kerja</span></span>
          @else
            {{ $ringkas['tem'] }} temuan, {{ $ringkas['rek'] }} rekomendasi, {{ $ringkas['sat'] }} satuan kerja
          @endif
        </span>
      </div>
    </div>
  </form>
</div>
@endsection
