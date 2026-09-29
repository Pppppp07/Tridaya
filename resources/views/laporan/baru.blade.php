@extends('rangka')
@section('judul', 'Catat laporan baru')
@section('isi')
@php
  use App\Support\Tampil;

  /* Catat laporan baru — padanan `FormBaru`. Dua langkah (sejak 25 Sep): isi
     laporan — surat dan seluruh temuan di satu halaman — lalu tinjau dan kirim.
     Satu formulir: menekan tombol apa pun membawa SELURUH isian, jadi tidak
     ada ketikan yang hilang saat berpindah. */
  $n = $d['n'];

  $langkah = [
    ['n' => 1, 'nama' => 'Isi laporan', 'ket' => 'Surat, temuan, dan rekomendasinya'],
    ['n' => 2, 'nama' => 'Tinjau & kirim', 'ket' => 'Periksa lalu kirim ke satuan kerja'],
  ];

  /* Mengajukan laporan memecahnya jadi penugasan terpisah ke banyak satuan
     kerja sekaligus — tidak bisa ditarik lewat aplikasi. */
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

  /* Meninggalkan formulir tidak pernah membuang isian diam-diam. Yang ada
     isinya disimpan sebagai draf; yang benar-benar ingin membuang memakai
     "Kosongkan formulir", dan itu bertanya dulu. */
  $tanyaKosong = [
    'judul' => 'Kosongkan formulir ini?',
    'ket' => 'Seluruh isian — data surat, temuan, dan rekomendasinya — dihapus, '
      .'termasuk draf yang tersimpan. Belum ada yang terkirim ke satuan kerja mana pun.',
    'tombol' => 'Ya, kosongkan',
    'nada' => 'merah',
  ];
@endphp

<div class="body">
  {{-- `data-langkah`: skrip mengembalikan posisi gulir sesudah halaman dimuat
       ulang, kecuali langkahnya berganti — pindah ke tinjauan dan kembali
       selalu mulai dari atas. `data-fokus`: isian kosong pertama di bagian
       yang ditunjuk mendapat kursor. `data-gulir`: bagian yang dituju, untuk
       jawaban kiriman skrip yang ditukar di tempat (29 Sep) — kiriman biasa
       menyebutnya di jangkar alamat. --}}
  <form method="post" action="{{ route('laporan.baru.simpan') }}" data-form-baru data-langkah="{{ $n }}"
    @if(session('fokus')) data-fokus @endif @if($gulir ?? null) data-gulir="{{ $gulir }}" @endif>
    @csrf
    {{-- Tombol bawaan: Enter di isian satu baris memakai tombol kirim pertama.
         Tanpa ini yang pertama "Kembali ke beranda" — Enter di Nomor surat
         meninggalkan formulir. Yang ini cuma menyimpan lalu kembali ke tempat
         yang sama. Dengan skrip, Enter tidak mengirim apa pun. --}}
    <button type="submit" name="aksi" value="simpan" class="fb-sr" style="visibility:hidden" tabindex="-1" aria-hidden="true">Simpan</button>

    {{-- Satu lajur untuk seluruh halaman — bilah tombol, stepper, dan isian
         sama lebar, tepi kanannya segaris. --}}
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
        {{-- Tidak dimatikan selagi isiannya belum lengkap (berbeda dari
             prototipe): isian yang belum sampai ke server tidak pernah bisa
             diperiksa. Server yang menahan, sambil membuka bagian yang kurang. --}}
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

      {{-- Satu daftar usulan untuk seluruh isian bentuk tindak lanjut. --}}
      <datalist id="bentuk-tl">
        @foreach($bentuk as $b)<option value="{{ $b }}"></option>@endforeach
      </datalist>

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
        {{-- Yang masih kurang disebut isiannya, dan "Tunjukkan" membuka bagian
             itu lalu menggulir ke sana. Di halaman isian keduanya dirender dan
             skrip menukarnya selagi diisi, seperti di prototipe. --}}
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
