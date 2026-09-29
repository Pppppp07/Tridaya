{{-- Rincian kartu yang dibuka: bagian-bagian yang membentuk angkanya (tanpa
     tanda hitung — Hizkia, 24 Sep: "39 LHP + 10 LHA = 49" terlihat "kurang
     professional"), satu kalimat keseluruhan dan sumber datanya, lalu panel
     rinciannya. Semuanya per tindak lanjut satuan kerja, seperti kartunya.
     Judulnya nama kartu saja; sebutan "Asal angka" dibuang 24 Sep. --}}
@php
  use App\Support\Dasbor;

  $trenTeks = $gerak === 0 ? 'Sama dengan akhir '.$bulanLalu.'.'
    : ($gerak < 0 ? 'Turun' : 'Naik').' '.abs($gerak).' sejak akhir '.$bulanLalu.'.';
  /* Yang belum memadai menurut mejanya: satuan kerja (menunggu tanggapannya)
     lebih dulu, lalu yang sedang diproses — urutan rel posisi. */
  $mejaBM = array_values(array_filter(Dasbor::daftarMeja(), fn ($m) => ($t['meja'][$m['k']] ?? 0) > 0));
  $sebutMeja = fn ($m) => $m['k'] === 'selesai' ? 'selesai diperiksa'
    : 'di '.($m['k'] === 'satker' ? 'satuan kerja' : $m['nama']);

  $RINCIAN = [
    'M' => [
      'judul' => $kataM,
      'angka' => [['n' => $t['M'], 't' => $kecil($kataM), 'warna' => $W['M']],
        ['n' => $t['BM'], 't' => $kecil($kataBM), 'warna' => $W['BM']]],
      'ket' => 'Dari '.$t['n'].' tindak lanjut satuan kerja di '.$t['rek'].' rekomendasi. '.$kataM
        .' bila sudah dinyatakan '.$kecil($kataM).' oleh Inspektorat.',
      'panel' => [['tren', 'dsb-lebar2'], ['satker', null]],
    ],
    'BM' => [
      'judul' => $kataBM,
      'angka' => array_values(array_map(fn ($j) => ['n' => $t['jenisBM'][$j], 't' => $j, 'warna' => null],
        array_filter(['LHP', 'LHA'], fn ($j) => ($t['jenisBM'][$j] ?? 0) > 0))),
      'ket' => 'Dari '.$t['n'].' tindak lanjut satuan kerja. '.$trenTeks,
      'panel' => [['satker', 'dsb-lebar2'], ['kategori', null], ['perlu', 'dsb-lebar3']],
    ],
    'tunggu' => [
      'judul' => 'Menunggu satuan kerja',
      'angka' => array_map(fn ($m) => ['n' => $t['meja'][$m['k']], 't' => $sebutMeja($m),
        'warna' => $m['k'] === 'satker' ? $W['tunggu'] : $W['proses']], $mejaBM),
      'ket' => 'Dari '.$t['BM'].' tindak lanjut '.$kecil($kataBM)
        .': yang di satuan kerja menunggu tanggapannya, sisanya sedang diproses.',
      'panel' => [['tunggu', 'dsb-lebar2'], ['meja', null]],
    ],
    'sisa' => [
      'judul' => 'Sisa nilai',
      'angka' => [['n' => $rpk($t['nilai'] - $t['sisa']), 't' => 'diakui', 'warna' => $W['M']],
        ['n' => $rpk($t['sisa']), 't' => 'sisa', 'warna' => 'var(--warn)']],
      'ket' => 'Dari nilai tindak lanjut '.$rpk($t['nilai']).'. Diakui = sudah dinyatakan '.$kecil($kataM)
        .' oleh Inspektorat; sisa bukan uang yang belum disetor.',
      'panel' => [['sisa-satker', 'dsb-lebar2'], ['sisa-tahun', null], ['perlu', 'dsb-lebar3']],
    ],
    'ss' => [
      'judul' => 'SS di SIPTL',
      'angka' => $t['bpk']['n'] ? array_values(array_map(fn ($x) => ['n' => $t['bpk'][$x], 't' => $x.' · '.$STATUS($x), 'warna' => $W[$x]],
        array_filter(Dasbor::URUT_BPK, fn ($x) => $t['bpk'][$x] > 0))) : [],
      'ket' => $t['bpk']['n'] ? 'Dari '.$t['bpk']['n'].' tindak lanjut LHP. Status SIPTL dicatat Setba dari situs BPK; LHA tidak masuk SIPTL.'
        : 'LHA tidak masuk SIPTL.',
      'panel' => [['status', null], ['tahun-siptl', null]],
    ],
  ];
  $R = $RINCIAN[$k['kartu']];
@endphp
{{-- data-tumbuh: grafiknya tumbuh saat rincian kartu ini baru dibuka, tidak
     saat filternya diganti (data-wadah menyebut kartunya). --}}
<section id="dsb-rincian" class="dsb-rincian" aria-labelledby="dsb-rincian-judul" data-tumbuh data-wadah="kartu:{{ $k['kartu'] }}">
  <div class="dsb-rincian-kepala">
    <h2 id="dsb-rincian-judul">{{ $R['judul'] }}</h2>
    <span class="sela"></span>
    <button type="submit" name="ubah" value="{{ 'kartu:'.$k['kartu'] }}" class="ikonbtn" aria-label="Tutup rincian" title="Tutup">
      <x-ikon n="X" :s="16" />
    </button>
  </div>
  <div class="dsb-asal">
    @if($R['angka'])
      <div class="dsb-rinci-angka">
        @foreach($R['angka'] as $x)
          <span class="dsb-bag">
            <b>{{ $x['n'] }}</b>
            <span>@if($x['warna'])<i style="background:{{ $x['warna'] }}" aria-hidden="true"></i>@endif{{ $x['t'] }}</span>
          </span>
        @endforeach
      </div>
    @endif
    <p class="dsb-asal-ket">{{ $R['ket'] }}</p>
  </div>
  <div class="dsb-petak tiga">
    @foreach($R['panel'] as [$panel, $kelas])
      @include('ringkasan.panel.'.$panel, ['kelas' => $kelas])
    @endforeach
  </div>
</section>
