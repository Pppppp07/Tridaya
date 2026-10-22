@extends(\App\Support\Halaman::bagian() ? 'rangka-bagian' : 'rangka')
@section('judul', 'Bandingkan data historis')
@section('isi')
@php
  use App\Enums\PosisiBerkas;
  use App\Support\Historis\PetaKolom;

  $d = $row['data'];
  $v = fn ($k) => (string) old('data.'.$k, $d[$k] ?? '');
  $salah = $row['errors_kolom'] ?? [];
  $jumlahBaris = count($draf->isian['baris']);
  $hitung = collect($banding)->countBy('keadaan');
  $hasilOpsi = ['' => 'Belum diketahui', 'M' => 'Memadai', 'BM' => 'Belum memadai'];
  $pilihanTetap = [
    'sumber' => ['LHP' => 'LHP · BPK', 'LHA' => 'LHA · Inspektorat'],
    'hasil' => $hasilOpsi, 'hasil_uki' => $hasilOpsi,
    'status_bpk' => ['' => 'Belum tercatat di SIPTL / tidak berlaku', 'SS' => 'SS · Sudah sesuai', 'BS' => 'BS · Belum sesuai', 'BT' => 'BT · Belum ditindaklanjuti'],
    'posisi' => ['' => 'Pilih posisi saat ini'] + collect(PosisiBerkas::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all(),
  ];
  $master = ['satker_id' => 'satker', 'kategori_id' => 'kategori', 'sifat_id' => 'sifat', 'bentuk_id' => 'bentuk'];
  $tanggal = ['tgl_surat', 'tgl_terima', 'tanggal_acuan', 'siptl_tanggal', 'renaksi'];
  $panjang = ['uraian', 'rencana_aksi', 'catatan', 'uraian_verifikasi', 'catatan_bpk'];
@endphp
<div class="body hrv">
  <div class="hrv-head">
    <div><a class="hrv-back" href="{{ route('laporan.historis.review', $draf) }}">← Ringkasan review</a><h2>{{ $row['sheet'] }} · baris {{ $row['baris'] }}</h2>
      <p>{{ $draf->nama_berkas }}@if($row['kembar'] ?? []) · juga mewakili baris {{ implode(', ', $row['kembar']) }} yang isinya sama persis @endif</p></div>
    <div class="hrv-nav">
      @if($i > 0)<a class="btn btn-s" href="{{ route('laporan.historis.baris', [$draf, $i - 1]) }}">← Sebelumnya</a>@endif
      @if($i + 1 < $jumlahBaris)<a class="btn btn-s" href="{{ route('laporan.historis.baris', [$draf, $i + 1]) }}">Berikutnya →</a>@endif
    </div>
  </div>
  @if(!$draf->diimpor_pada && ($row['errors'] || $row['warnings']))
    <section class="hrv-card hrv-issues"><h3>{{ $row['errors'] ? 'Perlu diperbaiki' : 'Perlu diperhatikan' }}</h3>@if($row['errors'])<ul>@foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>@endif @if($row['warnings'])<div class="hrv-warnings"><b>Periksa sebelum menyetujui</b><ul>@foreach($row['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>@endif</section>
  @endif

  <section class="hrv-card">
    <h3>Perbandingan data</h3>
    <p class="hint">Kiri: isi file asli. Kanan: isian sistem yang akan masuk ke database — koreksi langsung di sini, lalu simpan. File aslinya tidak berubah.</p>
    <p class="hrv-hitung">@foreach(PetaKolom::KEADAAN as $kd => $x)@if($hitung[$kd] ?? 0)<span class="hrv-badge {{ $x['nada'] }}">{{ $hitung[$kd] }} {{ mb_strtolower($x['nama']) }}</span> @endif @endforeach</p>
    <form method="post" action="{{ route('laporan.historis.simpan', [$draf, $i]) }}">@csrf<input type="hidden" name="versi" value="{{ $draf->versi }}">
      <fieldset @disabled($draf->diimpor_pada) class="hrv-banding-wadah">
        <legend class="sr-only">Perbandingan dan koreksi data baris {{ $row['baris'] }}</legend>
        <table class="hrv-banding">
          <thead><tr><th scope="col">Kolom sistem</th><th scope="col">Isi file asli</th><th scope="col">Diisi sistem</th><th scope="col">Keadaan</th></tr></thead>
          <tbody>
            @foreach($banding as $k => $b)
              @php $id = 'hrv-k-'.$k; $kd = PetaKolom::KEADAAN[$b['keadaan']]; @endphp
              <tr id="kolom-{{ $k }}" @class(['k-'.$b['keadaan'], 'k-salah' => isset($salah[$k])])>
                <th scope="row"><label for="{{ $id }}">{{ $b['label'] }}</label>@if($b['wajib'])<span class="wajib" aria-hidden="true">*</span>@endif</th>
                <td class="asli" data-judul="Isi file asli">
                  @forelse($b['asal'] as $s)
                    <span class="judul">{{ $s['judul'] }}</span><span class="isi">{{ $s['isi'] !== '' ? $s['isi'] : '—' }}</span>
                  @empty
                    <span class="tiada">Kolomnya tidak ada di file</span>
                  @endforelse
                </td>
                <td class="sistem" data-judul="Diisi sistem">
                  @if(isset($master[$k]))
                    <select id="{{ $id }}" name="data[{{ $k }}]"><option value="">{{ $k === 'kategori_id' ? 'Belum diklasifikasikan' : 'Pilih' }}</option>@foreach($pilihan[$master[$k]] as $p)<option value="{{ $p->id }}" @selected($v($k) === (string) $p->id)>{{ $p->nama }}{{ $master[$k] === 'satker' ? ' · '.($p->kode_satker ?: $p->kode) : ($master[$k] === 'kategori' ? ' · '.$p->sumber->value : '') }}</option>@endforeach</select>
                    @if($k === 'bentuk_id')
                      {{-- Teks dari file; tampil (CSS) hanya selama belum ada bentuk yang dipilih dari daftar. --}}
                      <span class="hrv-bentuk-teks">
                        <label for="hrv-k-bentuk_teks">Teks bentuk dari file</label>
                        <input id="hrv-k-bentuk_teks" type="text" name="data[bentuk_teks]" value="{{ $v('bentuk_teks') }}" maxlength="500">
                        <span class="hrv-sub">Disimpan apa adanya bila tidak ada bentuk yang dipilih dari daftar.</span>
                        @foreach($salah['bentuk_teks'] ?? [] as $pesan)<span class="salah">{{ $pesan }}</span>@endforeach
                      </span>
                    @endif
                  @elseif(isset($pilihanTetap[$k]))
                    <select id="{{ $id }}" name="data[{{ $k }}]">@foreach($pilihanTetap[$k] as $nilai => $nama)<option value="{{ $nilai }}" @selected($v($k) === (string) $nilai)>{{ $nama }}</option>@endforeach</select>
                  @elseif(in_array($k, $tanggal, true))
                    <input id="{{ $id }}" type="date" name="data[{{ $k }}]" value="{{ $v($k) }}">
                  @elseif(in_array($k, $panjang, true))
                    <textarea id="{{ $id }}" name="data[{{ $k }}]" rows="{{ $k === 'uraian' ? 4 : 2 }}">{{ $v($k) }}</textarea>
                  @else
                    <input id="{{ $id }}" type="text" name="data[{{ $k }}]" value="{{ $v($k) }}" @if(str_starts_with($k, 'nilai')) inputmode="numeric" @endif>
                  @endif
                  @foreach($salah[$k] ?? [] as $pesan)<span class="salah">{{ $pesan }}</span>@endforeach
                </td>
                <td class="keadaan">
                  <span class="hrv-badge {{ $kd['nada'] }}">{{ $kd['nama'] }}</span>
                  @if($b['alasan'])<span class="hrv-sub">{{ $b['alasan'] }}</span>@elseif($b['cara'] && $b['keadaan'] !== 'kosong')<span class="hrv-sub">{{ $b['cara'] }}</span>@endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <p class="hint"><span class="wajib">*</span> wajib diisi sebelum impor.</p>
        <div class="hrv-decisions"><input type="hidden" name="ikut" value="0"><label class="hrv-check"><input type="checkbox" name="ikut" value="1" @checked(old('ikut', $row['ikut']))><span>Sertakan baris ini dalam impor</span></label>
          <input type="hidden" name="diperiksa" value="0"><label class="hrv-check"><input type="checkbox" name="diperiksa" value="1" @checked(old('diperiksa', $row['diperiksa']))><span>Saya sudah memeriksa data ini, termasuk peringatan dan perbedaannya dengan file asli.</span></label>
          @if(!$draf->diimpor_pada)<button type="submit" class="btn btn-p"><x-ikon n="Check" :s="16" /> Simpan review</button>@endif
        </div>
      </fieldset>
    </form>
  </section>

  @if($lain)
    <details class="hrv-card hrv-lain"><summary>Kolom file lain yang tidak disimpan ({{ count($lain) }})</summary>
      <dl>@foreach($lain as $x)<dt>{{ $x['judul'] }}@if($x['ket'])<span class="hrv-sub">{{ $x['ket'] }}</span>@endif</dt><dd>{{ $x['isi'] !== '' ? $x['isi'] : '—' }}</dd>@endforeach</dl>
    </details>
  @endif
</div>
@endsection
