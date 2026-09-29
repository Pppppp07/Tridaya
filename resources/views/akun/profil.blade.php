@php
  use App\Enums\PeranPengguna as P;
  use App\Support\DirektoriIrm;

  /* Tab Profil — padanan `TabProfilAkun` prototipe. Hanya dibaca: nama, NIP,
     jabatan, dan email disamakan dengan eHRM tiap masuk lewat SSO
     (App\Support\Sso\Otorisasi); unit organisasinya dibaca dari direktori. */
  $satker = $u->peran === P::SATKER;
@endphp
<x-dm-kepala judul="Profil" ket="Data kepegawaian Anda, diambil dari direktori pegawai (eHRM)."
  :info="['Nama, NIP, jabatan, dan unit disamakan dengan eHRM setiap kali Anda masuk lewat SSO, jadi tidak diubah di sini.',
    'Kalau ada yang salah, perbaiki di eHRM, atau hubungi Setba.']" />

<div class="ak-blok">
  <h3>Data pegawai</h3>
  <x-fakta :isi="array_values(array_filter([
    ['l' => 'Nama lengkap', 'v' => e($u->name)],
    ['l' => 'NIP', 'v' => e(DirektoriIrm::nipTampil($u->nip)), 'mono' => true],
    ['l' => 'Jabatan', 'v' => e($u->jabatan ?: '—')],
    ['l' => 'Unit organisasi', 'v' => e($pegawai['unitNama'] ?? '—')],
    ['l' => 'Email', 'v' => e($u->email ?: '—')],
    $satker ? ['l' => 'Penanggung jawab unit', 'v' => e($u->satker?->nama ?? '—')] : null,
  ]))" />
  @if($errorDirektori)
    <p class="ak-ket" style="margin:10px 0 0">Direktori pegawai sedang tidak bisa dihubungi, jadi unit organisasinya belum tampil.</p>
  @endif
</div>

<div class="ak-blok">
  <h3>Peran dan hak akses</h3>
  <div class="ak-peran">
    <span class="ic-kotak"><x-ikon n="ShieldCheck" :s="17" /></span>
    <span class="teks">
      <b>{{ $satker ? 'Satuan kerja · '.($u->satker?->namaPendek() ?? '') : \App\Support\Rangka::pengguna($u)['peran'] }}</b>
      <span>{{ $u->peran->tugas() }}</span>
    </span>
  </div>
  <ul class="ak-hak">
    {{-- Hanya yang bisa dilakukan (28 Sep); yang tidak bisa tidak disebut. --}}
    @foreach($u->peran->hak() as $teks)
      <li><x-ikon n="CheckCircle2" :s="16" /><span>{{ $teks }}</span></li>
    @endforeach
  </ul>
  <p class="ak-catat">
    <x-ikon n="Lock" :s="13" />
    <span>{{ $satker
      ? 'Akun ini melekat pada penanggung jawab unit kerja. Setba menggantinya di Data master → Unit kerja.'
      : 'Peran diatur Setba atau Admin di Data master → Pengguna.' }} Setiap perubahan tercatat di log aktivitas.</span>
  </p>
</div>
