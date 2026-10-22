<?php

namespace Database\Seeders;

use App\Enums\JenisReferensi;
use App\Enums\PeranPengguna;
use App\Enums\SumberLaporan;
use App\Models\KategoriTemuan;
use App\Models\Referensi;
use App\Models\Satker;
use App\Models\User;
use App\Support\Akun;
use App\Support\DirektoriIrm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Data master: satuan kerja, bentuk tindak lanjut, kategori, sifat, akun contoh. */
class DataMasterSeeder extends Seeder
{
    /**
     * Enam belas satuan kerja dari lembar pemantauan: nama panjang untuk surat, pendek untuk layar,
     * unsur keempat kode singkat ('medan').
     */
    public static function satker(): array
    {
        $balai = 'Balai Pengembangan Kompetensi Pekerjaan Umum dan Perumahan Rakyat Wilayah ';

        return [
            ['SETBA-BPSDM', 'Sekretariat Badan Pengembangan Sumber Daya Manusia', 'Sekretariat BPSDM', 'sekretariat'],
            ['PUSAT-TALENTA', 'Pusat Pengelolaan Talenta', 'Pusat Pengelolaan Talenta', 'talenta'],
            ['PUSBANGKOM-SDACKPS', 'Pusat Pengembangan Kompetensi Sumber Daya Air, Cipta Karya dan Prasarana Strategis', 'Pusbangkom SDACKPS', 'sdackps'],
            ['PUSBANGKOM-BMPIPIW', 'Pusat Pengembangan Kompetensi Bina Marga, Pembiayaan Infrastruktur dan Pengembangan Infrastruktur Wilayah', 'Pusbangkom BMPIPIW', 'bmpipiw'],
            ['PUSBANGKOM-MANAJEMEN', 'Pusat Pengembangan Kompetensi Manajemen', 'Pusbangkom Manajemen', 'manajemen'],
            ['POLTEK-PU', 'Politeknik Pekerjaan Umum', 'Politeknik PU', 'politeknik'],
            ['BALAI-PENILAIAN', 'Balai Penilaian Kompetensi', 'Balai Penilaian Kompetensi', 'penilaian'],
            ['BALAI-1-MEDAN', $balai.'I Medan', 'Balai Wil. I Medan', 'medan'],
            ['BALAI-2-PALEMBANG', $balai.'II Palembang', 'Balai Wil. II Palembang', 'palembang'],
            ['BALAI-3-JAKARTA', $balai.'III Jakarta', 'Balai Wil. III Jakarta', 'jakarta'],
            ['BALAI-4-BANDUNG', $balai.'IV Bandung', 'Balai Wil. IV Bandung', 'bandung'],
            ['BALAI-5-YOGYAKARTA', $balai.'V Yogyakarta', 'Balai Wil. V Yogyakarta', 'yogyakarta'],
            ['BALAI-6-SURABAYA', $balai.'VI Surabaya', 'Balai Wil. VI Surabaya', 'surabaya'],
            ['BALAI-7-BANJARMASIN', $balai.'VII Banjarmasin', 'Balai Wil. VII Banjarmasin', 'banjarmasin'],
            ['BALAI-8-MAKASSAR', $balai.'VIII Makassar', 'Balai Wil. VIII Makassar', 'makassar'],
            ['BALAI-9-JAYAPURA', $balai.'IX Jayapura', 'Balai Wil. IX Jayapura', 'jayapura'],
        ];
    }

    public function run(): void
    {
        $satker = collect(self::satker())->mapWithKeys(fn ($s) => [
            $s[0] => Satker::updateOrCreate(
                ['kode' => $s[0]],
                ['nama' => $s[1], 'nama_pendek' => $s[2]],
            ),
        ]);

        /* ---- Kategori temuan ----
           Dari pemeriksanya, apa adanya: LHP SPI/KEPATUHAN, LHA aspek pemeriksaan Inspektorat. */
        $kategori = [
            [SumberLaporan::LHP, ['SPI', 'Kepatuhan', 'Lain-lain']],
            [SumberLaporan::LHA, ['Aspek efisiensi', 'Efektivitas dan efisiensi',
                'Ketaatan dalam peraturan perundangan', 'Risiko fraud']],
        ];
        foreach ($kategori as [$sumber, $daftar]) {
            foreach ($daftar as $i => $nama) {
                KategoriTemuan::updateOrCreate(
                    ['sumber' => $sumber->value, 'nama' => $nama],
                    ['urutan' => $i + 1, 'aktif' => true],
                );
            }
        }

        /* ---- Bentuk tindak lanjut ----
           perlu_nilai: bentuk yang lazim membawa kewajiban nilai. */
        $perluNilai = [
            'Bukti setor ke kas negara',
            'Penyetoran ke kas negara',
            'Penyerahan barang atau aset kepada negara',
            'Perbaikan hasil pekerjaan',
        ];
        foreach (\App\Support\BentukTindakLanjut::peta() as $nama => $dokumen) {
            $i = array_search($nama, \App\Support\BentukTindakLanjut::nama(), true);
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::BENTUK_TL->value,
                'nama' => $nama,
            ], [
                'dokumen' => $dokumen,
                'perlu_nilai' => in_array($nama, $perluNilai, true),
                'urutan' => $i + 1,
                'aktif' => true,
            ]);
        }

        /* ---- Kategori internal ---- */
        foreach ([
            'Perjalanan dinas dan penginapan',
            'Barang milik negara',
            'Pengadaan barang dan jasa',
            'Honorarium dan standar biaya',
            'Penerimaan negara bukan pajak',
            'Pertanggungjawaban belanja',
            'Ketidaksesuaian pedoman',
            'Lainnya',
        ] as $i => $nama) {
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::KATEGORI_INTERN->value,
                'nama' => $nama,
            ], ['urutan' => $i + 1, 'aktif' => true]);
        }

        /* ---- Sifat rekomendasi ----
           Kerugian negara menuntut penyetoran; administratif cukup dokumen atau perbaikan
           prosedur. */
        foreach (['Administratif', 'Informasi kerugian negara'] as $i => $nama) {
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::SIFAT_REKOM->value,
                'nama' => $nama,
            ], ['urutan' => $i + 1, 'perlu_nilai' => $nama === 'Informasi kerugian negara']);
        }

        /* ---- Akun contoh ----
           Satu akun per peran. Ganti seluruh password ini sebelum dipakai sungguhan. */
        $akun = [
            ['Petugas Setba', 'setba@contoh.test', PeranPengguna::SETBA, 'SETBA-BPSDM'],
            ['Petugas UKI', 'uki@contoh.test', PeranPengguna::UKI, null],
            ['Petugas Inspektorat', 'inspektorat@contoh.test', PeranPengguna::INSPEKTORAT, null],
            ['Pimpinan Medan', 'pimpinan@contoh.test', PeranPengguna::PIMPINAN, 'BALAI-1-MEDAN'],
            /* DTI: hanya melihat log aktivitas dan rekomendasi. */
            ['Petugas DTI', 'dti@contoh.test', PeranPengguna::DTI, null],
            ['Administrator', 'admin@contoh.test', PeranPengguna::ADMIN, null],
        ];

        /* ---- Penanggung jawab tiap unit kerja ----
           Satu akun per unit, milik orang pertama tiap unit di IRM contoh. */
        foreach (DirektoriIrm::semua()->where('pj', true) as $p) {
            $unit = $satker[$p['unit']] ?? null;
            if (! $unit) {
                continue;
            }
            $pj = User::where('email', $p['email'])->first()
                ?? User::where('peran', PeranPengguna::SATKER->value)->where('satker_id', $unit->id)
                    ->orderBy('id')->first()
                ?? new User(['password' => Hash::make('rahasia123')]);
            $pj->fill([
                'name' => $p['nama'], 'email' => $p['email'], 'nip' => $p['nip'], 'jabatan' => $p['jabatan'],
                'peran' => PeranPengguna::SATKER->value, 'satker_id' => $unit->id, 'aktif' => true,
            ])->save();
            User::where('peran', PeranPengguna::SATKER->value)->where('satker_id', $unit->id)
                ->whereKeyNot($pj->id)->update(['aktif' => false]);
        }

        foreach ($akun as [$nama, $email, $peran, $kodeSatker]) {
            /* Kuncinya email; password yang sudah diganti tidak ditimpa. */
            User::firstOrCreate([
                'email' => $email,
            ], [
                'name' => $nama,
                'password' => Hash::make('rahasia123'),
                'peran' => $peran->value,
                'satker_id' => $kodeSatker && $peran !== PeranPengguna::PIMPINAN ? $satker[$kodeSatker]->id : null,
                'pimpinan_satker_ids' => $peran === PeranPengguna::PIMPINAN ? [$satker[$kodeSatker]->id] : null,
                'jabatan' => $peran->nama(),
            ]);
        }

        /* Akun pusat dihubungkan ke pegawainya menurut email (NIP untuk SSO). */
        Akun::hubungkanKeDirektori(User::whereNull('nip')->get());
    }
}
