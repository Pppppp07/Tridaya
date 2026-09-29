<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\CariController;
use App\Http\Controllers\DataMasterController;
use App\Http\Controllers\PemberitahuanController;
use App\Http\Controllers\LaporanBaruController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MasukController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\RekomendasiController;
use App\Http\Controllers\RingkasanController;
use App\Http\Controllers\SasaranController;
use App\Http\Controllers\SiptlController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\TanggapanController;
use App\Http\Controllers\UnitKerjaController;
use Illuminate\Support\Facades\Route;

Route::get('/masuk', [MasukController::class, 'form'])->name('masuk')->middleware('guest');
/* Login developer (28 Sep): tampilan masuk selama pengembangan — email, kata
   password, dan akun contoh. Halaman /masuk tetap bersih seperti saat dipasang;
   yang ini tidak ditemukan di produksi (MasukController::adaPengembang). */
Route::get('/masuk/pengembang', [MasukController::class, 'pengembang'])->name('masuk.pengembang')->middleware('guest');
/* Selain batas lima kali salah per email (MasukController), satu alamat
   dibatasi 30 kiriman semenit — menebak dengan banyak email sekaligus juga
   tertahan (27 Sep). */
Route::post('/masuk', [MasukController::class, 'masuk'])->middleware(['guest', 'throttle:30,1']);
Route::post('/keluar', [MasukController::class, 'keluar'])->name('keluar');

/* Masuk lewat SSO eHRM (27 Sep). Penyedianya dipilih SIMTLHP_SSO; tanpa itu
   rute-rute ini menjawab 404. Lihat App\Support\Sso. */
Route::middleware(['guest', 'throttle:30,1'])->group(function () {
    Route::get('/masuk/sso', [SsoController::class, 'arahkan'])->name('sso.arahkan');
    Route::get('/masuk/sso/kembali', [SsoController::class, 'kembali'])->name('sso.kembali');
    Route::get('/masuk/sso/simulasi', [SsoController::class, 'simulasi'])->name('sso.simulasi');
    Route::post('/masuk/sso/simulasi', [SsoController::class, 'simulasiPilih'])->name('sso.simulasi.pilih');
});

Route::middleware('auth')->group(function () {
    /* Layar awal mengikuti perannya, sama seperti prototipe: Pimpinan
       mendarat di Ringkasan (berandanya), yang lain di Rekomendasi. */
    Route::get('/', [RekomendasiController::class, 'beranda'])->name('beranda');

    Route::get('/rekomendasi', [RekomendasiController::class, 'index'])->name('rekomendasi.index');
    Route::get('/rekomendasi/{rekomendasi}', [RekomendasiController::class, 'show'])->name('rekomendasi.show');

    /* Semua gerak berkas bekerja pada SASARAN — satu satuan kerja pada satu
       bentuk tindak lanjut. Rekomendasi tidak menempuh proses apa pun. */
    Route::post('/sasaran/{sasaran}/tanggapan', [TanggapanController::class, 'simpan'])->name('tanggapan.simpan');
    Route::post('/sasaran/{sasaran}/teruskan', [SasaranController::class, 'teruskan'])->name('sasaran.teruskan');
    Route::post('/sasaran/{sasaran}/putus', [SasaranController::class, 'putus'])->name('sasaran.putus');
    Route::post('/sasaran/{sasaran}/kirim-ulang', [SasaranController::class, 'kirimUlang'])->name('sasaran.kirimUlang');

    /* Urusan SIPTL milik Setba, per satuan kerja. */
    Route::post('/sasaran/{sasaran}/siptl/unggah', [SiptlController::class, 'unggah'])->name('siptl.unggah');
    Route::post('/sasaran/{sasaran}/siptl/status', [SiptlController::class, 'status'])->name('siptl.status');
    Route::post('/rekomendasi/{rekomendasi}/ulang-bpk', [SiptlController::class, 'ulangBpk'])->name('siptl.ulangBpk');

    /* Ditaruh sebelum rute {laporan}, kalau tidak 'baru' terbaca sebagai
       nomor laporan dan halamannya tidak pernah terbuka. */
    Route::get('/laporan/baru', [LaporanBaruController::class, 'form'])->name('laporan.baru');
    Route::post('/laporan/baru', [LaporanBaruController::class, 'simpan'])->name('laporan.baru.simpan');
    Route::post('/laporan/baru/buang', [LaporanBaruController::class, 'buang'])->name('laporan.baru.buang');

    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/{laporan}', [LaporanController::class, 'show'])->name('laporan.show');

    Route::get('/pemberitahuan', [PemberitahuanController::class, 'index'])->name('pemberitahuan');
    Route::post('/pemberitahuan/semua', [PemberitahuanController::class, 'tandaiSemua'])->name('pemberitahuan.semua');
    /* Pemberitahuan dirombak (26 Sep): panel lonceng, tanda per pemberitahuan, dan
       pembaruan berkala. `ringkas` sebelum `{notifikasi}`. */
    Route::post('/pemberitahuan/panel', [PemberitahuanController::class, 'panel'])->name('pemberitahuan.panel');
    Route::get('/pemberitahuan/ringkas', [PemberitahuanController::class, 'ringkas'])->name('pemberitahuan.ringkas');
    Route::post('/pemberitahuan/{notifikasi}/tandai', [PemberitahuanController::class, 'tandai'])->name('pemberitahuan.tandai');
    Route::get('/pemberitahuan/{notifikasi}', [PemberitahuanController::class, 'buka'])->name('pemberitahuan.buka');

    Route::get('/ringkasan', RingkasanController::class)->name('ringkasan');

    /* Pencarian di batang atas: rekomendasi dan laporan yang boleh dilihat. */
    Route::get('/cari', CariController::class)->name('cari');

    Route::get('/data-master', [DataMasterController::class, 'index'])->name('master');
    Route::post('/data-master/kategori', [DataMasterController::class, 'tambah'])->name('master.tambah');
    Route::post('/data-master/kategori/{referensi}', [DataMasterController::class, 'simpan'])->name('master.simpan');
    Route::post('/data-master/kategori/{referensi}/saklar', [DataMasterController::class, 'saklar'])->name('master.saklar');
    Route::post('/data-master/temuan', [DataMasterController::class, 'tambahTemuan'])->name('master.temuan.tambah');
    Route::post('/data-master/temuan/{kategori}', [DataMasterController::class, 'simpanTemuan'])->name('master.temuan.simpan');
    Route::post('/data-master/temuan/{kategori}/saklar', [DataMasterController::class, 'saklarTemuan'])->name('master.temuan.saklar');
    Route::post('/data-master/sifat', [DataMasterController::class, 'tambahSifat'])->name('master.sifat.tambah');
    Route::post('/data-master/sifat/{referensi}', [DataMasterController::class, 'simpanSifat'])->name('master.sifat.simpan');
    Route::post('/data-master/sifat/{referensi}/saklar', [DataMasterController::class, 'saklarSifat'])->name('master.sifat.saklar');
    /* Unit kerja dan penanggung jawabnya — penanggung jawab dipilih dari IRM. */
    Route::post('/data-master/unit', [UnitKerjaController::class, 'tambah'])->name('master.unit.tambah');
    Route::post('/data-master/unit/{satker}', [UnitKerjaController::class, 'simpan'])->name('master.unit.simpan');
    Route::post('/data-master/unit/{satker}/saklar', [UnitKerjaController::class, 'saklar'])->name('master.unit.saklar');
    Route::post('/data-master/unit/{satker}/penanggung-jawab', [UnitKerjaController::class, 'penanggungJawab'])->name('master.unit.pj');
    Route::get('/data-master/unit/{satker}/irm', [UnitKerjaController::class, 'cariIrm'])->name('master.unit.irm');
    /* Pengguna & hak akses (27 Sep): petugas pusat didaftarkan dari
       direktori pegawai menurut NIP, diberi peran, dinonaktifkan. Penanggung
       jawab unit kerja tetap lewat rute unit di atas. */
    Route::get('/data-master/pengguna/direktori', [PenggunaController::class, 'cari'])->name('master.pengguna.cari');
    Route::post('/data-master/pengguna', [PenggunaController::class, 'tambah'])->name('master.pengguna.tambah');
    Route::post('/data-master/pengguna/{pengguna}', [PenggunaController::class, 'simpan'])->name('master.pengguna.simpan');
    Route::post('/data-master/pengguna/{pengguna}/saklar', [PenggunaController::class, 'saklar'])->name('master.pengguna.saklar');

    /* Log aktivitas (27 Sep) — DTI dan Admin. */
    Route::get('/log-aktivitas', [LogController::class, 'index'])->name('log');
    Route::get('/log-aktivitas/unduh', [LogController::class, 'unduh'])->name('log.unduh');

    Route::get('/berkas/{lampiran}', [BerkasController::class, 'show'])->name('berkas.show');

    /* Profil & pengaturan (28 Sep) — akun yang sedang masuk saja. Password
       dibatasi enam kiriman semenit: tanpa itu formulir ini bisa dipakai
       menebak password orang yang meninggalkan komputernya terbuka. */
    Route::get('/akun', [AkunController::class, 'index'])->name('akun');
    Route::post('/akun/password', [AkunController::class, 'password'])->name('akun.password')->middleware('throttle:6,1');
    Route::post('/akun/sesi', [AkunController::class, 'sesi'])->name('akun.sesi');
    Route::post('/akun/setelan', [AkunController::class, 'setelan'])->name('akun.setelan');
    Route::post('/akun/tampilan', [AkunController::class, 'tampilan'])->name('akun.tampilan');
});
