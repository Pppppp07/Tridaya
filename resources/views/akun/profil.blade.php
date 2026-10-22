@php
  use App\Enums\PeranPengguna as P;
  use App\Support\DirektoriIrm;

  /* Hanya dibaca: nama, NIP, jabatan, email disamakan dengan eHRM tiap masuk SSO; unit dari
     direktori. */
  $satker = $u->peranAktif() === P::SATKER;
@endphp
<x-dm-kepala judul="Profil" ket="Data akun dan kepegawaian Anda."
  :info="['Identitas akun, data pegawai, serta peran dan cakupan akses Anda di aplikasi.',
    'Data pegawai berasal dari akun dan direktori yang terhubung. Peran serta cakupan Satker ditentukan oleh Admin DTI.']" />

<div class="ak-blok">
  <h3>Data pegawai</h3>
  <x-fakta :isi="array_values(array_filter([
    ['l' => 'Nama lengkap', 'v' => e($u->name)],
    ['l' => 'NIP', 'v' => e(DirektoriIrm::nipTampil($u->nip)), 'mono' => true],
    ['l' => 'Jabatan', 'v' => e($u->jabatan ?: '—')],
    ['l' => 'Unit organisasi', 'v' => e($pegawai['unitNama'] ?? '—')],
    ['l' => 'Email', 'v' => e($u->email ?: '—')],
    $satker ? ['l' => 'Penanggung jawab unit', 'v' => e($u->satker?->nama ?? '—')] : null,
    $u->punyaPeran(P::PIMPINAN) ? ['l' => 'Satker yang dipimpin', 'v' => e($u->daftarSatkerDipimpin(true) ?: 'Belum ditentukan')] : null,
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
      <span>{{ $u->peranAktif()->tugas() }}</span>
    </span>
  </div>
  <ul class="ak-hak">
    @foreach($u->peranAktif()->hak() as $teks)
      <li><x-ikon n="CheckCircle2" :s="16" /><span>{{ $teks }}</span></li>
    @endforeach
  </ul>
</div>
