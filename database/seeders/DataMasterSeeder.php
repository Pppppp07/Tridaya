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

/**
 * Data master yang sama persis dengan prototipe.
 *
 * Kedua artefak diperagakan berdampingan, dan data contohnya dibaca dari
 * berkas yang sama (`database/data/data-contoh.json`). Nama satuan kerja,
 * bentuk tindak lanjut, dan kategori yang berbeda satu huruf saja membuat
 * data contohnya tidak bisa dipasang.
 */
class DataMasterSeeder extends Seeder
{
    /**
     * Enam belas satuan kerja, disalin dari lembar pemantauan SETBA. Nama
     * panjangnya yang dipakai di surat; nama pendeknya yang dipakai di layar.
     *
     * Unsur kelima nama singkat unitnya ('medan') — dulu bagian depan email
     * akunnya; sekarang dipakai uji untuk menyebut unit kerja.
     *
     * @return list<array{0:string, 1:string, 2:string, 3:string, 4:string}> kode, nama, pendek, jenis, alias
     */
    public static function satker(): array
    {
        $balai = 'Balai Pengembangan Kompetensi Pekerjaan Umum dan Perumahan Rakyat Wilayah ';

        return [
            ['SETBA-BPSDM', 'Sekretariat Badan Pengembangan Sumber Daya Manusia', 'Sekretariat BPSDM', 'sekretariat', 'sekretariat'],
            ['PUSAT-TALENTA', 'Pusat Pengelolaan Talenta', 'Pusat Pengelolaan Talenta', 'pusat', 'talenta'],
            ['PUSBANGKOM-SDACKPS', 'Pusat Pengembangan Kompetensi Sumber Daya Air, Cipta Karya dan Prasarana Strategis', 'Pusbangkom SDACKPS', 'pusat', 'sdackps'],
            ['PUSBANGKOM-BMPIPIW', 'Pusat Pengembangan Kompetensi Bina Marga, Pembiayaan Infrastruktur dan Pengembangan Infrastruktur Wilayah', 'Pusbangkom BMPIPIW', 'pusat', 'bmpipiw'],
            ['PUSBANGKOM-MANAJEMEN', 'Pusat Pengembangan Kompetensi Manajemen', 'Pusbangkom Manajemen', 'pusat', 'manajemen'],
            ['POLTEK-PU', 'Politeknik Pekerjaan Umum', 'Politeknik PU', 'politeknik', 'politeknik'],
            ['BALAI-PENILAIAN', 'Balai Penilaian Kompetensi', 'Balai Penilaian Kompetensi', 'balai', 'penilaian'],
            ['BALAI-1-MEDAN', $balai.'I Medan', 'Balai Wil. I Medan', 'balai', 'medan'],
            ['BALAI-2-PALEMBANG', $balai.'II Palembang', 'Balai Wil. II Palembang', 'balai', 'palembang'],
            ['BALAI-3-JAKARTA', $balai.'III Jakarta', 'Balai Wil. III Jakarta', 'balai', 'jakarta'],
            ['BALAI-4-BANDUNG', $balai.'IV Bandung', 'Balai Wil. IV Bandung', 'balai', 'bandung'],
            ['BALAI-5-YOGYAKARTA', $balai.'V Yogyakarta', 'Balai Wil. V Yogyakarta', 'balai', 'yogyakarta'],
            ['BALAI-6-SURABAYA', $balai.'VI Surabaya', 'Balai Wil. VI Surabaya', 'balai', 'surabaya'],
            ['BALAI-7-BANJARMASIN', $balai.'VII Banjarmasin', 'Balai Wil. VII Banjarmasin', 'balai', 'banjarmasin'],
            ['BALAI-8-MAKASSAR', $balai.'VIII Makassar', 'Balai Wil. VIII Makassar', 'balai', 'makassar'],
            ['BALAI-9-JAYAPURA', $balai.'IX Jayapura', 'Balai Wil. IX Jayapura', 'balai', 'jayapura'],
        ];
    }

    public function run(): void
    {
        $satker = collect(self::satker())->mapWithKeys(fn ($s) => [
            $s[0] => Satker::updateOrCreate(
                ['kode' => $s[0]],
                ['nama' => $s[1], 'nama_pendek' => $s[2], 'jenis' => $s[3]],
            ),
        ]);

        /* ---------- kategori temuan ----------
           Penggolongan dari pemeriksanya sendiri, tertulis apa adanya dari
           suratnya. LHP: tiga golongan lembar pemantauan (292 SPI, 38
           KEPATUHAN). LHA: aspek pemeriksaan Inspektorat. */
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

        /* ---------- bentuk tindak lanjut ----------
           perlu_nilai menandai bentuk yang lazimnya membawa kewajiban nilai:
           yang menyetor, yang menyerahkan aset, dan yang memperbaiki
           pekerjaan. */
        $perluNilai = [
            'Bukti setor ke kas negara',
            'Penyetoran ke kas negara',
            'Penyerahan barang atau aset kepada negara',
            'Perbaikan hasil pekerjaan',
        ];
        foreach (\App\Support\BentukTindakLanjut::nama() as $i => $nama) {
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::BENTUK_TL->value,
                'nama' => $nama,
            ], [
                'perlu_nilai' => in_array($nama, $perluNilai, true),
                'urutan' => $i + 1,
                'aktif' => true,
            ]);
        }

        /* ---------- kategori internal ----------
           Pengelompokan BPSDM sendiri, dengan warnanya — dipakai grafik
           Ringkasan, jadi menambah kategori tidak perlu menyentuh kode. */
        foreach ([
            ['Perjalanan dinas dan penginapan', 'hijau'],
            ['Barang milik negara', 'merah'],
            ['Pengadaan barang dan jasa', 'jingga'],
            ['Honorarium dan standar biaya', 'kuning'],
            ['Penerimaan negara bukan pajak', 'biru'],
            ['Pertanggungjawaban belanja', 'ungu'],
            ['Ketidaksesuaian pedoman', 'tosca'],
            ['Lainnya', 'abu'],
        ] as $i => [$nama, $warna]) {
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::KATEGORI_INTERN->value,
                'nama' => $nama,
            ], ['urutan' => $i + 1, 'warna' => $warna, 'aktif' => true]);
        }

        /* ---------- jenis dokumen ----------
           Jenis baku untuk berkas yang diunggah. Berkas yang diminta per nama
           ("Nota Konfirmasi KPPN setoran sisa") membawa sebutannya sendiri di
           `lampirans.label_jenis`. */
        foreach ([
            'Surat laporan pemeriksaan', 'Bukti setor kas negara (SSBP)',
            'Nota Konfirmasi KPPN', 'Berita acara', 'Surat keputusan',
            'Dokumen perbaikan', 'Laporan hasil perbaikan',
            'Surat hasil verifikasi', 'Foto atau dokumentasi', 'Lainnya',
        ] as $i => $nama) {
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::JENIS_DOKUMEN->value,
                'nama' => $nama,
            ], ['urutan' => $i + 1]);
        }

        /* ---------- alasan tidak dapat ditindaklanjuti ----------
           Daftarnya baku menurut ketentuan, bukan teks bebas. */
        foreach ([
            'Keadaan kahar',
            'Sakit, dibuktikan surat keterangan dokter',
            'Menjadi tersangka dan ditahan',
            'Menjadi terpidana',
            'Alasan sah lain berdasarkan peraturan perundang-undangan',
        ] as $i => $nama) {
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::ALASAN_TD->value,
                'nama' => $nama,
            ], ['urutan' => $i + 1]);
        }

        /* ---------- sifat rekomendasi ----------
           Yang menyangkut kerugian negara menuntut penyetoran; yang
           administratif cukup dilengkapi dokumen atau diperbaiki prosedurnya. */
        foreach (['Administratif', 'Informasi kerugian negara'] as $i => $nama) {
            Referensi::updateOrCreate([
                'jenis' => JenisReferensi::SIFAT_REKOM->value,
                'nama' => $nama,
            ], ['urutan' => $i + 1, 'perlu_nilai' => $nama === 'Informasi kerugian negara']);
        }

        /* ---------- akun contoh ----------
           Satu akun per peran. Ganti seluruh password ini sebelum aplikasi
           dipakai sungguhan. */
        $akun = [
            ['Petugas Setba', 'setba@contoh.test', PeranPengguna::SETBA, 'SETBA-BPSDM'],
            ['Petugas UKI', 'uki@contoh.test', PeranPengguna::UKI, null],
            ['Petugas Inspektorat', 'inspektorat@contoh.test', PeranPengguna::INSPEKTORAT, null],
            ['Pimpinan', 'pimpinan@contoh.test', PeranPengguna::PIMPINAN, null],
            /* Penjaga data (27 Sep) — hanya melihat log aktivitas dan
               rekomendasi. */
            ['Petugas DTI', 'dti@contoh.test', PeranPengguna::DTI, null],
            ['Administrator', 'admin@contoh.test', PeranPengguna::ADMIN, null],
        ];

        /* ---------- penanggung jawab tiap unit kerja ----------
           Satu akun per unit kerja, dan akun itu milik penanggung jawabnya —
           orang pertama tiap unit di IRM contoh (App\Support\DirektoriIrm),
           sama dengan prototipe. Di prototipe peran "Satuan kerja" memilih
           unitnya di bilah samping; di sini unitnya melekat pada akunnya.

           Akun lama ber-email unit (medan@contoh.test) dipindahkan ke
           penanggung jawabnya — password-nya tidak ditimpa. Menyemai ulang
           mengembalikan penanggung jawab contoh: akun satuan kerja lain di
           unit yang sama dinonaktifkan. */
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
            /* Kuncinya email saja. Password yang sudah diganti tidak boleh
               ditimpa kembali jadi password contoh. */
            User::firstOrCreate([
                'email' => $email,
            ], [
                'name' => $nama,
                'password' => Hash::make('rahasia123'),
                'peran' => $peran->value,
                'satker_id' => $kodeSatker ? $satker[$kodeSatker]->id : null,
                'jabatan' => $peran->nama(),
            ]);
        }

        /* Akun pusat dihubungkan ke pegawainya di direktori menurut email (27
           Sep): NIP, nama, dan jabatannya diambil dari sana. Masuk lewat SSO
           mengenali orang dari NIP — akun tanpa NIP tidak akan pernah bisa
           masuk lewat SSO. Password dan perannya tidak disentuh. */
        Akun::hubungkanKeDirektori(User::whereNull('nip')->get());
    }
}
