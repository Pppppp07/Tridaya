@php
  use App\Enums\PosisiBerkas;

  /* Setba meneruskan berkas SATU baris — padanan panelSetbaBaris. Isian
     suratnya langsung terbuka; tidak ada tombol pembuka lebih dulu. Sejak
     25 Sep berbentuk panel kerja (`x-panel-kerja`). */
  $keUki = $x->pos() === PosisiBerkas::SETBA_TINJAU;
  $terakhir = $suratTerakhir[$keUki ? 'uki' : 'inspektorat'] ?? null;
  $tujuan = $keUki ? 'UKI' : 'Inspektorat';
  $banyakBentuk = $r->semuaBaris()->where('satker_id', $x->satker_id)->pluck('tindakan_id')->unique()->count() > 1;
  $pastikan = [
    'judul' => "Teruskan berkas ke {$tujuan}?",
    'ket' => 'Berkas '.$x->satker->namaPendek().($banyakBentuk ? ' ('.$x->tindakan->namaBentuk().')' : '')
      .' keluar dari meja Setba. Untuk menariknya kembali harus menunggu pihak tujuan mengembalikannya. Satuan kerja lain pada rekomendasi ini tidak ikut berangkat.',
    'tombol' => "Ya, teruskan ke {$tujuan}",
  ];
  $k = $x->id;
@endphp

<form method="post" action="{{ route('sasaran.teruskan', $x) }}" id="r-terus" data-form-surat
  data-lanjut="Berkas keluar dari meja Setba menuju {{ $tujuan }}.">
  @csrf
  {{-- Nama tindakannya judul, satuan kerjanya pemilik berkas (22 Sep). Dulu:
       label "Tindakan Setba", isi nama satuan kerja. --}}
  <x-panel-kerja ikon="Send" :judul="'Teruskan ke '.$tujuan" wajib
    ket="Nomor, tanggal, dan perihal surat harus terisi" :lanjut="'Berkas keluar dari meja Setba menuju '.$tujuan.'.'"
    :info="[
      $keUki ? 'UKI tidak menerima berkas tanpa surat pengantar dari Setba.' : 'Nomor surat ini yang dirujuk Inspektorat saat menerbitkan CHV.',
      'Yang berangkat hanya satuan kerja pada baris ini. Yang lain tetap di tempatnya.',
    ]">
    <x-slot:kalimat>Berkas <b>{{ $x->satker->namaPendek() }}</b> dikirim bersama surat di bawah ini.</x-slot:kalimat>

    {{-- Surat terakhir ke tujuan yang sama ditawarkan, dan disebut terang-
         terangan supaya tidak terkirim tanpa diperiksa. Barisnya hilang begitu
         nomor suratnya terisi (`pasangFormSurat`). --}}
    @if($terakhir)
      <x-baris-isi label="Surat terakhir" :kunci="'surat-terakhir-'.$k"
        :info="'Surat pengantar terakhir yang dikirim Setba ke '.$tujuan.'.'" :data-surat-terakhir="json_encode($terakhir)">
        <div class="fb-sebaris kerja-teks">
          <span class="mono">{{ $terakhir['nomor'] }}</span>
          <button type="button" class="fb-link" data-pakai-surat>Pakai surat yang sama</button>
        </div>
      </x-baris-isi>
    @endif

    @include('rekomendasi.bagian.isi-surat', [
      'kunci' => 'terus-'.$k,
      'ket' => $keUki ? 'Surat permohonan validasi dari Setba kepada UKI.' : 'Surat permohonan verifikasi dari Setba kepada Inspektorat.',
      'contohNomor' => $keUki ? 'PW.02.02-Sb/128' : 'PW.02.02-Sb/241',
      'contohPerihal' => $keUki ? 'Permohonan validasi tindak lanjut' : 'Permohonan verifikasi tindak lanjut',
    ])

    <x-slot:aksi>
      <button type="submit" class="btn btn-p" data-pastikan='@json($pastikan)' data-butuh-surat>
        <x-ikon n="Send" :s="14" /> Teruskan ke {{ $tujuan }}
      </button>
    </x-slot:aksi>
  </x-panel-kerja>
</form>
