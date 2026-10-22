{{-- Rincian kartu yang dibuka: bagian-bagian angkanya, satu kalimat dan sumber datanya, lalu
     panelnya. --}}
@php
  use App\Support\Dasbor;

  /* Kotak rincian = tombol filter, sama dengan keping filter utama: angkanya dihitung tanpa filternya
     sendiri (`tindakKotak`), yang berlaku menyala, pilihan lain dipudarkan. Kalimat di bawahnya
     memakai angka yang sama, jadi jumlah kotaknya cocok. */
  $tK = $d['tindakKotak'];
  $kotak = fn ($n, $teks, $warna, $ubah, $on, $adaPilihan) => ['n' => $n, 't' => $teks, 'warna' => $warna,
    'ubah' => $ubah, 'on' => $on, 'redup' => $adaPilihan && ! $on];
  $kotakHasil = fn ($h, $n, $teks, $warna) => $kotak($n, $teks, $warna, 'hasil:'.$h, $f['hasil'] === $h, $f['hasil'] !== '');
  /* Tahap yang ada isinya (atau sedang difilter), urut rantai posisi. */
  $mejaBM = array_values(array_filter(Dasbor::daftarMeja(),
    fn ($m) => ($tK['meja'][$m['k']] ?? 0) > 0 || in_array($m['k'], $f['meja'], true)));
  $sebutMeja = fn ($m) => $m['k'] === 'selesai' ? 'selesai diperiksa'
    : 'di '.($m['k'] === 'satker' ? 'satuan kerja' : $m['nama']);
  $nBpk = fn ($x) => $x === 'belumUnggah' ? $tK['belumUnggah'] : $tK['bpk'][$x];
  $kodeBpk = array_values(array_filter(Dasbor::FILTER_BPK, fn ($x) => $nBpk($x) > 0 || in_array($x, $f['bpk'], true)));

  $ketSiptl = $tK['bpk']['n']
    ? 'Dari '.$tK['bpk']['n'].' tindak lanjut di SIPTL'
      .($tK['belumUnggah'] ? ', ditambah '.$tK['belumUnggah'].' yang sudah memadai tapi belum diunggah' : '')
      .'. Status SIPTL dicatat Setba dari situs BPK.'
    : 'Belum ada data SIPTL'.($tK['belumUnggah'] ? '; '.$tK['belumUnggah'].' tindak lanjut sudah memadai tapi belum diunggah' : '').'.';

  $RINCIAN = [
    'M' => [
      'judul' => 'Status',
      'angka' => [$kotakHasil('M', $t['M'], $kecil($kataM), $W['M']), $kotakHasil('BM', $t['BM'], $kecil($kataBM), $W['BM'])],
      /* Hanya sampai Inspektorat; status SIPTL di kartunya sendiri. */
      'ket' => 'Dari '.$t['n'].' tindak lanjut satuan kerja, menurut hasil verifikasi Inspektorat'
        .($adaSiptl ? ' (status SIPTL tidak dihitung)' : '').'.',
      /* Data historis tanpa tanggal selesai di sistem: grafik Per bulan akan tampak tidak ada yang
         selesai, jadi tidak dipasang di tab itu. */
      'panel' => $k['data'] === 'historis' ? [['satker', 'dsb-lebar3'], ['perlu', 'dsb-lebar3']]
        : [['tren', 'dsb-lebar2'], ['satker', null], ['perlu', 'dsb-lebar3']],
    ],
    'tunggu' => [
      'judul' => 'Proses tindak lanjut',
      'angka' => array_map(fn ($m) => $kotak($tK['meja'][$m['k']] ?? 0, $sebutMeja($m), $warnaProses($m['k']),
        'meja:'.$m['k'], in_array($m['k'], $f['meja'], true), (bool) $f['meja']), $mejaBM),
      'ket' => 'Dari '.$tK['BM'].' tindak lanjut '.$kecil($kataBM).', menurut posisi berkasnya: di satuan kerja'
        .' menunggu tanggapannya, di Setba, UKI, atau Inspektorat sedang diproses.',
      'panel' => [['tunggu', 'dsb-lebar2'], ['meja', null]],
    ],
    'sisa' => [
      'judul' => 'Sisa nilai',
      'angka' => [$kotakHasil('M', $rpk($t['nilai'] - $t['sisa']), 'diakui', $W['M']),
        $kotakHasil('BM', $rpk($t['sisa']), 'sisa', 'var(--warn)')],
      'ket' => 'Dari nilai tindak lanjut '.$rpk($t['nilai']).'. Diakui = sudah dinyatakan '.$kecil($kataM)
        .' oleh Inspektorat; sisa bukan uang yang belum disetor.',
      'panel' => [['sisa-satker', 'dsb-lebar2'], ['sisa-tahun', null], ['perlu', 'dsb-lebar3']],
    ],
    'ss' => [
      'judul' => 'SS di SIPTL',
      'angka' => array_map(fn ($x) => $kotak($nBpk($x), $x === 'belumUnggah' ? 'Belum diunggah' : $x.' · '.$STATUS($x),
        $W[$x], 'bpk:'.$x, in_array($x, $f['bpk'], true), (bool) $f['bpk']), $kodeBpk),
      'ket' => $ketSiptl,
      'panel' => [['status', null], ['tahun-siptl', null]],
    ],
  ];
  $R = $RINCIAN[$k['kartu']];
@endphp
{{-- data-tumbuh: grafik tumbuh saat rincian baru dibuka, tidak saat filter diganti. --}}
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
      <div class="dsb-rinci-angka" role="group" aria-label="Filter {{ mb_strtolower($R['judul']) }}">
        @foreach($R['angka'] as $x)
          <button type="submit" name="ubah" value="{{ $x['ubah'].'@dsb-rincian' }}" aria-pressed="{{ $x['on'] ? 'true' : 'false' }}"
            class="dsb-bag{{ $x['redup'] ? ' redup' : '' }}" title="{{ $x['on'] ? 'Lepas filter ini' : 'Filter: '.$x['t'] }}">
            <b>{{ $x['n'] }}</b>
            <span>@if($x['warna'])<i style="background:{{ $x['warna'] }}" aria-hidden="true"></i>@endif{{ $x['t'] }}</span>
          </button>
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
