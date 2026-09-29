@php
  use App\Enums\HasilTelaah;
  use App\Models\Laporan;
  use App\Models\Temuan;
  use App\Support\Tampil;

  /* Satu temuan beserta rekomendasinya — padanan BlokTemuan. Selalu tertutup
     saat pertama tampil; yang genting tetap tercetak di kepalanya. */
  $mendesak = $tem->rekomendasi->filter(fn ($r) => $r->perluPerhatian())->count();
  $nilai = $tem->nilaiTemuan();
  $ditagih = (int) $tem->rekomendasi->sum('nilai_pulih');
  $kembali = (int) $tem->rekomendasi->sum(fn ($r) => $r->totalSetor());
  $memadai = $tem->rekomendasi->filter(fn ($r) => $r->keadaanUnor() === HasilTelaah::M)->count();
  $idIsi = 'temuan-'.$tem->id;

  /* Keterangan temuannya berbaris ke bawah (27 Sep) — padanan daftar Fakta di
     BlokTemuan, bentuk yang sama dengan Uraian temuan di rincian rekomendasi.
     Angka uangnya memakai nama dan urutan Pemulihan dana tingkat laporan:
     tagihan disebut kalau berbeda dari nilai temuannya, yang sudah dan belum
     dipulihkan hanya kalau memang ada yang ditagih. Judul, butir, dan kode
     tidak diulang — kepala bloknya sudah menyebutnya. */
  $uang = $nilai > 0 && ! $tem->hanya_terperiksa;
  $sisa = $ditagih - $kembali;
  $angka = fn (string $teks) => '<b class="angkafakta">'.$teks.'</b>';
  $faktaTemuan = [
    ['l' => 'Kategori temuan', 'ket' => Temuan::ketKategori($jenis),
      'v' => view('components.kategori-temuan', ['nama' => $tem->kategori?->nama])->render()],
    ['l' => 'Kategori internal', 'ket' => Temuan::KET_KATEGORI_INTERN,
      'v' => view('components.tag-kategori', ['kat' => $tem->kategoriIntern, 'polos' => true])->render()],
    ['l' => 'Sebab', 'v' => e($tem->sebab ?: '—')],
    ['l' => 'Akibat', 'v' => e($tem->akibat ?: '—')],
    $uang ? ['l' => 'Nilai temuan', 'v' => $angka(Tampil::rupiah($nilai))] : null,
    $uang && $ditagih > 0 && $ditagih !== $nilai
      ? ['l' => 'Tagihan rekomendasi', 'ket' => Laporan::KET_TAGIHAN, 'v' => $angka(Tampil::rupiah($ditagih))] : null,
    $uang && $ditagih < $nilai
      ? ['l' => 'Administratif', 'c' => 'var(--jingga)', 'ket' => Laporan::KET_ADMINISTRATIF,
          'v' => $angka(Tampil::rupiah($nilai - $ditagih))] : null,
    $uang && $ditagih > 0
      ? ['l' => 'Sudah dipulihkan', 'c' => 'var(--ok)', 'v' => $angka($kembali ? Tampil::rupiah($kembali) : 'Rp 0')] : null,
    $uang && $ditagih > 0
      ? ['l' => $sisa > 0 ? 'Sisa yang harus dipulihkan' : 'Sisa tagihan', 'c' => $sisa > 0 ? 'var(--bad)' : 'var(--ok)',
          'v' => $angka($sisa > 0 ? Tampil::rupiah($sisa) : 'Sudah lunas')] : null,
  ];
@endphp

{{-- data-temuan dan data-satker: sasaran pintasan (26 Sep) — nama satuan kerja
     dan angka di kepala halaman membuka blok ini lalu menyorot barisnya. --}}
<div class="temblok" data-temblok data-temuan="{{ $tem->id }}" data-satker="{{ $tem->satkers->pluck('id')->join('|') }}">
  <button type="button" class="kep" aria-expanded="false" aria-controls="{{ $idIsi }}" data-buka-temuan>
    <span class="panah" style="color:var(--ink-3);flex:none">›</span>
    <span class="mono" style="font-size:15px;font-weight:700;color:var(--ink-3);flex:none">{{ $i + 1 }}</span>
    <span style="flex:1;min-width:200px">
      <span style="display:block;font-size:14px;font-weight:600;letter-spacing:-.01em">{{ $tem->judul }}</span>
      <span class="lbl" style="display:block;margin-top:3px">
        {{ $tem->kode }} · butir {{ $tem->nomor_pada_surat }} · {{ $tem->rekomendasi->count() }} rekomendasi{{ $memadai > 0 ? ' · '.$memadai.' '.mb_strtolower(HasilTelaah::M->nama($jenis)) : '' }}{{ $nilai > 0 ? ' · '.Tampil::rupiahSingkat($nilai) : '' }}
      </span>
    </span>
    @if($mendesak > 0)
      <span class="lbl" style="color:var(--bad);font-weight:600;flex:none">{{ $mendesak }} perlu perhatian</span>
    @endif
    @if($tem->hanya_terperiksa)
      <span class="pantau" style="flex:none"><x-ikon n="Eye" :s="12" /> untuk diketahui</span>
    @else
      <x-cap :s="\App\Models\Rekomendasi::simpulkan($tem->rekomendasi)" :jenis="$jenis" />
    @endif
  </button>

  <div class="isi isitem" id="{{ $idIsi }}" data-isi-temuan>
    <x-fakta :isi="$faktaTemuan" />

    @if($tem->hanya_terperiksa)
      <div class="pesan" style="margin:0">
        <x-ikon n="Eye" :s="16" />
        <span>Temuan ini tercatat atas nama satuan kerja Anda, tetapi tindak lanjutnya menjadi tugas satuan kerja lain. Tidak ada yang perlu Anda kerjakan di sini.</span>
      </div>
    @endif

    <div class="tw">
      <table data-tabel-temuan>
        <thead>
          <tr><th class="num">No</th><th>Uraian rekomendasi</th><th>Kode</th><th>Satuan kerja</th><th>Tenggat jawab</th>
            <th>Kemajuan <x-info :teks="\App\Support\TindakLanjutRingkas::KETERANGAN" /></th></tr>
        </thead>
        <tbody>
          @foreach($tem->rekomendasi as $j => $rek)
            @php
              $lewat = $rek->telatTenggat();
              $lewatBatas = $rek->hariLewatPerbaikan();
              $satker = $rek->satkerTampil($peran, $u->satker_id);
            @endphp
            {{-- Menekan barisnya langsung membuka halaman rincian
                 rekomendasinya — sama seperti tabel Rekomendasi. Dulu barisnya
                 membuka rincian kecil di bawahnya; isinya mengulang sebagian
                 halaman rincian dengan lebih sedikit keterangan, dan yang
                 membacanya tetap harus membuka halamannya juga. --}}
            <tr class="bukaan{{ $rek->perluPerhatian() ? ' awas' : '' }}" data-rek="{{ $rek->id }}"
              data-satker="{{ $satker->pluck('id')->join('|') }}"
              data-href="{{ route('rekomendasi.show', ['rekomendasi' => $rek, 'dari' => 'laporan']) }}">
              <td class="num mono" style="font-size:12px">
                <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>
                {{ $i + 1 }}.{{ $j + 1 }}
              </td>
              <td style="max-width:380px;font-size:13px">{{ $rek->uraian }}</td>
              <td class="mono" style="font-size:11.5px">
                {{ $rek->refLhp() }}
                <div class="lbl" style="margin-top:3px">{{ $rek->kode }}</div>
              </td>
              <td style="font-size:12.5px">{{ $satker->count() === 1 ? $satker->first()->namaPendek() : ($satker->count() ? $satker->count().' satuan kerja' : '—') }}</td>
              <td class="mono" style="font-size:12px;color:{{ $lewat ? 'var(--bad)' : 'inherit' }}">
                {{ Tampil::tgl($rek->tenggat_jawab) }}
                @if($lewat)<div class="lbl" style="color:var(--bad)">{{ Tampil::lamaTelat($rek->lewatTenggat()) }}</div>@endif
                @if($lewatBatas > 0)<div class="lbl" style="color:var(--bad)">lewat batas perbaikan {{ $lewatBatas }} hari</div>@endif
              </td>
              <td><x-kemajuan :rek="$rek" /></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
