{{-- Satu baris filter — padanan `Pemilah` di DasborUji.jsx.

     Keping yang muat di barisnya ditampilkan; sisanya — dan SELURUH pilihan,
     berkelompok dan bisa dicari — ada di menu "+N lainnya". Berapa yang muat
     diukur skrip dari lebar barisnya sendiri (salinan tak terlihat `dsb-ukur`),
     jadi tetap satu baris di layar mana pun. Tanpa skrip seluruh keping
     tampil dan barisnya melipat.

     Daftar pendek (paling banyak 8) tampil utuh — yang kosong cuma
     dipudarkan. Daftar panjang hanya menampilkan yang punya data pada
     filter lain saat ini, atau yang sedang dipilih.

     Masukan: $id, $judul, $satuan, $opsi, $pilih, $tunggal, $hitung (peta atau
     null), $aksi (awalan perbuatan: "tahun", "satker", "lain:intern"), $buang
     (kunci baris tambahan, atau null). --}}
@php
  use App\Support\Dasbor;

  $satuan ??= 'pilihan';
  $tunggal ??= false;
  $buang ??= null;
  $nOf = fn ($k) => $hitung === null ? null : ($hitung[$k] ?? 0);
  $on = fn ($k) => in_array($k, $pilih, true);
  $calon = count($opsi) <= 8 || $hitung === null ? $opsi
    : array_values(array_filter($opsi, fn ($o) => $on($o['k']) || $nOf($o['k']) > 0));
  /* Baris tambahan: "lain:intern" untuk Semua, "lain:intern:<nilai>" untuk pilihan. */
  $nilaiUbah = fn ($k) => $aksi.':'.$k;
  $semuaUbah = str_starts_with($aksi, 'lain:') ? $aksi : $aksi.':';
  $setel = 'setel:'.$aksi;
  $keping = function ($o, $cuma) use ($nOf, $on) {
    $n = $nOf($o['k']);
    return [
      'n' => $n,
      'on' => $on($o['k']),
      'kelas' => trim(($n === 0 ? 'nol ' : '').(! empty($o['nonaktif']) ? 'mati' : '')),
      'title' => ($o['lengkap'] ?? $o['nama']).(! empty($o['nonaktif']) ? ' — nonaktif di data master' : '')
        .($n !== null ? ' — '.$n.' tindak lanjut' : ''),
    ];
  };
  /* Kelompok menu, urut kemunculan pertamanya. */
  $grup = [];
  foreach ($opsi as $o) {
    $nm = $o['grup'] ?? '';
    $grup[$nm] ??= ['nama' => $nm, 'isi' => []];
    $grup[$nm]['isi'][] = $o;
  }
  $grup = array_values($grup);
@endphp
<div class="dsb-pemilah" data-pemilah data-satuan="{{ $satuan }}" data-setel="{{ $setel }}" data-tunggal="{{ $tunggal ? '1' : '' }}"
  data-jumlah="{{ count($opsi) }}" data-dipilih="{{ json_encode(array_values($pilih)) }}">
  <div class="dsb-keping" role="group" aria-labelledby="{{ $id }}" data-keping-baris>
    <button type="submit" name="ubah" value="{{ $semuaUbah }}" class="semua" aria-pressed="{{ $pilih ? 'false' : 'true' }}"
      title="Seluruh {{ $satuan }}">Semua</button>
    @foreach($calon as $o)
      @php $x = $keping($o, false); @endphp
      <button type="submit" name="ubah" value="{{ $nilaiUbah($o['k']) }}" aria-pressed="{{ $x['on'] ? 'true' : 'false' }}"
        data-keping="{{ json_encode($o['k']) }}" @if($x['kelas']) class="{{ $x['kelas'] }}" @endif
        title="{{ $x['title'] }}">
        @if(! empty($o['warna']))<i class="titik" style="background:{{ $o['warna'] }}" aria-hidden="true"></i>@endif{{ $o['nama'] }}
      </button>
    @endforeach
    {{-- Dibuka skrip; tanpa skrip seluruh keping sudah tampil. --}}
    <button type="button" class="buka-lain" hidden data-buka-lain aria-haspopup="dialog" aria-expanded="false"
      aria-controls="{{ $id }}-menu"><span data-sisa>+{{ count($opsi) - count($calon) }} lainnya</span><b class="n" hidden data-sisa-dipilih></b><x-ikon n="ChevronDown" :s="13" /></button>
    @if($buang)
      <button type="submit" name="ubah" value="{{ 'lepas:'.$buang }}" class="buang-baris"
        aria-label="Hapus baris filter {{ $judul }}" title="Hapus baris filter ini"><x-ikon n="X" :s="14" /></button>
    @endif
  </div>
  {{-- Salinan tak terlihat, semata untuk mengukur lebar tiap keping. --}}
  <div class="dsb-keping dsb-ukur" aria-hidden="true">
    <button type="button" class="semua" aria-pressed="{{ $pilih ? 'false' : 'true' }}" data-semua tabindex="-1">Semua</button>
    @foreach($calon as $o)
      @php $x = $keping($o, true); @endphp
      <button type="button" aria-pressed="{{ $x['on'] ? 'true' : 'false' }}" data-keping tabindex="-1"
        @if($x['kelas']) class="{{ $x['kelas'] }}" @endif>@if(! empty($o['warna']))<i class="titik" style="background:{{ $o['warna'] }}"></i>@endif{{ $o['nama'] }}</button>
    @endforeach
    {{-- Lencana angka hanya disisakan tempatnya kalau ada yang dipilih. --}}
    <button type="button" class="buka-lain" data-lain tabindex="-1">+{{ count($opsi) }} lainnya<b class="n" @unless($pilih) hidden @endunless>9</b><x-ikon n="ChevronDown" :s="13" /></button>
    @if($buang)<button type="button" class="buang-baris" data-buang tabindex="-1"><x-ikon n="X" :s="14" /></button>@endif
  </div>

  {{-- Daftar lengkap — padanan `MenuPilihan`: berkelompok, kotak cari kalau
       isinya banyak, jumlah tindak lanjut tiap pilihan, dan pilih/kosongkan
       sekaligus. Melayang di bawah tombolnya; dibuka dan ditempatkan skrip. --}}
  <div id="{{ $id }}-menu" class="dsb-menu" role="dialog" aria-label="Pilih {{ mb_strtolower($judul) }}" hidden data-menu>
    <div class="kepala-menu">
      <div class="judul-menu">
        <b>{{ $judul }}</b>
        <span>{{ $pilih ? count($pilih).' dipilih' : 'semua '.count($opsi).' '.$satuan }}</span>
      </div>
      @if(count($opsi) > 7)
        <label class="cari-menu">
          <x-ikon n="Search" :s="14" />
          <input type="search" placeholder="Cari {{ $satuan }}…" aria-label="Cari {{ $satuan }}" data-cari autocomplete="off">
        </label>
      @endif
      @unless($tunggal)
        <div class="aksi-menu">
          <button type="button" data-pilih-semua>Pilih semua</button>
          <button type="button" data-kosongkan @disabled(! $pilih)>Kosongkan</button>
        </div>
      @endunless
    </div>
    <div class="isi-menu">
      @foreach($grup as $g)
        <div class="grup-menu" role="group" aria-label="{{ $g['nama'] !== '' ? $g['nama'] : $judul }}" data-grup>
          @if($g['nama'] !== '')
            <div class="judul-grup">
              <span>{{ $g['nama'] }}</span>
              @if(! $tunggal && count($grup) > 1)
                <button type="button" data-pilih-grup>{{ collect($g['isi'])->every(fn ($o) => $on($o['k'])) ? 'lepas semua' : 'pilih semua' }}</button>
              @endif
            </div>
          @endif
          @foreach($g['isi'] as $o)
            @php $n = $nOf($o['k']); $aktif = $on($o['k']); @endphp
            <button type="submit" name="ubah" value="{{ $nilaiUbah($o['k']) }}" aria-pressed="{{ $aktif ? 'true' : 'false' }}"
              class="opsi{{ $n === 0 ? ' nol' : '' }}" data-opsi="{{ json_encode($o['k']) }}"
              data-teks="{{ mb_strtolower(implode(' ', array_filter([$o['nama'], $o['lengkap'] ?? '', (string) $o['k']]))) }}">
              <span class="kotak{{ $tunggal ? ' bulat' : '' }}" aria-hidden="true">@if($aktif)<x-ikon n="Check" :s="12" :w="3" />@endif</span>
              <span class="nm">
                @if(! empty($o['warna']))<i class="titik" style="background:{{ $o['warna'] }}" aria-hidden="true"></i>@endif{{ $o['lengkap'] ?? $o['nama'] }}
                @if(! empty($o['nonaktif']))<span class="tag-mati">nonaktif</span>@endif
              </span>
              @if($n !== null)<span class="jml">{{ $n }}</span>@endif
            </button>
          @endforeach
        </div>
      @endforeach
      <div class="kosong-menu" hidden data-kosong-menu>Tidak ada {{ $satuan }} yang cocok dengan “<span></span>”.</div>
    </div>
  </div>
</div>
